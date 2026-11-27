<?php
require_once __DIR__ . '/php/layout.php';
require_once __DIR__ . '/php/prestamos.php';

exigir_rol(['administrador']);
actualizar_vencidos($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'guardar_config') {
        $dias = max(1, (int)($_POST['dias_prestamo'] ?? 7));
        $tarifa = max(0, (float)($_POST['tarifa_mora_diaria'] ?? 1.50));
        $max_ren = max(0, (int)($_POST['max_renovaciones'] ?? 2));
        $moneda = trim($_POST['moneda'] ?? 'S/') ?: 'S/';
        $pdo->prepare("UPDATE configuracion SET dias_prestamo = ?, tarifa_mora_diaria = ?, max_renovaciones = ?, moneda = ? LIMIT 1")
            ->execute([$dias, $tarifa, $max_ren, $moneda]);
        registrar_auditoria($pdo, 'CONFIGURACION', "Actualizó reglas: {$dias} días, {$moneda} {$tarifa}/día, {$max_ren} renovaciones.");
        flash_set('ok', 'Parámetros de biblioteca actualizados.');
        header('Location: panel.php');
        exit;
    }
    if ($accion === 'pagar_mora') {
        $res = marcar_mora_pagada($pdo, (int)($_POST['prestamo_id'] ?? 0));
        flash_set('ok', $res['msg']);
        header('Location: panel.php');
        exit;
    }
}

$cfg = obtener_config($pdo);
$totalLibros = (int)$pdo->query("SELECT COUNT(*) FROM libros WHERE estado = 1")->fetchColumn();
$prestamosActivos = listar_prestamos_activos($pdo);
$totalVencidos = count(array_filter($prestamosActivos, fn($p) => $p['estado'] === 'vencido'));
$moraTotal = (float)$pdo->query("SELECT COALESCE(SUM(mora_monto), 0) FROM prestamos WHERE mora_monto > 0")->fetchColumn();
$morasPendientes = $pdo->query("
    SELECT p.*, e.codigo_ejemplar, l.titulo, CONCAT(u.nombres, ' ', u.apellidos) AS lector
    FROM prestamos p
    JOIN ejemplares e ON e.id = p.ejemplar_id
    JOIN libros l ON l.id = e.libro_id
    JOIN usuarios u ON u.id = p.lector_id
    WHERE p.mora_monto > 0 AND p.mora_pagada = 0
    ORDER BY p.fecha_vencimiento ASC
")->fetchAll();

render_head('Panel de Control — SIBI');
render_topbar('panel');
?>
<main class="container">
  <div class="section-bar">
        <h1 style="font-size:28px;">Panel de Control</h1>
        <div class="actions-cell no-print" id="reportes">
          <a href="exportar_csv.php" class="btn btn-outline btn-sm">Exportar CSV</a>
          <button type="button" onclick="window.print()" class="btn btn-navy btn-sm">Imprimir / PDF</button>
        </div>
      </div>

      <div class="kpi-grid">
        <div class="kpi">
          <div class="kpi-label">Títulos en Catálogo</div>
          <div class="kpi-value"><?= $totalLibros ?></div>
        </div>
        <div class="kpi kpi-green">
          <div class="kpi-label">Préstamos Activos</div>
          <div class="kpi-value"><?= count($prestamosActivos) ?></div>
        </div>
        <div class="kpi kpi-red kpi-danger">
          <div class="kpi-label">Vencidos</div>
          <div class="kpi-value"><?= $totalVencidos ?></div>
        </div>
        <div class="kpi kpi-orange">
          <div class="kpi-label">Mora acumulada</div>
          <div class="kpi-value"><?= h($cfg['moneda']) ?> <?= number_format($moraTotal, 2) ?></div>
        </div>
      </div>

      <div class="card mb-22">
        <h3>Préstamos Activos</h3>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Lector</th><th>Libro</th><th>Código</th><th>Vencimiento</th><th>Estado</th></tr></thead>
            <tbody>
              <?php if (!$prestamosActivos): ?>
                <tr><td colspan="5" class="text-muted">No hay préstamos activos en este momento.</td></tr>
              <?php endif; ?>
              <?php foreach ($prestamosActivos as $p): ?>
              <tr>
                <td><strong><?= h($p['lector']) ?></strong></td>
                <td><?= h($p['titulo']) ?></td>
                <td><span class="code-tag"><?= h($p['codigo_ejemplar']) ?></span></td>
                <td><?= h($p['fecha_vencimiento']) ?></td>
                <td>
                  <?php if ($p['estado'] === 'vencido'): ?>
                    <span class="badge badge-red">Vencido · <?= h($cfg['moneda']) ?> <?= number_format((float)$p['mora_monto'], 2) ?></span>
                  <?php elseif ((int)$p['dias_restantes'] <= 2): ?>
                    <span class="badge badge-amber">Próximo a vencer</span>
                  <?php else: ?>
                    <span class="badge badge-green">A tiempo</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="grid-2">
        <div class="card">
          <h3>Moras pendientes</h3>
          <?php if (!$morasPendientes): ?>
            <p class="text-muted">No hay moras pendientes de cobro.</p>
          <?php else: ?>
            <?php foreach ($morasPendientes as $m): ?>
              <div class="copy-item">
                <div>
                  <strong><?= h($m['lector']) ?></strong>
                  <div class="text-muted"><?= h($m['titulo']) ?> (<?= h($m['codigo_ejemplar']) ?>)</div>
                </div>
                <div style="display:flex;align-items:center;gap:8px;">
                  <span class="badge badge-red"><?= h($cfg['moneda']) ?> <?= number_format((float)$m['mora_monto'], 2) ?></span>
                  <form method="post" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="accion" value="pagar_mora">
                    <input type="hidden" name="prestamo_id" value="<?= (int)$m['id'] ?>">
                    <button class="btn btn-navy btn-sm" type="submit">Marcar pagada</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <div class="card no-print">
          <h3>Configuración de parámetros</h3>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="accion" value="guardar_config">
            <div class="grid-2">
              <div class="field"><label>Días de préstamo</label><input class="input" type="number" min="1" name="dias_prestamo" value="<?= (int)$cfg['dias_prestamo'] ?>"></div>
              <div class="field"><label>Tarifa diaria</label><input class="input" type="number" step="0.10" min="0" name="tarifa_mora_diaria" value="<?= number_format((float)$cfg['tarifa_mora_diaria'], 2, '.', '') ?>"></div>
            </div>
            <div class="grid-2">
              <div class="field"><label>Máx. renovaciones</label><input class="input" type="number" min="0" name="max_renovaciones" value="<?= (int)$cfg['max_renovaciones'] ?>"></div>
              <div class="field"><label>Moneda</label><input class="input" name="moneda" value="<?= h($cfg['moneda']) ?>"></div>
            </div>
            <button type="submit" class="btn btn-primary">Guardar parámetros</button>
          </form>
        </div>
      </div>
</main>
<?php render_footer(); ?>
