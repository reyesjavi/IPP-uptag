<?php
// includes/auth.php — Control de sesión, roles y seguridad
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/base.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Verificar sesión ──
function estaAutenticado(): bool {
    return isset($_SESSION['usuario_id']) && !empty($_SESSION['usuario_id']);
}

// ── Requerir login básico ──
function requiereLogin(): void {
    if (!estaAutenticado()) {
        header('Location: ' . url('login.php'));
        exit;
    }
    sincronizarAfiliado();
}

// ── Sincronizar id_afiliado en sesión Y en BD ──────────────
function sincronizarAfiliado(): void {
    if (($_SESSION['usuario_rol'] ?? '') !== 'afiliado') return;
    if (!empty($_SESSION['afiliado_id'])) return; // ya sincronizado

    try {
        $pdo = getDB();
        $uid = $_SESSION['usuario_id'];
        $ci  = $_SESSION['usuario_ci'];

        // Paso 1: buscar en usuarios_registrados
        $s = $pdo->prepare("SELECT id_afiliado FROM usuarios_registrados WHERE id_usuario=:id");
        $s->execute([':id' => $uid]);
        $idAfil = $s->fetchColumn();

        // Paso 2: si no tiene, buscar en tabla afiliado por CI y vincularlo
        if (!$idAfil) {
            $a = $pdo->prepare("SELECT id_afiliado FROM afiliado WHERE ci=:ci LIMIT 1");
            $a->execute([':ci' => $ci]);
            $idAfil = $a->fetchColumn();

            if ($idAfil) {
                // Actualizar la BD para que quede persistente
                $pdo->prepare("UPDATE usuarios_registrados SET id_afiliado=:afil WHERE id_usuario=:uid")
                    ->execute([':afil' => $idAfil, ':uid' => $uid]);
            }
        }

        if (!$idAfil) return; // sin afiliado aún, no hay más que hacer

        // Paso 3: cargar datos del afiliado y actualizar sesión
        $af = $pdo->prepare("SELECT nombre, apellido, cod_pm FROM afiliado WHERE id_afiliado=:id");
        $af->execute([':id' => $idAfil]);
        $datos = $af->fetch();

        $_SESSION['afiliado_id']     = $idAfil;
        $_SESSION['afiliado_nombre'] = $datos ? trim($datos['nombre'].' '.$datos['apellido']) : '';
        $_SESSION['afiliado_cod_pm'] = $datos['cod_pm'] ?? null;
        unset($_SESSION['afiliado_cache']);

    } catch (Exception $e) { /* silencioso */ }
}

// ── Requerir rol específico ──
function requiereRol(string ...$roles): void {
    requiereLogin();
    if (!in_array($_SESSION['usuario_rol'] ?? '', $roles)) {
        header('Location: ' . url('acceso_denegado.php'));
        exit;
    }
}

// ── Helpers de rol ──
function esAdmin(): bool {
    return ($_SESSION['usuario_rol'] ?? '') === 'admin';
}
function esAdministrativo(): bool {
    return in_array($_SESSION['usuario_rol'] ?? '', ['admin', 'administrativo']);
}
function esAfiliado(): bool {
    return ($_SESSION['usuario_rol'] ?? '') === 'afiliado';
}

// ── Login con protección anti fuerza bruta + verificación de vigencia ──
function login(string $ci, string $password): array {
    $pdo  = getDB();
    $stmt = $pdo->prepare("
        SELECT u.id_usuario, u.username, u.password_hash, u.rol, u.activo,
               u.intentos_fallidos, u.bloqueado,
               a.id_afiliado, a.nombre, a.apellido, a.cod_pm, a.cod_a
        FROM usuarios_registrados u
        LEFT JOIN afiliado a ON a.id_afiliado = u.id_afiliado
        WHERE u.username = :ci
        LIMIT 1
    ");
    $stmt->execute([':ci' => $ci]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        return ['ok'=>false,'msg'=>'Usuario no encontrado. Verifica tu cédula.'];
    }

    // Cuenta bloqueada por intentos fallidos
    if (!empty($usuario['bloqueado'])) {
        return ['ok'=>false,'msg'=>'Cuenta bloqueada por múltiples intentos fallidos. Contacta a administración.'];
    }

    if (!$usuario['activo']) {
        return ['ok'=>false,'msg'=>'Usuario inactivo. Contacte administración.'];
    }

    // Verificar contraseña
    if (!password_verify($password, $usuario['password_hash'])) {
        // Incrementar intentos fallidos
        $intentos = (int)($usuario['intentos_fallidos'] ?? 0) + 1;
        if ($intentos >= 3) {
            $pdo->prepare("UPDATE usuarios_registrados SET intentos_fallidos=:i, bloqueado=1 WHERE id_usuario=:id")
                ->execute([':i'=>$intentos, ':id'=>$usuario['id_usuario']]);
            return ['ok'=>false,'msg'=>'Demasiados intentos fallidos. Tu cuenta ha sido bloqueada por seguridad.'];
        }
        $pdo->prepare("UPDATE usuarios_registrados SET intentos_fallidos=:i WHERE id_usuario=:id")
            ->execute([':i'=>$intentos, ':id'=>$usuario['id_usuario']]);
        $restantes = 3 - $intentos;
        return ['ok'=>false,'msg'=>"Contraseña incorrecta. Te quedan $restantes intento(s)."];
    }

    // Verificar vigencia anual SOLO para afiliados
    if ($usuario['rol'] === 'afiliado') {
        $vig = $pdo->prepare("
            SELECT v.estado
            FROM vigencia_anual v
            JOIN agremiado ag ON ag.id_agremiado = v.id_agremiado
            WHERE ag.ci = :ci AND v.anio = YEAR(CURDATE()) AND v.estado='activa'
            LIMIT 1
        ");
        $vig->execute([':ci' => $usuario['username']]);
        $tieneVigencia = $vig->fetch();
        $_SESSION['vigencia_activa'] = (bool)$tieneVigencia;
    } else {
        $_SESSION['vigencia_activa'] = true; // admin/administrativo no requieren vigencia
    }

    // Resetear intentos fallidos al éxito
    $pdo->prepare("UPDATE usuarios_registrados SET intentos_fallidos=0, ultimo_acceso=NOW() WHERE id_usuario=:id")
        ->execute([':id'=>$usuario['id_usuario']]);

    // Prevenir Session Fixation
    session_regenerate_id(true);

    $_SESSION['usuario_id']      = $usuario['id_usuario'];
    $_SESSION['usuario_ci']      = $usuario['username'];
    $_SESSION['usuario_rol']     = $usuario['rol'];
    $_SESSION['afiliado_id']     = $usuario['id_afiliado'];
    $_SESSION['afiliado_nombre'] = trim(($usuario['nombre']??'').' '.($usuario['apellido']??''));
    $_SESSION['afiliado_cod_pm'] = $usuario['cod_pm'];

    registrarLog('login', 'Inicio de sesión', $usuario['id_usuario']);

    return ['ok'=>true, 'rol'=>$usuario['rol']];
}

// ── Logout ──
function logout(): void {
    registrarLog('logout', 'Cierre de sesión');
    session_unset();
    session_destroy();
    header('Location: ' . url('login.php'));
    exit;
}

// ── Datos del afiliado (cacheado en sesión) ──
function getAfiliado(bool $forzar = false): array {
    if (!estaAutenticado() || empty($_SESSION['afiliado_id'])) return [];
    if (!$forzar && isset($_SESSION['afiliado_cache'])) {
        return $_SESSION['afiliado_cache'];
    }
    $pdo  = getDB();
    $stmt = $pdo->prepare("
        SELECT a.*, p.costo
        FROM afiliado a
        LEFT JOIN plan_medico p ON p.cod_pm = a.cod_pm
        WHERE a.id_afiliado = :id
    ");
    $stmt->execute([':id' => $_SESSION['afiliado_id']]);
    $result = $stmt->fetch() ?: [];
    $_SESSION['afiliado_cache'] = $result;
    return $result;
}

// ── Registrar log de actividad ──
function registrarLog(string $accion, string $detalle, ?int $userId = null): void {
    try {
        $pdo  = getDB();
        $uid  = $userId ?? ($_SESSION['usuario_id'] ?? null);
        $ip   = $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
        $pdo->prepare("INSERT INTO log_actividad (id_usuario, accion, detalle, ip) VALUES (:uid,:a,:d,:ip)")
            ->execute([':uid'=>$uid, ':a'=>$accion, ':d'=>$detalle, ':ip'=>$ip]);
    } catch (Exception $e) { /* silencioso */ }
}

// ── Redirigir según rol ──
function redirigirSegunRol(): void {
    $rol = $_SESSION['usuario_rol'] ?? 'afiliado';
    $destino = match($rol) {
        'admin', 'administrativo' => url('admin/dashboard.php'),
        default                   => url('dashboard.php'),
    };
    header('Location: ' . $destino);
    exit;
}
