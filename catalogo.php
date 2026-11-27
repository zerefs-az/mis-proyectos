<?php
require_once __DIR__ . '/php/layout.php';
require_once __DIR__ . '/php/libros.php';
require_once __DIR__ . '/php/prestamos.php';
require_once __DIR__ . '/php/usuarios.php';

exigir_login();
$u = usuario_actual();
$esStaff = in_array($u['rol'], ['administrador', 'bibliotecario'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $esStaff && ($_POST['accion'] ?? '') === 'prestamo_rapido') {
    verificar_csrf();
    $res = registrar_prestamo($pdo, (int)($_POST['lector_id'] ?? 0), $_POST['codigo_ejemplar'] ?? '');
    flash_set($res['ok'] ? 'ok' : 'error', $res['msg']);
    header('Location: catalogo.php');
    exit;
}

$q = trim($_GET['q'] ?? '');
$cat = (int)($_GET['cat'] ?? 0);
$solo_disp = isset($_GET['disp']) && $_GET['disp'] === '1';

$categorias = listar_categorias($pdo);
$libros = buscar_catalogo($pdo, $q, $cat, $solo_disp);
$misPrestamos = $esStaff ? [] : listar_prestamos_activos($pdo, $u['id']);
$lectores = $esStaff ? $pdo->query("SELECT id, nombres, apellidos, dni FROM usuarios WHERE estado = 1 ORDER BY nombres")->fetchAll() : [];

render_head('Catálogo — SIBI');
render_topbar('catalogo');
?>
<main class="container">
  <section class="hero">
    <div class="hero-content">
      <h1>Explora el Catálogo de Biblioteca</h1>
      <form method="get" class="hero-search">
        <?php if ($cat): ?><input type="hidden" name="cat" value="<?= $cat ?>"><?php endif; ?>
        <?php if ($solo_disp): ?><input type="hidden" name="disp" value="1"><?php endif; ?>
        <input type="text" name="q" value="<?= h($q) ?>" placeholder="Buscar por título, autor, categoría o ISBN...">
        <button type="submit">Buscar</button>
      </form>
    </div>
  </section>

  <div class="chips">
    <a class="chip <?= $cat === 0 ? 'active' : '' ?>" href="catalogo.php<?= $q !== '' ? '?q=' . urlencode($q) : '' ?>">Todos</a>
    <?php foreach ($categorias as $c): ?>
      <a class="chip <?= $cat === (int)$c['id'] ? 'active' : '' ?>"
         href="catalogo.php?cat=<?= (int)$c['id'] ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?><?= $solo_disp ? '&disp=1' : '' ?>">
        <?= h($c['nombre']) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="catalog-layout">
    <section>
      <div class="section-bar">
        <h2>Estantería Digital <span class="text-muted">(<?= count($libros) ?> títulos)</span></h2>
        <form method="get">
          <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= h($q) ?>"><?php endif; ?>
          <?php if ($cat): ?><input type="hidden" name="cat" value="<?= $cat ?>"><?php endif; ?>
          <label class="toggle-avail">
            <input type="checkbox" name="disp" value="1" <?= $solo_disp ? 'checked' : '' ?> onchange="this.form.submit()">
            Solo disponibles
          </label>
        </form>
      </div>

      <?php if (!$libros): ?>
        <div class="empty-state">No se encontraron libros con los filtros actuales.</div>
      <?php else: ?>
        <div class="books-grid">
          <?php foreach ($libros as $l): ?>
            <?php $disp = (int)$l['disponibles']; ?>
            <a href="libro.php?id=<?= (int)$l['id'] ?>" class="book-card">
              <div class="book-cover-wrap">
                <img src="<?= h($l['portada']) ?>" alt="<?= h($l['titulo']) ?>" loading="lazy">
              </div>
              <div class="book-body">
                <div class="book-title"><?= h($l['titulo']) ?></div>
                <div class="book-author"><?= h($l['autor']) ?></div>
                <?php if ($disp > 0): ?>
                  <span class="badge badge-green">Disponible (<?= $disp ?>)</span>
                <?php else: ?>
                  <span class="badge badge-gray">Prestado</span>
                <?php endif; ?>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <aside class="card">
      <?php if ($esStaff): ?>
        <h3>Panel de Préstamo Rápido</h3>
        <p class="card-sub">Registra una salida directa sin salir del catálogo.</p>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="accion" value="prestamo_rapido">
          <div class="field">
            <label for="lector_id">Lector</label>
            <select name="lector_id" id="lector_id" class="input" required>
              <option value="">Seleccionar lector...</option>
              <?php foreach ($lectores as $lec): ?>
                <option value="<?= (int)$lec['id'] ?>"><?= h($lec['nombres'] . ' ' . $lec['apellidos'] . ' (' . $lec['dni'] . ')') ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="codigo_ejemplar">Código de ejemplar</label>
            <input class="input" type="text" id="codigo_ejemplar" name="codigo_ejemplar" required placeholder="Ej. REC-PRG-001">
          </div>
          <button type="submit" class="btn btn-primary btn-block">Registrar préstamo</button>
        </form>
        <div style="margin-top:12px;">
          <a href="mostrador.php" class="btn btn-outline btn-block">Ir a Mostrador completo</a>
        </div>
      <?php else: ?>
        <h3>Mi Estantería</h3>
        <p class="card-sub">Tus préstamos activos y fechas de entrega.</p>
        <?php if (!$misPrestamos): ?>
          <p class="text-muted">No tienes préstamos activos en este momento.</p>
        <?php else: ?>
          <?php foreach ($misPrestamos as $mp): ?>
            <div class="copy-item" style="flex-direction:column;align-items:flex-start;gap:6px;">
              <strong><?= h($mp['titulo']) ?></strong>
              <span class="text-muted">Código: <span class="code-tag"><?= h($mp['codigo_ejemplar']) ?></span></span>
              <span class="text-muted">Vence: <?= h($mp['fecha_vencimiento']) ?></span>
              <?php if ($mp['estado'] === 'vencido'): ?>
                <span class="badge badge-red">Vencido · Mora S/ <?= number_format((float)$mp['mora_monto'], 2) ?></span>
              <?php elseif ((int)$mp['dias_restantes'] <= 2): ?>
                <span class="badge badge-amber">Próximo a vencer</span>
              <?php else: ?>
                <span class="badge badge-green">A tiempo</span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      <?php endif; ?>
    </aside>
  </div>
</main>
<?php render_footer(); ?>
