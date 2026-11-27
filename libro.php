<?php
require_once __DIR__ . '/php/layout.php';
require_once __DIR__ . '/php/libros.php';

exigir_login();
$id = (int)($_GET['id'] ?? 0);
$esStaff = in_array(usuario_actual()['rol'], ['administrador', 'bibliotecario'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $esStaff) {
    verificar_csrf();
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'agregar_ejemplar') {
        $res = agregar_ejemplar($pdo, $id, $_POST['codigo_ejemplar'] ?? '');
        flash_set($res['ok'] ? 'ok' : 'error', $res['msg']);
        header("Location: libro.php?id={$id}");
        exit;
    }
    if ($accion === 'editar_libro') {
        $res = actualizar_libro($pdo, $id, $_POST, $_FILES['portada'] ?? null);
        flash_set($res['ok'] ? 'ok' : 'error', $res['msg']);
        header("Location: libro.php?id={$id}");
        exit;
    }
    if ($accion === 'eliminar_libro') {
        $res = eliminar_libro($pdo, $id);
        flash_set($res['ok'] ? 'ok' : 'error', $res['msg']);
        header('Location: ' . ($res['ok'] ? 'catalogo_admin.php' : "libro.php?id={$id}"));
        exit;
    }
}

$libro = obtener_libro($pdo, $id);
if (!$libro) {
    flash_set('error', 'El libro solicitado no existe.');
    header('Location: catalogo.php');
    exit;
}

$disponible = null;
foreach ($libro['ejemplares'] as $e) {
    if ($e['estado'] === 'disponible') { $disponible = $e['codigo_ejemplar']; break; }
}
$autores = $esStaff ? listar_autores($pdo) : [];
$categorias = $esStaff ? listar_categorias($pdo) : [];

render_head($libro['titulo'] . ' — SIBI');
render_topbar('catalogo');
?>
<main class="container">
  <div class="mb-16">
    <a href="catalogo.php" class="btn btn-outline btn-sm">← Volver al catálogo</a>
  </div>

  <div class="detail-grid mb-22">
    <div>
      <img src="<?= h($libro['portada']) ?>" alt="<?= h($libro['titulo']) ?>" class="detail-cover">
    </div>

    <div class="card">
      <span class="badge badge-blue mb-16"><?= h($libro['categoria']) ?></span>
      <h1 style="font-size:30px;margin-bottom:6px;"><?= h($libro['titulo']) ?></h1>
      <p class="text-muted" style="font-size:15px;">por <strong><?= h($libro['autor']) ?></strong></p>

      <div class="meta-list">
        <div><span>Editorial</span><?= h($libro['editorial'] ?: 'No registrada') ?></div>
        <div><span>Año</span><?= h((string)($libro['anio_publicacion'] ?: '—')) ?></div>
        <div><span>ISBN</span><?= h($libro['isbn'] ?: '—') ?></div>
        <div><span>Categoría</span><?= h($libro['categoria']) ?></div>
      </div>

      <h3 style="margin-bottom:8px;">Sinopsis</h3>
      <p style="color:var(--muted);"><?= nl2br(h($libro['sinopsis'] ?: 'Sin sinopsis registrada.')) ?></p>
    </div>

    <aside class="card">
      <h3>Ejemplares Físicos</h3>
      <?php if (!$libro['ejemplares']): ?>
        <p class="text-muted mb-16">Aún no hay copias físicas registradas.</p>
      <?php else: ?>
        <?php foreach ($libro['ejemplares'] as $e): ?>
          <div class="copy-item">
            <span class="code-tag"><?= h($e['codigo_ejemplar']) ?></span>
            <?php if ($e['estado'] === 'disponible'): ?>
              <span class="badge badge-green">Disponible</span>
            <?php elseif ($e['estado'] === 'prestado'): ?>
              <span class="badge badge-gray">Prestado</span>
            <?php else: ?>
              <span class="badge badge-amber">Mantenimiento</span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if ($esStaff): ?>
        <?php if ($disponible): ?>
          <a href="mostrador.php?codigo=<?= urlencode($disponible) ?>" class="btn btn-primary btn-block" style="margin-top:12px;">Registrar Préstamo</a>
        <?php endif; ?>

        <hr style="border:none;border-top:1px solid var(--border);margin:18px 0;">
        <h3 style="font-size:15px;">Agregar copia física</h3>
        <form method="post" style="display:flex;gap:8px;margin-bottom:14px;">
          <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="accion" value="agregar_ejemplar">
          <input class="input" type="text" name="codigo_ejemplar" required placeholder="Ej. REC-PRG-009">
          <button type="submit" class="btn btn-navy btn-sm">+ Copia</button>
        </form>

        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="accion" value="eliminar_libro">
          <button type="submit" class="btn btn-danger btn-block btn-sm" data-confirm="¿Seguro que deseas eliminar '<?= h($libro['titulo']) ?>' del catálogo?">Eliminar libro del catálogo</button>
        </form>
      <?php else: ?>
        <p class="text-muted" style="margin-top:14px;">Presenta el código del ejemplar disponible en el mostrador de biblioteca para solicitar el préstamo.</p>
      <?php endif; ?>
    </aside>
  </div>

  <?php if ($esStaff): ?>
  <section class="card">
    <h3>Editar libro y cambiar portada</h3>
    <p class="card-sub">Actualiza la ficha bibliográfica o sube una nueva imagen de portada (JPG, PNG o WebP hasta 2 MB).</p>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="accion" value="editar_libro">
      <div class="grid-2">
        <div class="field">
          <label>Título</label>
          <input class="input" name="titulo" required value="<?= h($libro['titulo']) ?>">
        </div>
        <div class="field">
          <label>Portada del libro</label>
          <div class="cover-upload-box">
            <img id="edit-cover-preview" src="<?= h($libro['portada']) ?>" alt="Vista previa" class="cover-upload-preview">
            <div class="cover-upload-info">
              <input type="file" name="portada" accept="image/jpeg,image/png,image/webp" data-cover-input="edit-cover-preview">
              <small>JPG, PNG o WebP · Máx. 2 MB</small>
            </div>
          </div>
        </div>
      </div>
      <div class="grid-2">
        <div class="field">
          <label>Autor</label>
          <select class="input" name="autor_id" required>
            <?php foreach ($autores as $a): ?>
              <option value="<?= (int)$a['id'] ?>" <?= (int)$a['id'] === (int)$libro['autor_id'] ? 'selected' : '' ?>><?= h($a['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Categoría</label>
          <select class="input" name="categoria_id" required>
            <?php foreach ($categorias as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === (int)$libro['categoria_id'] ? 'selected' : '' ?>><?= h($c['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="grid-2">
        <div class="field"><label>ISBN</label><input class="input" name="isbn" value="<?= h($libro['isbn'] ?? '') ?>"></div>
        <div class="field"><label>Editorial</label><input class="input" name="editorial" value="<?= h($libro['editorial'] ?? '') ?>"></div>
      </div>
      <div class="grid-2">
        <div class="field"><label>Año de publicación</label><input class="input" type="number" name="anio_publicacion" value="<?= h((string)($libro['anio_publicacion'] ?? '')) ?>"></div>
        <div class="field"><label>Sinopsis</label><textarea class="input" name="sinopsis" rows="2"><?= h($libro['sinopsis'] ?? '') ?></textarea></div>
      </div>
      <button type="submit" class="btn btn-navy">Guardar cambios</button>
    </form>
  </section>
  <?php endif; ?>
</main>
<?php render_footer(); ?>
