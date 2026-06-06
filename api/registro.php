<?php
// api/registro.php — Registro de agremiados con verificación de identidad por correo
define('API_REQUEST', true);
require_once __DIR__ . '/../config/base.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';

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
    // ══════════════════════════════════════════════════════
    $stmt = $pdo->prepare("SELECT id_agremiado, nombre, apellido, activo, correo FROM agremiado WHERE ci = :ci LIMIT 1");
    $stmt->execute([':ci' => $ci]);
    $agremiado = $stmt->fetch();

    if (!$agremiado) {
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
    if (!$agremiado['activo']) {
        http_response_code(403);
        echo json_encode([
            'ok'     => false,
            'codigo' => 'AGREMIADO_INACTIVO',
            'msg'    => 'Tu agremiación no está activa. Contacta a la administración.'
        ]);
        exit;
    }

    $idAgremiado = $agremiado['id_agremiado'];
    $anioActual  = (int) date('Y');
    $hash        = password_hash($password, PASSWORD_BCRYPT, ['cost'=>12]);
    // El correo de verificación SIEMPRE va al correo de ficha del agremiado,
    // no al que teclea quien rellena el formulario (así se prueba la identidad).
    $correoFicha = trim($agremiado['correo'] ?? '');

    // ══════════════════════════════════════════════════════
    // VALIDACIÓN 3: ¿Ya tiene cuenta en el portal?
    // ══════════════════════════════════════════════════════
    $stmtCuenta = $pdo->prepare("SELECT id_usuario, activo, correo_verificado FROM usuarios_registrados WHERE username = :ci LIMIT 1");
    $stmtCuenta->execute([':ci' => $ci]);
    $cuentaExistente = $stmtCuenta->fetch();

    if ($cuentaExistente) {
        // Cuenta creada pero sin verificar el correo: reenviar el enlace.
        if (empty($cuentaExistente['correo_verificado'])) {
            if ($correoFicha !== '') {
                $tokenPlano = crearTokenVerificacion($pdo, (int)$cuentaExistente['id_usuario']);
                enviarCorreoVerificacion($correoFicha, $agremiado['nombre'], $ci, $tokenPlano);
                echo json_encode([
                    'ok'     => true,
                    'codigo' => 'VERIFICACION_REENVIADA',
                    'msg'    => 'Tu cuenta aún no está verificada. Te reenviamos el enlace de activación al correo registrado en tu ficha de agremiado.'
                ]);
            } else {
                echo json_encode([
                    'ok'     => true,
                    'codigo' => 'PENDIENTE_APROBACION',
                    'msg'    => 'Tu solicitud está pendiente de aprobación por la administración del IPP.'
                ]);
            }
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
            'msg'    => "¡Vigencia renovada para $anioActual, {$agremiado['nombre']}! Ya puedes iniciar sesión.",
            'redirigir' => true
        ]);
        exit;
    }

    // ══════════════════════════════════════════════════════
    // CUENTA NUEVA
    // ══════════════════════════════════════════════════════
    $pdo->beginTransaction();

    // Traer datos completos del agremiado para copiarlos al afiliado
    $datosAgr = $pdo->prepare("SELECT nombre, apellido, fecha_nacimiento, correo, telefono FROM agremiado WHERE id_agremiado = :id");
    $datosAgr->execute([':id' => $idAgremiado]);
    $ag = $datosAgr->fetch();

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
        } elseif (!empty($ag['correo'])) {
            $stmtChkCorreo = $pdo->prepare("SELECT 1 FROM afiliado WHERE correo = :c LIMIT 1");
            $stmtChkCorreo->execute([':c' => $ag['correo']]);
            $correoAfil = $stmtChkCorreo->fetchColumn() ? null : $ag['correo'];
        }

        $pdo->prepare("
            INSERT INTO afiliado (id_agremiado, nombre, apellido, ci, fecha_nacimiento, correo, telefono, fecha_ingreso, activo, cod_a, cod_pm)
            VALUES (:idag, :nom, :ape, :ci, :fnac, :correo, :tel, CURDATE(), 1, NULL, NULL)
        ")->execute([
            ':idag'   => $idAgremiado,
            ':nom'    => $ag['nombre'],
            ':ape'    => $ag['apellido'],
            ':ci'     => $ci,
            ':fnac'   => $ag['fecha_nacimiento'] ?: null,
            ':correo' => $correoAfil,
            ':tel'    => $telefono ?: $ag['telefono'],
        ]);
        $afiliadoVinc = (int) $pdo->lastInsertId();

        // 3) Registrar al afiliado como su propio beneficiario (titular)
        $stmtNumBen = $pdo->prepare("SELECT COALESCE(MAX(numero_beneficiario),0)+1 FROM beneficiario WHERE id_afiliado=:id");
        $stmtNumBen->execute([':id' => $afiliadoVinc]);
        $numBen = (int)$stmtNumBen->fetchColumn();
        $pdo->prepare("
            INSERT IGNORE INTO beneficiario (numero_beneficiario, ci, nombre, apellido, fecha_nacimiento, parentesco, id_afiliado)
            VALUES (:num, :ci, :nom, :ape, :fnac, 'Titular', :afil)
        ")->execute([
            ':num'  => $numBen,
            ':ci'   => $ci,
            ':nom'  => $ag['nombre'],
            ':ape'  => $ag['apellido'],
            ':fnac' => $ag['fecha_nacimiento'] ?: null,
            ':afil' => $afiliadoVinc,
        ]);
    }

    // ── SIN correo en ficha: no podemos probar identidad por correo ──
    //    → encolar como solicitud para aprobación administrativa.
    if ($correoFicha === '') {
        $pdo->prepare("
            INSERT INTO solicitud_registro (ci, correo_contacto, telefono, password_hash, estado)
            VALUES (:ci, :correo, :tel, :hash, 'pendiente')
        ")->execute([
            ':ci'     => $ci,
            ':correo' => $correo ?: null,
            ':tel'    => $telefono ?: ($ag['telefono'] ?? null),
            ':hash'   => $hash,
        ]);
        $pdo->commit();

        registrarLog('registro_pendiente', "Solicitud sin correo de ficha, CI: $ci");
        echo json_encode([
            'ok'     => true,
            'codigo' => 'PENDIENTE_APROBACION',
            'msg'    => 'No hay un correo registrado en tu ficha de agremiado, por lo que tu solicitud quedó pendiente de aprobación por la administración del IPP. Te contactaremos en breve.'
        ]);
        exit;
    }

    // ── CON correo en ficha: crear cuenta PENDIENTE de verificación ──
    //    activo=0 y correo_verificado=0 → login bloqueado hasta confirmar.
    $stmtIns = $pdo->prepare("
        INSERT IGNORE INTO usuarios_registrados (username, password_hash, rol, activo, correo_verificado, id_afiliado)
        VALUES (:user, :hash, 'afiliado', 0, 0, :afil)
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

    // Registrar vigencia anual (el acceso se habilita al verificar el correo)
    registrarVigencia($pdo, $idAgremiado, $anioActual);

    // Generar token de verificación (hasheado en BD)
    $tokenPlano = crearTokenVerificacion($pdo, $idUsuario);

    $pdo->commit();

    // Enviar el correo DESPUÉS del commit, para que el enlace ya sea válido.
    enviarCorreoVerificacion($correoFicha, $agremiado['nombre'], $ci, $tokenPlano);

    try {
        $pdo->prepare("INSERT INTO log_actividad (id_usuario, accion, detalle, ip) VALUES (NULL,'registro_nuevo',:d,:ip)")
            ->execute([':d'=>"Registro pendiente de verificación CI: $ci", ':ip'=>$_SERVER['REMOTE_ADDR']??'']);
    } catch (Exception $e) {}

    echo json_encode([
        'ok'        => true,
        'codigo'    => 'VERIFICACION_ENVIADA',
        'msg'       => "¡Casi listo, {$agremiado['nombre']}! Te enviamos un enlace de activación al correo registrado en tu ficha de agremiado. Revísalo para activar tu cuenta.",
        'nombre'    => $agremiado['nombre'].' '.$agremiado['apellido'],
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

// Invalida tokens previos y crea uno nuevo. Devuelve el token EN CLARO
// (en BD se guarda su hash sha256). El enlace del correo lleva el token en claro.
function crearTokenVerificacion(PDO $pdo, int $idUsuario): string {
    $pdo->prepare("UPDATE verificacion_correo SET usado=1 WHERE id_usuario=:id AND usado=0")
        ->execute([':id'=>$idUsuario]);
    $tokenPlano = bin2hex(random_bytes(32));
    $pdo->prepare("
        INSERT INTO verificacion_correo (id_usuario, token, expira_en, ip_solicitud)
        VALUES (:id, :tok, :exp, :ip)
    ")->execute([
        ':id'  => $idUsuario,
        ':tok' => hash('sha256', $tokenPlano),
        ':exp' => date('Y-m-d H:i:s', strtotime('+24 hours')),
        ':ip'  => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);
    return $tokenPlano;
}

function enviarCorreoVerificacion(string $destino, string $nombre, string $usuario, string $tokenPlano): void {
    $enlace = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://'
            . ($_SERVER['HTTP_HOST'] ?? '')
            . url('verificar_correo.php') . '?token=' . $tokenPlano;

    $cuerpo = "Hola $nombre,\n\n"
            . "Recibimos una solicitud para crear tu cuenta en el portal IPP-UPTAG ($usuario).\n\n"
            . "Haz clic en el siguiente enlace para activar tu cuenta:\n"
            . "$enlace\n\n"
            . "Este enlace expirará en 24 horas.\n\n"
            . "Si no solicitaste esta cuenta, ignora este mensaje.\n\n"
            . "— Sistema IPP-UPTAG";

    enviarCorreo($destino, 'Activa tu cuenta — IPP-UPTAG', $cuerpo);
}
