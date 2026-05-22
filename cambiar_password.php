<?php
// cambiar_password.php — Procesa el cambio de contraseña
require_once __DIR__ . '/config/base.php';
require_once __DIR__ . '/includes/auth.php';
requiereLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') { verificarCsrf(); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('dashboard.php'));
    exit;
}

$pdo          = getDB();
$usuarioId    = $_SESSION['usuario_id'];
$passActual   = $_POST['pass_actual']   ?? '';
$passNueva    = $_POST['pass_nueva']    ?? '';
$passConfirmar= $_POST['pass_confirmar']?? '';

// ── Validaciones ──────────────────────────────────────────
if (empty($passActual) || empty($passNueva) || empty($passConfirmar)) {
    $_SESSION['flash_pass'] = ['ok'=>false,'msg'=>'Todos los campos son obligatorios.'];
    header('Location: ' . url('perfil.php'));
    exit;
}

if ($passNueva !== $passConfirmar) {
    $_SESSION['flash_pass'] = ['ok'=>false,'msg'=>'Las contraseñas nuevas no coinciden.'];
    header('Location: ' . url('perfil.php'));
    exit;
}

if (strlen($passNueva) < 16) {
    $_SESSION['flash_pass'] = ['ok'=>false,'msg'=>'La nueva contraseña debe tener al menos 8 caracteres.'];
    header('Location: ' . url('perfil.php'));
    exit;
}

// ── Verificar contraseña actual ───────────────────────────
$stmt = $pdo->prepare("SELECT password_hash FROM usuarios_registrados WHERE id_usuario = :id");
$stmt->execute([':id' => $usuarioId]);
$usuario = $stmt->fetch();

if (!$usuario || !password_verify($passActual, $usuario['password_hash'])) {
    $_SESSION['flash_pass'] = ['ok'=>false,'msg'=>'La contraseña actual es incorrecta.'];
    header('Location: ' . url('perfil.php'));
    exit;
}

// ── Verificar que la nueva no sea igual a la actual ───────
if (password_verify($passNueva, $usuario['password_hash'])) {
    $_SESSION['flash_pass'] = ['ok'=>false,'msg'=>'La nueva contraseña debe ser diferente a la actual.'];
    header('Location: ' . url('perfil.php'));
    exit;
}

// ── Actualizar contraseña ─────────────────────────────────
$nuevoHash = password_hash($passNueva, PASSWORD_BCRYPT, ['cost'=>12]);
$pdo->prepare("UPDATE usuarios_registrados SET password_hash=:hash WHERE id_usuario=:id")
    ->execute([':hash'=>$nuevoHash, ':id'=>$usuarioId]);

// Actualizar también en cuenta_web si existe
try {
    $pdo->prepare("
        UPDATE cuenta_web SET password_hash=:hash
        WHERE id_agremiado=(SELECT id_afiliado FROM usuarios_registrados WHERE id_usuario=:id)
    ")->execute([':hash'=>$nuevoHash, ':id'=>$usuarioId]);
} catch (Exception $e) { /* silencioso si no existe */ }

// Registrar en log
registrarLog('cambio_password', 'El usuario cambió su contraseña');

unset($_SESSION['csrf_token']);
$_SESSION['flash_pass'] = ['ok'=>true,'msg'=>'✅ Contraseña actualizada correctamente.'];
header('Location: ' . url('perfil.php'));
exit;
