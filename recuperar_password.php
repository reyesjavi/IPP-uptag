<?php
// recuperar_password.php — Solicitar recuperación de contraseña
require_once __DIR__ . '/config/base.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';   // verificarRateLimitIP()
require_once __DIR__ . '/lib/phpmailer/Exception.php';
require_once __DIR__ . '/lib/phpmailer/PHPMailer.php';
require_once __DIR__ . '/lib/phpmailer/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

if (session_status() === PHP_SESSION_NONE) session_start();

$paso    = $_GET['paso'] ?? 'solicitar';
$token   = $_GET['token'] ?? '';
$mensaje = '';
$error   = '';

// ── PASO 1: Procesar solicitud de recuperación ──
if ($paso === 'solicitar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $ci = trim(strtoupper($_POST['ci'] ?? ''));

    if (empty($ci)) {
        $error = 'Ingresa tu cédula de identidad.';
    } elseif (!verificarRateLimitIP('recuperacion_intento', 5, 60)) {
        // Limitar el abuso del endpoint (spam de correos / sondeo)
        $error = 'Demasiadas solicitudes desde esta red. Espera un momento e inténtalo de nuevo.';
    } else {
        $pdo  = getDB();
        $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
        // Registrar el intento para que el rate-limit por IP lo contabilice
        registrarLog_simple($pdo, 'recuperacion_intento', "CI: $ci", null, $ip);

        $stmt = $pdo->prepare("
            SELECT u.id_usuario, u.username, a.correo, a.nombre
            FROM usuarios_registrados u
            LEFT JOIN afiliado a ON a.id_afiliado = u.id_afiliado
            WHERE u.username = :ci AND u.activo = 1
            LIMIT 1
        ");
        $stmt->execute([':ci' => $ci]);
        $usuario = $stmt->fetch();

        // Siempre mostrar el mismo mensaje (no revelar si el usuario existe)
        $mensaje = 'Si tu cédula está registrada y tiene correo asociado, recibirás las instrucciones en breve.';

        if ($usuario && !empty($usuario['correo'])) {
            // Invalidar tokens anteriores
            $pdo->prepare("UPDATE recuperacion_password SET usado=1 WHERE id_usuario=:id AND usado=0")
                ->execute([':id' => $usuario['id_usuario']]);

            // Generar token seguro. En BD se guarda su hash sha256; el enlace
            // del correo lleva el token en claro.
            $token     = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expira    = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $pdo->prepare("
                INSERT INTO recuperacion_password (id_usuario, token, expira_en, ip_solicitud)
                VALUES (:id, :token, :expira, :ip)
            ")->execute([':id'=>$usuario['id_usuario'], ':token'=>$tokenHash, ':expira'=>$expira, ':ip'=>$ip]);

            // Construir enlace
            $enlace = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://'
                    . $_SERVER['HTTP_HOST']
                    . url('recuperar_password.php') . '?paso=restablecer&token=' . $token;

            // Enviar email con PHPMailer
            enviarRecuperacion($usuario['correo'], $usuario['nombre'], $usuario['username'], $enlace);

            registrarLog_simple($pdo, 'recuperacion_solicitada', "CI: $ci", $usuario['id_usuario'], $ip);
        }
    }
}

// ── PASO 2: Validar token y mostrar form de nueva contraseña ──
$tokenValido = false;
$usuarioToken = null;
if ($paso === 'restablecer' && $token) {
    $pdo  = getDB();
    $stmt = $pdo->prepare("
        SELECT r.*, u.username
        FROM recuperacion_password r
        JOIN usuarios_registrados u ON u.id_usuario = r.id_usuario
        WHERE r.token = :token AND r.usado = 0 AND r.expira_en > NOW()
        LIMIT 1
    ");
    $stmt->execute([':token' => hash('sha256', $token)]);
    $usuarioToken = $stmt->fetch();
    $tokenValido  = (bool)$usuarioToken;
    if (!$tokenValido) $error = 'El enlace es inválido o ya expiró. Solicita uno nuevo.';
}

// ── PASO 3: Procesar nueva contraseña ──
if ($paso === 'restablecer' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $tokenPost    = $_POST['token'] ?? '';
    $nueva        = $_POST['nueva_password'] ?? '';
    $confirmar    = $_POST['confirmar_password'] ?? '';

    $pdo  = getDB();
    $stmt = $pdo->prepare("
        SELECT r.*, u.username
        FROM recuperacion_password r
        JOIN usuarios_registrados u ON u.id_usuario = r.id_usuario
        WHERE r.token = :token AND r.usado = 0 AND r.expira_en > NOW()
        LIMIT 1
    ");
    $stmt->execute([':token' => hash('sha256', $tokenPost)]);
    $reg = $stmt->fetch();

    if (!$reg) {
        $error = 'El enlace es inválido o ya expiró.';
    } elseif (strlen($nueva) < 16) {
        $error = 'La contraseña debe tener al menos 16 caracteres.';
        $tokenValido = true; $usuarioToken = $reg;
    } elseif ($nueva !== $confirmar) {
        $error = 'Las contraseñas no coinciden.';
        $tokenValido = true; $usuarioToken = $reg;
    } else {
        $hash = password_hash($nueva, PASSWORD_BCRYPT, ['cost'=>12]);
        $pdo->prepare("UPDATE usuarios_registrados SET password_hash=:h, intentos_fallidos=0, bloqueado=0, bloqueado_hasta=NULL WHERE id_usuario=:id")
            ->execute([':h'=>$hash, ':id'=>$reg['id_usuario']]);
        $pdo->prepare("UPDATE recuperacion_password SET usado=1 WHERE id_recuperacion=:id")
            ->execute([':id'=>$reg['id_recuperacion']]);
        registrarLog_simple($pdo, 'password_restablecida', "Usuario: {$reg['username']}", $reg['id_usuario'], $_SERVER['REMOTE_ADDR']??'');
        $mensaje = '¡Contraseña actualizada correctamente! Ya puedes iniciar sesión.';
        $paso    = 'listo';
    }
}

function registrarLog_simple(PDO $pdo, string $accion, string $detalle, ?int $uid, string $ip): void {
    try {
        $pdo->prepare("INSERT INTO log_actividad (id_usuario,accion,detalle,ip) VALUES (:u,:a,:d,:i)")
            ->execute([':u'=>$uid,':a'=>$accion,':d'=>$detalle,':i'=>$ip]);
    } catch (Exception $e) {}
}

function enviarRecuperacion(string $destino, string $nombre, string $usuario, string $enlace): void {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = getenv('MAIL_HOST') ?: 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = getenv('MAIL_USER') ?: '';
        $mail->Password   = getenv('MAIL_PASS') ?: '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)(getenv('MAIL_PORT') ?: 587);
        $mail->CharSet    = 'UTF-8';

        $fromName = getenv('MAIL_FROM_NAME') ?: 'IPP UPTAG';
        $fromAddr = getenv('MAIL_USER') ?: 'no-reply@uptag.edu.ve';
        $mail->setFrom($fromAddr, $fromName);
        $mail->addAddress($destino);

        $mail->Subject = 'Recuperación de contraseña — IPP - UPTAG';
        $mail->Body    = "Hola $nombre,\n\n"
                       . "Recibimos una solicitud para restablecer la contraseña de tu cuenta ($usuario).\n\n"
                       . "Haz clic en el siguiente enlace para establecer una nueva contraseña:\n"
                       . "$enlace\n\n"
                       . "Este enlace expirará en 1 hora.\n\n"
                       . "Si no solicitaste este cambio, ignora este mensaje.\n\n"
                       . "— Sistema IPP-UPTAG";

        $mail->send();
    } catch (\Exception $e) {
        error_log('[UPTAG Mail] No se pudo enviar correo de recuperación a ' . $destino . ': ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Recuperar Contraseña — UPTAG</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Nunito:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.14.0/tabler-icons.min.css"/>
  <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>"/>
  <style>
    body { background:var(--bg); display:flex; align-items:center; justify-content:center; min-height:100vh; }
    .rec-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:2.5rem 2rem; width:420px; max-width:95vw; box-shadow:var(--shadow-md); }
    .rec-card h2 { font-family:'Syne',sans-serif; font-size:20px; font-weight:700; color:var(--text); margin-bottom:.5rem; }
    .rec-card p  { font-size:13px; color:var(--text-3); margin-bottom:1.5rem; line-height:1.6; }
  </style>
</head>
<body>
<div class="rec-card">
  <div style="text-align:center;margin-bottom:1.5rem">
    <div class="nav-logo" style="margin:0 auto 1rem;width:46px;height:46px">UP</div>
    <h2>Recuperar contraseña</h2>
  </div>

  <?php if ($error): ?>
    <div class="flash-msg flash-err"><i class="ti ti-alert-circle"></i> <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($mensaje): ?>
    <div class="flash-msg flash-ok"><i class="ti ti-check"></i> <?= htmlspecialchars($mensaje) ?></div>
  <?php endif; ?>

  <?php if ($paso === 'listo'): ?>
    <div style="text-align:center;margin-top:1rem">
      <a href="<?= url('login.php') ?>" class="btn btn-teal" style="width:100%;justify-content:center">
        <i class="ti ti-login"></i> Ir al inicio de sesión
      </a>
    </div>

  <?php elseif ($paso === 'restablecer' && $tokenValido): ?>
    <p>Ingresa tu nueva contraseña para <strong><?= htmlspecialchars($usuarioToken['username']) ?></strong>.</p>
    <form method="POST">
      <?= campoCsrf() ?>
      <input type="hidden" name="token" value="<?= htmlspecialchars($token ?: ($_POST['token'] ?? '')) ?>">
      <div class="form-group">
        <label>Nueva contraseña <small style="color:var(--text-3)">(mín. 16 caracteres)</small></label>
        <input type="password" name="nueva_password" minlength="16" required placeholder="Mínimo 16 caracteres"/>
      </div>
      <div class="form-group">
        <label>Confirmar contraseña</label>
        <input type="password" name="confirmar_password" minlength="16" required placeholder="Repite la contraseña"/>
      </div>
      <button type="submit" class="btn btn-teal" style="width:100%;justify-content:center">
        <i class="ti ti-lock-check"></i> Establecer nueva contraseña
      </button>
    </form>

  <?php elseif ($paso === 'solicitar' && !$mensaje): ?>
    <p>Ingresa tu cédula de identidad. Si tienes un correo registrado, recibirás un enlace para restablecer tu contraseña.</p>
    <form method="POST">
      <?= campoCsrf() ?>
      <div class="form-group">
        <label>Cédula de identidad</label>
        <input type="text" name="ci" placeholder="V-12345678" required style="text-transform:uppercase"/>
      </div>
      <button type="submit" class="btn btn-teal" style="width:100%;justify-content:center">
        <i class="ti ti-mail"></i> Enviar enlace de recuperación
      </button>
    </form>
  <?php endif; ?>

  <div style="text-align:center;margin-top:1.5rem">
    <a href="<?= url('login.php') ?>" class="btn-link" style="font-size:13px;color:var(--text-3)">
      <i class="ti ti-arrow-left"></i> Volver al inicio de sesión
    </a>
  </div>
</div>
<script src="<?= url('assets/js/app.js') ?>"></script>
</body>
</html>
