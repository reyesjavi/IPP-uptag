<?php
$pageTitle   = 'Usuarios';
$pageSubtitle= 'Gestión de acceso al portal';
$activeAdmin = 'usuarios';
require_once __DIR__ . '/../config/base.php';
require_once __DIR__ . '/../includes/auth.php';
requiereRol('admin');
$pdo   = getDB();

// ── Crear usuario ──
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['accion']??'')==='crear') {
    verificarCsrf();
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $rol      = $_POST['rol'] ?? 'afiliado';
    $afilId   = intval($_POST['id_afiliado'] ?? 0) ?: null;

    if ($username && $password && in_array($rol,['admin','administrativo','afiliado'])) {
        if (strlen($password) < 16) {
            $_SESSION['flash_admin'] = ['ok'=>false,'msg'=>'La contraseña debe tener al menos 16 caracteres.'];
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost'=>12]);
            try {
                $pdo->prepare("
                    INSERT INTO usuarios_registrados (username, password_hash, rol, activo, id_afiliado)
                    VALUES (:u, :h, :r, 1, :a)
                ")->execute([':u'=>$username,':h'=>$hash,':r'=>$rol,':a'=>$afilId]);
                registrarLog('usuario_creado', "Usuario '$username' creado con rol $rol");
                $_SESSION['flash_admin'] = ['ok'=>true,'msg'=>"Usuario '$username' creado correctamente."];
            } catch (PDOException $e) {
                $_SESSION['flash_admin'] = ['ok'=>false,'msg'=>'Error: ese usuario ya existe.'];
            }
        }
    } else {
        $_SESSION['flash_admin'] = ['ok'=>false,'msg'=>'Completa todos los campos correctamente.'];
    }
    header('Location: '.url('admin/usuarios.php')); exit;
}

// ── Cambiar estado (activar/desactivar) ──
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['accion']??'')==='toggle') {
    verificarCsrf();
    $id     = intval($_POST['id'] ?? 0);
    $activo = intval($_POST['activo'] ?? 0);
    if ($id && $id !== intval($_SESSION['usuario_id'])) {
        $pdo->prepare("UPDATE usuarios_registrados SET activo=:a WHERE id_usuario=:id")
            ->execute([':a'=>$activo,':id'=>$id]);
        registrarLog('usuario_'.($activo?'activado':'desactivado'),"Usuario #$id");
        $_SESSION['flash_admin'] = ['ok'=>true,'msg'=>'Usuario '.($activo?'activado':'desactivado').'.'];
    }
    header('Location: '.url('admin/usuarios.php')); exit;
}

// ── Eliminar usuario — requiere contraseña del admin ──
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['accion']??'')==='eliminar') {
    verificarCsrf();
    $id            = intval($_POST['id'] ?? 0);
    $passAdmin     = $_POST['pass_admin'] ?? '';

    if (!$id || $id === intval($_SESSION['usuario_id'])) {
        $_SESSION['flash_admin'] = ['ok'=>false,'msg'=>'No puedes eliminar tu propia cuenta.'];
        header('Location: '.url('admin/usuarios.php')); exit;
    }

    // Verificar contraseña del administrador actual
    $stmtAdmin = $pdo->prepare("SELECT password_hash FROM usuarios_registrados WHERE id_usuario=:id");
    $stmtAdmin->execute([':id' => $_SESSION['usuario_id']]);
    $adminData = $stmtAdmin->fetch();

    if (!$adminData || !password_verify($passAdmin, $adminData['password_hash'])) {
        $_SESSION['flash_admin'] = ['ok'=>false,'msg'=>'Contraseña incorrecta. No se eliminó el usuario.'];
        header('Location: '.url('admin/usuarios.php')); exit;
    }

    // Obtener username para el log antes de eliminar
    $stmtUser = $pdo->prepare("SELECT username FROM usuarios_registrados WHERE id_usuario=:id");
    $stmtUser->execute([':id'=>$id]);
    $userElim = $stmtUser->fetchColumn();

    $pdo->prepare("DELETE FROM usuarios_registrados WHERE id_usuario=:id")->execute([':id'=>$id]);
    registrarLog('usuario_eliminado',"Usuario '$userElim' (#$id) eliminado por admin");
    $_SESSION['flash_admin'] = ['ok'=>true,'msg'=>"Usuario '$userElim' eliminado correctamente."];
    header('Location: '.url('admin/usuarios.php')); exit;
}

$usuarios  = $pdo->query("
    SELECT u.*, a.nombre, a.apellido
    FROM usuarios_registrados u
    LEFT JOIN afiliado a ON a.id_afiliado = u.id_afiliado
    ORDER BY u.id_usuario DESC
")->fetchAll();

$afiliados = $pdo->query("SELECT id_afiliado, nombre, apellido, ci FROM afiliado ORDER BY nombre")->fetchAll();

require_once __DIR__ . '/header.php';
?>


<div style="display:grid;grid-template-columns:1.8fr 1fr;gap:1.2rem">

  <!-- Lista de usuarios -->
  <div class="sc">
    <h3>Usuarios registrados (<?= count($usuarios) ?>)</h3>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead><tr><th>Usuario</th><th>Nombre</th><th>Rol</th><th>Último acceso</th><th>Estado</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($usuarios as $u):
          $rolBadge = match($u['rol']) { 'admin'=>'badge-amber','administrativo'=>'badge-blue', default=>'badge-green' };
          $esYo     = $u['id_usuario'] == $_SESSION['usuario_id'];
        ?>
        <tr>
          <td style="font-weight:500"><?= htmlspecialchars($u['username']) ?></td>
          <td><?= $u['nombre'] ? htmlspecialchars($u['nombre'].' '.$u['apellido']) : '<span style="color:var(--text-3)">—</span>' ?></td>
          <td><span class="badge <?= $rolBadge ?>"><?= ucfirst($u['rol']) ?></span></td>
          <td><?= $u['ultimo_acceso'] ? date('d/m/Y H:i',strtotime($u['ultimo_acceso'])) : '—' ?></td>
          <td><span class="badge <?= $u['activo']?'badge-green':'badge-red' ?>"><?= $u['activo']?'Activo':'Inactivo' ?></span></td>
          <td>
            <?php if (!$esYo): ?>
            <form method="POST" style="display:inline">
              <?= campoCsrf() ?>
              <input type="hidden" name="accion" value="toggle">
              <input type="hidden" name="id" value="<?= $u['id_usuario'] ?>">
              <input type="hidden" name="activo" value="<?= $u['activo']?0:1 ?>">
              <button type="submit" class="btn-xs <?= $u['activo']?'btn-reject':'btn-approve' ?>">
                <?= $u['activo']?'Desactivar':'Activar' ?>
              </button>
            </form>
            <button class="btn-xs btn-delete" onclick="abrirEliminar(<?= $u['id_usuario'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')">
              <i class="ti ti-trash"></i>
            </button>
            <?php else: ?>
              <span style="font-size:12px;color:var(--text-3)">Tu cuenta</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Crear nuevo usuario -->
  <div class="sc">
    <h3><i class="ti ti-user-plus" style="color:var(--primary)"></i> Crear nuevo usuario</h3>
    <form method="POST" action="<?= url('admin/usuarios.php') ?>">
      <?= campoCsrf() ?>
      <input type="hidden" name="accion" value="crear">
      <div class="fl" style="margin-bottom:.9rem">
        <label>Usuario (cédula o correo) *</label>
        <input type="text" name="username" placeholder="V-12345678" required />
      </div>
      <div class="fl" style="margin-bottom:.9rem">
        <label>Contraseña * <small style="color:var(--text-3)">(mín. 16 caracteres)</small></label>
        <input type="password" name="password" placeholder="Mínimo 16 caracteres" required minlength="16" />
      </div>
      <div class="fl" style="margin-bottom:.9rem">
        <label>Rol *</label>
        <select name="rol" required>
          <option value="afiliado">Afiliado / Docente</option>
          <option value="administrativo">Personal Administrativo</option>
          <option value="admin">Administrador</option>
        </select>
      </div>
      <div class="fl" style="margin-bottom:1.2rem">
        <label>Vincular a afiliado (opcional)</label>
        <select name="id_afiliado">
          <option value="">— Sin vincular —</option>
          <?php foreach ($afiliados as $a): ?>
          <option value="<?= $a['id_afiliado'] ?>">
            <?= htmlspecialchars($a['nombre'].' '.$a['apellido'].' ('.$a['ci'].')') ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-teal" style="width:100%">
        <i class="ti ti-user-plus"></i> Crear usuario
      </button>
    </form>
  </div>
</div>

<!-- Modal de eliminación con autenticación -->
<div class="modal-bg" id="modalEliminar">
  <div class="modal">
    <button class="modal-close" onclick="cerrarEliminar()"><i class="ti ti-x"></i></button>
    <h3 style="color:var(--red)"><i class="ti ti-alert-triangle"></i> Eliminar usuario</h3>
    <p style="font-size:13px;color:var(--text-2);margin-bottom:1.2rem">
      Vas a eliminar al usuario <strong id="eliminar-nombre"></strong>.<br>
      Esta acción es <strong>irreversible</strong>. Ingresa tu contraseña de administrador para confirmar.
    </p>
    <form method="POST" action="<?= url('admin/usuarios.php') ?>">
      <?= campoCsrf() ?>
      <input type="hidden" name="accion" value="eliminar">
      <input type="hidden" name="id" id="eliminar-id">
      <div class="fl" style="margin-bottom:1.2rem">
        <label>Tu contraseña de administrador *</label>
        <input type="password" name="pass_admin" placeholder="Ingresa tu contraseña" required minlength="1"
               style="width:100%;padding:9px 12px;border:1.5px solid var(--red);border-radius:8px;font-family:'Nunito',sans-serif;font-size:13px"/>
      </div>
      <div class="btn-row">
        <button type="submit" class="btn btn-teal" style="background:var(--red)">
          <i class="ti ti-trash"></i> Confirmar eliminación
        </button>
        <button type="button" class="btn btn-outline" onclick="cerrarEliminar()">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<script>
function abrirEliminar(id, nombre) {
  document.getElementById('eliminar-id').value    = id;
  document.getElementById('eliminar-nombre').textContent = nombre;
  document.getElementById('modalEliminar').classList.add('open');
}
function cerrarEliminar() {
  document.getElementById('modalEliminar').classList.remove('open');
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
