<?php
require_once __DIR__ . '/php/layout.php';
require_once __DIR__ . '/php/usuarios.php';
require_once __DIR__ . '/php/prestamos.php';

exigir_rol(['administrador', 'bibliotecario']);
$esAdmin = usuario_actual()['rol'] === 'administrador';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'crear_usuario') {
        $res = crear_usuario($pdo, $_POST, $_FILES['foto'] ?? null);
        flash_set($res['ok'] ? 'ok' : 'error', $res['msg']);
    } elseif ($accion === 'editar_usuario') {
        $editId = (int)($_POST['usuario_id'] ?? 0);
        $res = actualizar_usuario_admin($pdo, $editId, $_POST, $_FILES['foto'] ?? null);
        flash_set($res['ok'] ? 'ok' : 'error', $res['msg']);
        if (!$res['ok']) {
            header('Location: lectores.php?editar=' . $editId);
            exit;
        }
    } elseif ($accion === 'toggle_estado' && $esAdmin) {
        $res = cambiar_estado_usuario($pdo, (int)($_POST['usuario_id'] ?? 0));
        flash_set($res['ok'] ? 'ok' : 'error', $res['msg']);
    } elseif ($accion === 'eliminar_usuario' && $esAdmin) {
        $res = eliminar_usuario($pdo, (int)($_POST['usuario_id'] ?? 0));
        flash_set($res['ok'] ? 'ok' : 'error', $res['msg']);
    }
    header('Location: lectores.php');
    exit;
}

$q = trim($_GET['q'] ?? '');
$usuarios = listar_usuarios($pdo, $q);
$verHistorial = (int)($_GET['historial'] ?? 0);
$historial = $verHistorial ? listar_historial_lector($pdo, $verHistorial) : [];

$editarId = (int)($_GET['editar'] ?? 0);
$usuarioEditar = null;
if ($editarId > 0) {
    $cand = obtener_usuario_por_id($pdo, $editarId);
    if ($cand && ($esAdmin || $cand['rol'] === 'lector')) {
        $usuarioEditar = $cand;
    }
}

render_head('Lectores y Usuarios — SIBI');
render_topbar('lectores');
?>
<main class="container">
  <div class="section-bar">
    <h1 style="font-size:28px;">Lectores y Personal</h1>
    <form method="get" style="display:flex;gap:8px;">
      <input class="input" name="q" value="<?= h($q) ?>" placeholder="Buscar por nombre o DNI..." style="width:240px;">
      <button class="btn btn-navy btn-sm" type="submit">Filtrar</button>
    </form>
  </div>

  <div class="grid-admin-2">
    <div class="card">
      <?php if ($usuarioEditar): ?>
        <?php
          $edInit = strtoupper(mb_substr($usuarioEditar['nombres'] ?? 'U', 0, 1));
          $edTieneFoto = !empty($usuarioEditar['foto']);
          $edEsPropio = (int)$usuarioEditar['id'] === (int)usuario_actual()['id'];
        ?>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:6px;">
          <h3 style="margin-bottom:0;">Editar usuario</h3>
          <a href="lectores.php" class="btn btn-outline btn-sm">Cancelar</a>
        </div>
        <p class="card-sub">Editando a <strong><?= h($usuarioEditar['nombres'] . ' ' . $usuarioEditar['apellidos']) ?></strong>.</p>

        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="accion" value="editar_usuario">
          <input type="hidden" name="usuario_id" value="<?= (int)$usuarioEditar['id'] ?>">
          <input type="hidden" name="quitar_foto" id="quitar-foto-flag" value="">

          <div class="field">
            <label>Foto de perfil (ajuste 1:1)</label>
            <div class="cover-upload-box">
              <label for="edit-foto-input" class="reg-avatar-preview-wrap" title="Cambiar foto">
                <img id="edit-avatar-img"
                     src="<?= $edTieneFoto ? h($usuarioEditar['foto']) : '' ?>"
                     alt="Foto actual"
                     class="reg-avatar-preview-img"
                     style="<?= $edTieneFoto ? '' : 'display:none;' ?>">
                <span id="edit-avatar-initial"
                      class="reg-avatar-preview-initial"
                      style="<?= $edTieneFoto ? 'display:none;' : '' ?>"><?= h($edInit) ?></span>
              </label>
              <div class="cover-upload-info">
                <input type="file" id="edit-foto-input" name="foto" accept="image/jpeg,image/png,image/webp" data-avatar-input="edit-avatar-img" data-avatar-initial="edit-avatar-initial">
                <?php if ($edTieneFoto): ?>
                  <button type="button" class="btn btn-danger btn-sm" id="btn-quitar-foto" data-clear-img="edit-avatar-img" data-clear-init="edit-avatar-initial" data-clear-input="edit-foto-input" style="margin-top:6px;">Quitar foto</button>
                <?php else: ?>
                  <small>JPG, PNG o WebP · máx. 2 MB</small>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="field"><label>Nombres *</label><input class="input" name="nombres" required value="<?= h($usuarioEditar['nombres']) ?>"></div>
          <div class="field"><label>Apellidos *</label><input class="input" name="apellidos" required value="<?= h($usuarioEditar['apellidos']) ?>"></div>
          <div class="grid-2">
            <div class="field"><label>DNI *</label><input class="input" name="dni" required maxlength="15" value="<?= h($usuarioEditar['dni']) ?>"></div>
            <div class="field"><label>Teléfono</label><input class="input" name="telefono" maxlength="20" value="<?= h($usuarioEditar['telefono'] ?? '') ?>"></div>
          </div>
          <div class="field"><label>Correo electrónico *</label><input class="input" type="email" name="correo" required value="<?= h($usuarioEditar['correo']) ?>"></div>

          <?php if ($esAdmin && !$edEsPropio): ?>
          <div class="field">
            <label>Rol</label>
            <select class="input" name="rol">
              <option value="lector" <?= $usuarioEditar['rol'] === 'lector' ? 'selected' : '' ?>>Lector (Estudiante)</option>
              <option value="bibliotecario" <?= $usuarioEditar['rol'] === 'bibliotecario' ? 'selected' : '' ?>>Bibliotecario</option>
              <option value="administrador" <?= $usuarioEditar['rol'] === 'administrador' ? 'selected' : '' ?>>Administrador</option>
            </select>
          </div>
          <?php endif; ?>

          <?php if ($esAdmin): ?>
          <div class="reset-pass-box">
            <div class="field" style="margin-bottom:0;">
              <label for="edit-password">Restablecer contraseña (opcional)</label>
              <div class="pass-wrap">
                <input class="input" type="password" id="edit-password" name="password" minlength="6" placeholder="Dejar vacío para mantener la actual">
                <button type="button" class="pass-toggle" data-toggle-pass="edit-password">Ver</button>
              </div>
              <small class="text-muted" style="display:block;margin-top:5px;">Si el usuario perdió su clave, escribe una nueva aquí (mín. 6 caracteres) y guarda.</small>
            </div>
          </div>
          <?php endif; ?>

          <button type="submit" class="btn btn-primary btn-block">Guardar cambios</button>
        </form>
      <?php else: ?>
        <h3>Registrar <?= $esAdmin ? 'usuario' : 'lector' ?></h3>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="accion" value="crear_usuario">

          <div class="field">
            <label>Foto de perfil (opcional)</label>
            <div class="cover-upload-box">
              <label for="reg-foto-input" class="reg-avatar-preview-wrap" title="Elegir imagen">
                <img id="reg-avatar-img" src="" alt="Vista previa" class="reg-avatar-preview-img" style="display:none;">
                <span id="reg-avatar-initial" class="reg-avatar-preview-initial">+</span>
              </label>
              <div class="cover-upload-info">
                <input type="file" id="reg-foto-input" name="foto" accept="image/jpeg,image/png,image/webp" data-avatar-input="reg-avatar-img" data-avatar-initial="reg-avatar-initial">
                <small>JPG, PNG o WebP · máx. 2 MB (ajuste 1:1)</small>
              </div>
            </div>
          </div>

          <div class="field"><label>Nombres *</label><input class="input" name="nombres" required></div>
          <div class="field"><label>Apellidos *</label><input class="input" name="apellidos" required></div>
          <div class="grid-2">
            <div class="field"><label>DNI *</label><input class="input" name="dni" required maxlength="15"></div>
            <div class="field"><label>Teléfono</label><input class="input" name="telefono"></div>
          </div>
          <div class="field"><label>Correo electrónico *</label><input class="input" type="email" name="correo" required></div>
          <div class="field">
            <label for="reg-password">Contraseña inicial *</label>
            <div class="pass-wrap">
              <input class="input" type="password" id="reg-password" name="password" required minlength="6" value="123456">
              <button type="button" class="pass-toggle" data-toggle-pass="reg-password">Ver</button>
            </div>
          </div>
          <?php if ($esAdmin): ?>
          <div class="field">
            <label>Rol</label>
            <select class="input" name="rol">
              <option value="lector">Lector (Estudiante)</option>
              <option value="bibliotecario">Bibliotecario</option>
              <option value="administrador">Administrador</option>
            </select>
          </div>
          <?php endif; ?>
          <button type="submit" class="btn btn-primary btn-block">Registrar</button>
        </form>
      <?php endif; ?>
    </div>

    <div class="card">
      <h3>Directorio (<?= count($usuarios) ?>)</h3>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Usuario</th><th>DNI</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr></thead>
          <tbody>
            <?php foreach ($usuarios as $us): ?>
            <?php
              $usInit = strtoupper(mb_substr($us['nombres'] ?? 'U', 0, 1));
              $puedeEditar = $esAdmin || $us['rol'] === 'lector';
              $enEdicion = $usuarioEditar && (int)$usuarioEditar['id'] === (int)$us['id'];
            ?>
            <tr<?= $enEdicion ? ' style="background:#EFF6FF;"' : '' ?>>
              <td>
                <div class="book-cell">
                  <span class="user-avatar user-avatar-sm">
                    <?php if (!empty($us['foto'])): ?>
                      <img src="<?= h($us['foto']) ?>" alt="<?= h($us['nombres']) ?>" class="user-avatar-img">
                    <?php else: ?>
                      <?= h($usInit) ?>
                    <?php endif; ?>
                  </span>
                  <div>
                    <strong><?= h($us['nombres'] . ' ' . $us['apellidos']) ?></strong>
                    <div class="text-muted"><?= h($us['correo']) ?></div>
                  </div>
                </div>
              </td>
              <td><?= h($us['dni']) ?></td>
              <td><span class="badge badge-blue"><?= h(etiqueta_rol($us['rol'])) ?></span></td>
              <td>
                <span class="badge <?= $us['estado'] ? 'badge-green' : 'badge-gray' ?>"><?= $us['estado'] ? 'Activo' : 'Inactivo' ?></span>
                <?php if ((float)$us['mora_pendiente'] > 0): ?>
                  <div style="margin-top:4px;"><span class="badge badge-red">Mora S/ <?= number_format((float)$us['mora_pendiente'], 2) ?></span></div>
                <?php endif; ?>
              </td>
              <td>
                <div class="actions-cell">
                  <?php if ($puedeEditar): ?>
                    <a href="lectores.php?editar=<?= (int)$us['id'] ?>" class="btn btn-navy btn-sm">Editar</a>
                  <?php endif; ?>
                  <a href="lectores.php?historial=<?= (int)$us['id'] ?>" class="btn btn-outline btn-sm">Historial</a>
                  <?php if ($esAdmin && (int)$us['id'] !== (int)usuario_actual()['id']): ?>
                    <form method="post" style="display:inline;">
                      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                      <input type="hidden" name="accion" value="toggle_estado">
                      <input type="hidden" name="usuario_id" value="<?= (int)$us['id'] ?>">
                      <button class="btn btn-outline btn-sm" type="submit"><?= $us['estado'] ? 'Desactivar' : 'Activar' ?></button>
                    </form>
                    <form method="post" style="display:inline;">
                      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                      <input type="hidden" name="accion" value="eliminar_usuario">
                      <input type="hidden" name="usuario_id" value="<?= (int)$us['id'] ?>">
                      <button class="btn btn-danger btn-sm" type="submit" data-confirm="¿Seguro que deseas eliminar al usuario <?= h($us['nombres'] . ' ' . $us['apellidos']) ?>?">Eliminar</button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if ($verHistorial): ?>
        <hr style="border:none;border-top:1px solid var(--border);margin:20px 0;">
        <h3>Historial de préstamos</h3>
        <?php if (!$historial): ?>
          <p class="text-muted">Este usuario no registra préstamos.</p>
        <?php else: ?>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Libro</th><th>Código</th><th>Préstamo</th><th>Vencimiento</th><th>Estado</th></tr></thead>
              <tbody>
                <?php foreach ($historial as $hItem): ?>
                <tr>
                  <td><?= h($hItem['titulo']) ?></td>
                  <td><span class="code-tag"><?= h($hItem['codigo_ejemplar']) ?></span></td>
                  <td><?= h($hItem['fecha_prestamo']) ?></td>
                  <td><?= h($hItem['fecha_vencimiento']) ?></td>
                  <td><span class="badge <?= $hItem['estado'] === 'vencido' ? 'badge-red' : ($hItem['estado'] === 'activo' ? 'badge-green' : 'badge-gray') ?>"><?= ucfirst($hItem['estado']) ?></span></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</main>
<?php render_footer(); ?>
