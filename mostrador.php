<?php
require_once __DIR__ . '/php/layout.php';
require_once __DIR__ . '/php/prestamos.php';

exigir_rol(['administrador', 'bibliotecario']);
$tab = ($_GET['tab'] ?? 'prestar') === 'devolver' ? 'devolver' : 'prestar';
$codigo_pre = strtoupper(trim($_GET['codigo'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'prestar') {
        $res = registrar_prestamo($pdo, (int)($_POST['lector_id'] ?? 0), $_POST['codigo_ejemplar'] ?? '', $_POST['observaciones'] ?? '');
        flash_set($res['ok'] ? 'ok' : 'error', $res['msg']);
        header('Location: mostrador.php?tab=prestar');
        exit;
    }
    if ($accion === 'devolver') {
        $res = registrar_devolucion($pdo, $_POST['codigo_ejemplar'] ?? '', isset($_POST['danado']), $_POST['observaciones'] ?? '');
        flash_set($res['ok'] ? 'ok' : 'error', $res['msg']);
        header('Location: mostrador.php?tab=devolver');
        exit;
    }
    if ($accion === 'renovar') {
        $res = renovar_prestamo($pdo, (int)($_POST['prestamo_id'] ?? 0));
        flash_set($res['ok'] ? 'ok' : 'error', $res['msg']);
        header('Location: mostrador.php?tab=devolver');
        exit;
    }
}

$cfg = obtener_config($pdo);
$lectores = $pdo->query("SELECT id, nombres, apellidos, dni FROM usuarios WHERE estado = 1 ORDER BY nombres")->fetchAll();
$disponibles = $pdo->query("
    SELECT e.codigo_ejemplar, l.titulo
    FROM ejemplares e JOIN libros l ON l.id = e.libro_id
    WHERE e.estado = 'disponible' AND l.estado = 1
    ORDER BY e.codigo_ejemplar
")->fetchAll();
$activos = listar_prestamos_activos($pdo);

render_head('Mostrador — SIBI');
render_topbar('mostrador');
?>
<main class="container">
  <h1 style="text-align:center;font-size:32px;margin-bottom:8px;">Mostrador</h1>
  <div class="tabs">
    <a href="mostrador.php?tab=prestar" class="tab <?= $tab === 'prestar' ? 'active' : '' ?>">Prestar</a>
    <a href="mostrador.php?tab=devolver" class="tab <?= $tab === 'devolver' ? 'active' : '' ?>">Devolver</a>
  </div>

  <?php if ($tab === 'prestar'): ?>
    <form method="post" class="counter-grid">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="accion" value="prestar">
      <div class="card">
        <div class="step-title">Paso 1 · Identificar Lector</div>
        <div class="field">
          <label for="lector_id">Seleccionar lector</label>
          <select name="lector_id" id="lector_id" class="input" required>
            <option value="">Buscar por nombre o DNI...</option>
            <?php foreach ($lectores as $lec): ?>
              <option value="<?= (int)$lec['id'] ?>"><?= h($lec['nombres'] . ' ' . $lec['apellidos'] . ' — DNI ' . $lec['dni']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <hr style="border:none;border-top:1px solid var(--border);margin:20px 0;">

        <div class="step-title">Paso 2 · Identificar Ejemplar</div>
        <div class="field">
          <label for="codigo_ejemplar">Código del ejemplar</label>
          <input list="lista-disponibles" class="input" id="codigo_ejemplar" name="codigo_ejemplar" required value="<?= h($codigo_pre) ?>" placeholder="Ej. REC-PRG-001">
          <datalist id="lista-disponibles">
            <?php foreach ($disponibles as $d): ?>
              <option value="<?= h($d['codigo_ejemplar']) ?>"><?= h($d['titulo']) ?></option>
            <?php endforeach; ?>
          </datalist>
        </div>
        <div class="field">
          <label for="observaciones">Observaciones (opcional)</label>
          <input class="input" id="observaciones" name="observaciones" placeholder="Ej. Tapa en buen estado">
        </div>
      </div>

      <div class="summary-box">
        <div class="step-title">Paso 3 · Resumen de Operación</div>
        <div class="summary-row"><span>Fecha de entrega</span><strong><?= date('d/m/Y') ?></strong></div>
        <div class="summary-row"><span>Fecha límite (<?= (int)$cfg['dias_prestamo'] ?> días)</span><strong><?= date('d/m/Y', strtotime('+' . (int)$cfg['dias_prestamo'] . ' days')) ?></strong></div>
        <div class="summary-row"><span>Tarifa de mora diaria</span><strong><?= h($cfg['moneda']) ?> <?= number_format((float)$cfg['tarifa_mora_diaria'], 2) ?> / día</strong></div>
        <div class="summary-row"><span>Renovaciones permitidas</span><strong>Hasta <?= (int)$cfg['max_renovaciones'] ?></strong></div>
        <button type="submit" class="btn btn-primary btn-block" style="margin-top:18px;">Confirmar préstamo</button>
      </div>
    </form>
  <?php else: ?>
    <div class="counter-grid">
      <div class="card">
        <div class="step-title">Registrar Devolución</div>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="accion" value="devolver">
          <div class="field">
            <label for="codigo_dev">Código del ejemplar devuelto</label>
            <input class="input" id="codigo_dev" name="codigo_ejemplar" required placeholder="Ej. REC-BDD-002">
          </div>
          <div class="field">
            <label style="display:flex;align-items:center;gap:8px;font-weight:500;">
              <input type="checkbox" name="danado" value="1"> Marcar ejemplar en mantenimiento por daño físico
            </label>
          </div>
          <div class="field">
            <label for="obs_dev">Observaciones de recepción</label>
            <input class="input" id="obs_dev" name="observaciones" placeholder="Opcional">
          </div>
          <button type="submit" class="btn btn-primary btn-block">Confirmar Devolución</button>
        </form>
      </div>

      <div class="card">
        <h3>Préstamos Activos (<?= count($activos) ?>)</h3>
        <?php if (!$activos): ?>
          <p class="text-muted">No hay préstamos pendientes de devolución.</p>
        <?php else: ?>
          <?php foreach ($activos as $a): ?>
            <div class="copy-item" style="flex-direction:column;align-items:stretch;gap:8px;">
              <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
                <strong><?= h($a['titulo']) ?></strong>
                <span class="code-tag"><?= h($a['codigo_ejemplar']) ?></span>
              </div>
              <div class="text-muted">Lector: <?= h($a['lector']) ?> · Vence: <?= h($a['fecha_vencimiento']) ?></div>
              <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
                <?php if ($a['estado'] === 'vencido'): ?>
                  <span class="badge badge-red">Vencido · Mora <?= h($cfg['moneda']) ?> <?= number_format((float)$a['mora_monto'], 2) ?></span>
                <?php elseif ((int)$a['dias_restantes'] <= 2): ?>
                  <span class="badge badge-amber">Próximo a vencer</span>
                <?php else: ?>
                  <span class="badge badge-green">A tiempo</span>
                <?php endif; ?>
                <div class="actions-cell">
                  <form method="post" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="accion" value="devolver">
                    <input type="hidden" name="codigo_ejemplar" value="<?= h($a['codigo_ejemplar']) ?>">
                    <button class="btn btn-primary btn-sm" type="submit">Devolver</button>
                  </form>
                  <?php if ($a['estado'] === 'activo'): ?>
                  <form method="post" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="accion" value="renovar">
                    <input type="hidden" name="prestamo_id" value="<?= (int)$a['id'] ?>">
                    <button class="btn btn-outline btn-sm" type="submit">Renovar</button>
                  </form>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</main>
<?php render_footer(); ?>
