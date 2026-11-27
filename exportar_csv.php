<?php
require_once __DIR__ . '/php/auth.php';
require_once __DIR__ . '/php/prestamos.php';

exigir_rol(['administrador']);
actualizar_vencidos($pdo);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="reporte-prestamos-sibi-' . date('Y-m-d') . '.csv"');
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');
fputcsv($out, ['ID', 'Lector', 'DNI', 'Libro', 'Código', 'Fecha Préstamo', 'Fecha Vencimiento', 'Fecha Devolución', 'Mora', 'Estado']);

$rows = $pdo->query("
    SELECT p.id, CONCAT(u.nombres, ' ', u.apellidos) AS lector, u.dni, l.titulo,
           e.codigo_ejemplar, p.fecha_prestamo, p.fecha_vencimiento, p.fecha_devolucion, p.mora_monto, p.estado
    FROM prestamos p
    JOIN usuarios u ON u.id = p.lector_id
    JOIN ejemplares e ON e.id = p.ejemplar_id
    JOIN libros l ON l.id = e.libro_id
    ORDER BY p.id DESC
")->fetchAll();

foreach ($rows as $r) {
    fputcsv($out, array_values($r));
}
fclose($out);
exit;
