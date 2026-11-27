<?php
require_once __DIR__ . '/php/layout.php';
require_once __DIR__ . '/php/usuarios.php';

exigir_login();
$uid = (int)usuario_actual()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'actualizar_perfil') {
        $res = actualizar_perfil_propio($pdo, $uid, $_POST, $_FILES['foto'] ?? null);
        flash_set($res['ok'] ? 'ok' : 'error', $res['msg']);
    } elseif ($accion === 'cambiar_clave') {
        $res = cambiar_clave_propia(
            $pdo,
            $uid,
            $_POST['clave_actual'] ?? '',
            $_POST['clave_nueva'] ?? '',
            $_POST['clave_conf'] ?? ''
        );
        flash_set($res['ok'] ? 'ok' : 'error', $res['msg']);
    }
    header('Location: perfil.php');
    exit;
}

$u = obtener_usuario_por_id($pdo, $uid);
if (!$u) {
    header('Location: logout.php');
    exit;
}
$inicial = strtoupper(mb_substr($u['nombres'] ?? 'U', 0, 1));
$tieneFoto = !empty($u['foto']);

render_head('Mi Perfil — SIBI');
render_topbar('perfil');
?>
<main class="container">
  <div class="section-bar">
    <div>
      <h1 style="font-size:28px;">Mi Perfil</h1>
      <p class="text-muted">Actualiza tu foto de perfil, tus datos personales y tu contraseña de acceso.</p>
    </div>
    <a href="<?= $u['rol'] === 'administrador' ? 'panel.php' : 'catalogo.php' ?>" class="btn btn-outline btn-sm">Volver</a>
  </div>

  <div class="grid-2">
    <div class="card">
      <h3>Datos personales y foto</h3>
      <p class="card-sub">Haz clic en el ícono de cámara sobre tu foto para elegir una imagen (se ajusta automáticamente a proporción 1:1).</p>

      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="accion" value="actualizar_perfil">
        <input type="hidden" name="quitar_foto" id="quitar-foto-flag" value="">

        <div class="profile-avatar-block">
          <label for="perfil-foto-input" class="profile-avatar-box" title="Cambiar foto de perfil">
            <img id="perfil-avatar-img"
                 src="<?= $tieneFoto ? h($u['foto']) : '' ?>"
                 alt="Foto de perfil"
                 class="profile-avatar-img"
                 style="<?= $tieneFoto ? '' : 'display:none;' ?>">
            <span id="perfil-avatar-initial"
                  class="profile-avatar-initial"
                  style="<?= $tieneFoto ? 'display:none;' : '' ?>"><?= h($inicial) ?></span>
            <span class="profile-avatar-edit-btn" aria-label="Editar imagen">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                <circle cx="12" cy="13" r="4"/>
              </svg>
            </span>
          </label>
          <input type="file"
                 id="perfil-foto-input"
                 name="foto"
                 accept="image/jpeg,image/png,image/webp"
                 data-avatar-input="perfil-avatar-img"
                 data-avatar-initial="perfil-avatar-initial"
                 class="sr-only">
          <div class="profile-avatar-meta">
            <strong><?= h($u['nombres'] . ' ' . $u['apellidos']) ?></strong>
            <div style="margin:4px 0 8px;">
              <span class="badge badge-blue"><?= h(etiqueta_rol($u['rol'])) ?></span>
              <span class="text-muted" style="margin-left:6px;">DNI: <?= h($u['dni']) ?></span>
            </div>
            <div class="actions-cell">
              <label for="perfil-foto-input" class="btn btn-outline btn-sm" style="cursor:pointer;">Cambiar foto</label>
              <?php if ($tieneFoto): ?>
                <button type="button" class="btn btn-danger btn-sm" id="btn-quitar-foto">Quitar foto</button>
              <?php endif; ?>
            </div>
            <small class="text-muted" style="display:block;margin-top:6px;">Formatos JPG, PNG o WebP (máx. 2 MB · ajuste proporcional 1:1).</small>
          </div>
        </div>

        <div class="grid-2">
          <div class="field">
            <label for="pf-nombres">Nombres *</label>
            <input class="input" id="pf-nombres" name="nombres" required value="<?= h($u['nombres']) ?>">
          </div>
          <div class="field">
            <label for="pf-apellidos">Apellidos *</label>
            <input class="input" id="pf-apellidos" name="apellidos" required value="<?= h($u['apellidos']) ?>">
          </div>
        </div>

        <div class="grid-2">
          <div class="field">
            <label for="pf-correo">Correo electrónico *</label>
            <input class="input" type="email" id="pf-correo" name="correo" required value="<?= h($u['correo']) ?>">
          </div>
          <div class="field">
            <label for="pf-telefono">Teléfono</label>
            <input class="input" id="pf-telefono" name="telefono" maxlength="20" value="<?= h($u['telefono'] ?? '') ?>">
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Guardar cambios de perfil</button>
      </form>
    </div>

    <div class="card">
      <h3>Seguridad y contraseña</h3>
      <p class="card-sub">Por seguridad, ingresa tu contraseña actual para confirmar el cambio.</p>

      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="accion" value="cambiar_clave">

        <div class="field">
          <label for="pf-clave-actual">Contraseña actual *</label>
          <div class="pass-wrap">
            <input class="input" type="password" id="pf-clave-actual" name="clave_actual" required placeholder="Ingresa tu clave actual">
            <button type="button" class="pass-toggle" data-toggle-pass="pf-clave-actual">Ver</button>
          </div>
        </div>

        <div class="field">
          <label for="pf-clave-nueva">Nueva contraseña *</label>
          <div class="pass-wrap">
            <input class="input" type="password" id="pf-clave-nueva" name="clave_nueva" required minlength="6" placeholder="Mínimo 6 caracteres">
            <button type="button" class="pass-toggle" data-toggle-pass="pf-clave-nueva">Ver</button>
          </div>
        </div>

        <div class="field">
          <label for="pf-clave-conf">Confirmar nueva contraseña *</label>
          <div class="pass-wrap">
            <input class="input" type="password" id="pf-clave-conf" name="clave_conf" required minlength="6" placeholder="Repite la nueva contraseña">
            <button type="button" class="pass-toggle" data-toggle-pass="pf-clave-conf">Ver</button>
          </div>
        </div>

        <button type="submit" class="btn btn-navy btn-block">Actualizar contraseña</button>
      </form>
    </div>
  </div>
</main>
<?php render_footer(); ?>
