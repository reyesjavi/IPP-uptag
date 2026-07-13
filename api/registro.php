<?php
// api/registro.php — Registro de agremiados (validación por padrón; cuenta activa al instante)
define('API_REQUEST', true);
require_once __DIR__ . '/../config/base.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../lib/integracion/Integraciones.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'codigo'=>'METODO_NO_PERMITIDO','msg'=>'Solo se acepta POST.']);
    exit;
}

if (!verificarRateLimitIP('registro_intento', 5, 60)) {
    http_response_code(429);
    echo json_encode(['ok'=>false,'codigo'=>'RATE_LIMIT','msg'=>'Demasiadas solicitudes desde esta red. Espera un momento e inténtalo de nuevo.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$ci       = strtoupper(trim($body['ci']       ?? ''));
$password = trim($body['password'] ?? '');
$correo   = trim($body['correo']   ?? '');
$telefono = trim($body['telefono'] ?? '');

// ── Validación básica ──
$errores = [];
if (empty($ci))                 $errores[] = 'La cédula de identidad es obligatoria.';
if (empty($password))           $errores[] = 'La contraseña es obligatoria.';
if (strlen($password) < 16)      $errores[] = 'La contraseña debe tener al menos 16 caracteres.';
if (!empty($correo) && !filter_var($correo, FILTER_VALIDATE_EMAIL))
                                $errores[] = 'El correo electrónico no es válido.';
if (!preg_match('/^[VEJ]-?\d{6,9}$/i', $ci))
                                $errores[] = 'Formato de cédula inválido. Use V-12345678.';

if (!empty($errores)) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'codigo'=>'DATOS_INVALIDOS','errores'=>$errores]);
    exit;
}

registrarLog('registro_intento', "Intento de registro CI: $ci");

try {
    $pdo = getDB();

    // ══════════════════════════════════════════════════════
    // VALIDACIÓN 1: ¿Existe la CI en el padrón de agremiados?
    // El padrón es del sistema de nómina: se consulta SIEMPRE a
    // través del provider (mock hoy, feed real mañana).
    // ══════════════════════════════════════════════════════
    $padron = Integraciones::estadoAfiliacion()->buscarPorCedula($ci);

    if (!$padron) {
        http_response_code(403);
        echo json_encode([
            'ok'     => false,
            'codigo' => 'NO_AGREMIADO',
            'msg'    => 'La cédula ingresada no se encuentra en el padrón de agremiados de la UPTAG. Si crees que es un error, contacta a la administración.'
        ]);
        exit;
    }

    // ══════════════════════════════════════════════════════
    // VALIDACIÓN 2: ¿El agremiado está activo?
    // ══════════════════════════════════════════════════════
    if (!$padron->activo) {
        http_response_code(403);
        echo json_encode([
            'ok'     => false,
            'codigo' => 'AGREMIADO_INACTIVO',
            'msg'    => 'Tu agremiación no está activa. Contacta a la administración.'
        ]);
        exit;
    }

    $idAgremiado = $padron->idAgremiadoLocal;
    $anioActual  = (int) date('Y');
    $hash        = password_hash($password, PASSWORD_BCRYPT, ['cost'=>12]);

    // ══════════════════════════════════════════════════════
    // VALIDACIÓN 3: ¿Ya tiene cuenta en el portal?
    // ══════════════════════════════════════════════════════
    $stmtCuenta = $pdo->prepare("SELECT id_usuario, activo, correo_verificado FROM usuarios_registrados WHERE username = :ci LIMIT 1");
    $stmtCuenta->execute([':ci' => $ci]);
    $cuentaExistente = $stmtCuenta->fetch();

    if ($cuentaExistente) {
        // La verificación por correo fue eliminada: una cuenta que quedó sin
        // verificar (legado) se activa directamente para permitir el acceso.
        if (empty($cuentaExistente['correo_verificado'])) {
            $pdo->prepare("UPDATE usuarios_registrados SET activo=1, correo_verificado=1 WHERE id_usuario=:id")
                ->execute([':id'=>(int)$cuentaExistente['id_usuario']]);
            echo json_encode([
                'ok'     => true,
                'codigo' => 'CUENTA_ACTIVADA',
                'msg'    => 'Tu cuenta ya está activa. Puedes iniciar sesión.'
            ]);
            exit;
        }

        // ══════════════════════════════════════════════════
        // VALIDACIÓN 4: ¿Ya tiene vigencia activa este año?
        // ══════════════════════════════════════════════════
        $stmtVig = $pdo->prepare("SELECT id_vigencia, estado FROM vigencia_anual WHERE id_agremiado = :id AND anio = :anio LIMIT 1");
        $stmtVig->execute([':id'=>$idAgremiado, ':anio'=>$anioActual]);
        $vigencia = $stmtVig->fetch();

        if ($vigencia && $vigencia['estado'] === 'activa') {
            http_response_code(409);
            echo json_encode([
                'ok'     => false,
                'codigo' => 'VIGENCIA_ACTIVA',
                'msg'    => "Ya tienes acceso activo para el año $anioActual. Puedes iniciar sesión directamente."
            ]);
            exit;
        }

        // Tiene cuenta verificada pero venció: renovar vigencia anual
        registrarVigencia($pdo, $idAgremiado, $anioActual);
        echo json_encode([
            'ok'     => true,
            'codigo' => 'VIGENCIA_RENOVADA',
            'msg'    => "¡Vigencia renovada para $anioActual, {$padron->nombre}! Ya puedes iniciar sesión.",
            'redirigir' => true
        ]);
        exit;
    }

    // ══════════════════════════════════════════════════════
    // CUENTA NUEVA
    // ══════════════════════════════════════════════════════
    $pdo->beginTransaction();

    // El afiliado se materializa junto con la cuenta: todo agremiado presente
    // en el padrón obtiene acceso activo de inmediato, sin aprobación previa.
    // Los datos personales se copian del DTO del padrón (provider de nómina).

    // 1) Buscar si ya existe un afiliado con esa CI
    $stmtAfil = $pdo->prepare("SELECT id_afiliado FROM afiliado WHERE ci = :ci LIMIT 1");
    $stmtAfil->execute([':ci' => $ci]);
    $afiliadoVinc = $stmtAfil->fetchColumn();

    // 2) Si NO existe afiliado, crearlo copiando datos del agremiado
    if (!$afiliadoVinc) {
        // Resolver correo del afiliado: usar el del formulario si lo proveyó,
        // o el del agremiado solo si no está ya en uso (UNIQUE KEY)
        $correoAfil = null;
        if (!empty($correo)) {
            $stmtChkCorreo = $pdo->prepare("SELECT 1 FROM afiliado WHERE correo = :c LIMIT 1");
            $stmtChkCorreo->execute([':c' => $correo]);
            if ($stmtChkCorreo->fetchColumn()) {
                $pdo->rollBack();
                http_response_code(409);
                echo json_encode(['ok'=>false,'codigo'=>'CORREO_DUPLICADO','msg'=>'El correo electrónico ingresado ya está registrado por otro usuario.']);
                exit;
            }
            $correoAfil = $correo;
        } elseif (!empty($padron->correo)) {
            $stmtChkCorreo = $pdo->prepare("SELECT 1 FROM afiliado WHERE correo = :c LIMIT 1");
            $stmtChkCorreo->execute([':c' => $padron->correo]);
            $correoAfil = $stmtChkCorreo->fetchColumn() ? null : $padron->correo;
        }

        // tipo_afiliado: fuente preferida el feed de nómina; si el feed no
        // lo trae, queda el default y el admin/afiliado podrá fijarlo luego.
        $tipoAfiliado = $padron->tipoAfiliado ?: 'profesor_activo';

        // Plan de beneficios vigente (config-driven, tabla plan)
        $idPlan = $pdo->query("SELECT id_plan FROM plan WHERE activo = 1 ORDER BY id_plan LIMIT 1")->fetchColumn() ?: null;

        $pdo->prepare("
            INSERT INTO afiliado (id_agremiado, nombre, apellido, ci, fecha_nacimiento, correo, telefono, fecha_ingreso, activo, tipo_afiliado, id_plan, cod_a, cod_pm)
            VALUES (:idag, :nom, :ape, :ci, :fnac, :correo, :tel, CURDATE(), 1, :tipo, :plan, NULL, NULL)
        ")->execute([
            ':idag'   => $idAgremiado,
            ':nom'    => $padron->nombre,
            ':ape'    => $padron->apellido,
            ':ci'     => $ci,
            ':fnac'   => $padron->fechaNacimiento,
            ':correo' => $correoAfil,
            ':tel'    => $telefono ?: $padron->telefono,
            ':tipo'   => $tipoAfiliado,
            ':plan'   => $idPlan,
        ]);
        $afiliadoVinc = (int) $pdo->lastInsertId();

        // 3) Registrar al afiliado como su propio beneficiario (titular)
        $stmtNumBen = $pdo->prepare("SELECT COALESCE(MAX(numero_beneficiario),0)+1 FROM beneficiario WHERE id_afiliado=:id");
        $stmtNumBen->execute([':id' => $afiliadoVinc]);
        $numBen = (int)$stmtNumBen->fetchColumn();
        $pdo->prepare("
            INSERT IGNORE INTO beneficiario (numero_beneficiario, ci, nombre, apellido, fecha_nacimiento, parentesco, id_afiliado)
            VALUES (:num, :ci, :nom, :ape, :fnac, 'titular', :afil)
        ")->execute([
            ':num'  => $numBen,
            ':ci'   => $ci,
            ':nom'  => $padron->nombre,
            ':ape'  => $padron->apellido,
            ':fnac' => $padron->fechaNacimiento,
            ':afil' => $afiliadoVinc,
        ]);
    }

    // ── Crear cuenta ACTIVA para todo agremiado del padrón ──
    //    La pertenencia al padrón de agremiados es la única validación previa;
    //    no se requiere aprobación administrativa ni verificación por correo.
    $stmtIns = $pdo->prepare("
        INSERT IGNORE INTO usuarios_registrados (username, password_hash, rol, activo, correo_verificado, id_afiliado)
        VALUES (:user, :hash, 'afiliado', 1, 1, :afil)
    ");
    $stmtIns->execute([':user'=>$ci, ':hash'=>$hash, ':afil'=>$afiliadoVinc]);

    if ($stmtIns->rowCount() === 0) {
        // Carrera: la cuenta apareció entre la VALIDACIÓN 3 y este INSERT.
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['ok'=>false,'codigo'=>'CUENTA_EXISTENTE','msg'=>'Ya existe una cuenta para esta cédula. Intenta iniciar sesión o recuperar tu contraseña.']);
        exit;
    }
    $idUsuario = (int) $pdo->lastInsertId();

    // Registrar vigencia anual (acceso inmediato).
    registrarVigencia($pdo, $idAgremiado, $anioActual);

    $pdo->commit();

    try {
        $pdo->prepare("INSERT INTO log_actividad (id_usuario, accion, detalle, ip) VALUES (NULL,'registro_nuevo',:d,:ip)")
            ->execute([':d'=>"Registro nuevo (cuenta activa) CI: $ci", ':ip'=>$_SERVER['REMOTE_ADDR']??'']);
    } catch (Exception $e) {}

    echo json_encode([
        'ok'        => true,
        'codigo'    => 'REGISTRO_OK',
        'msg'       => "¡Listo, {$padron->nombre}! Tu cuenta ya está activa. Ya puedes iniciar sesión.",
        'nombre'    => $padron->nombre.' '.$padron->apellido,
        'redirigir' => true,
    ]);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok'=>false,'codigo'=>'ERROR_SERVIDOR','msg'=>'Error interno. Intenta de nuevo más tarde.']);
    error_log('[UPTAG Registro] '.$e->getMessage());
}

// ── Helpers ──────────────────────────────────────────────────

function registrarVigencia(PDO $pdo, int $idAgremiado, int $anio): void {
    $venc = "$anio-12-31";
    $pdo->prepare("
        INSERT INTO vigencia_anual (id_agremiado, anio, fecha_vencimiento, estado)
        VALUES (:id, :anio, :venc, 'activa')
        ON DUPLICATE KEY UPDATE estado='activa', fecha_vencimiento=VALUES(fecha_vencimiento), fecha_registro=CURRENT_DATE
    ")->execute([':id'=>$idAgremiado, ':anio'=>$anio, ':venc'=>$venc]);
}
