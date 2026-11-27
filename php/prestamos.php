<?php
require_once __DIR__ . '/auth.php';

function actualizar_vencidos(PDO $pdo): void {
    $cfg = obtener_config($pdo);
    $tarifa = (float)$cfg['tarifa_mora_diaria'];
    $sql = "
        UPDATE prestamos
        SET estado = 'vencido',
            mora_monto = GREATEST(0, DATEDIFF(CURDATE(), fecha_vencimiento)) * ?
        WHERE estado IN ('activo','vencido')
          AND fecha_devolucion IS NULL
          AND CURDATE() > fecha_vencimiento
    ";
    $pdo->prepare($sql)->execute([$tarifa]);
}

function registrar_prestamo(PDO $pdo, int $lector_id, string $codigo_ejemplar, string $observaciones = ''): array {
    actualizar_vencidos($pdo);
    $codigo_ejemplar = strtoupper(trim($codigo_ejemplar));

    $uStmt = $pdo->prepare("SELECT id, nombres, apellidos, estado FROM usuarios WHERE id = ?");
    $uStmt->execute([$lector_id]);
    $lector = $uStmt->fetch();
    if (!$lector || !$lector['estado']) {
        return ['ok' => false, 'msg' => 'El lector seleccionado no existe o está inactivo.'];
    }

    $moraStmt = $pdo->prepare("
        SELECT COUNT(*) FROM prestamos
        WHERE lector_id = ? AND (estado = 'vencido' OR (mora_monto > 0 AND mora_pagada = 0))
    ");
    $moraStmt->execute([$lector_id]);
    if ((int)$moraStmt->fetchColumn() > 0) {
        return ['ok' => false, 'msg' => 'Lector bloqueado: registra préstamos vencidos o moras pendientes de pago.'];
    }

    $ejStmt = $pdo->prepare("
        SELECT e.*, l.titulo FROM ejemplares e
        JOIN libros l ON l.id = e.libro_id
        WHERE e.codigo_ejemplar = ? AND l.estado = 1
    ");
    $ejStmt->execute([$codigo_ejemplar]);
    $ej = $ejStmt->fetch();
    if (!$ej) {
        return ['ok' => false, 'msg' => "No se encontró el ejemplar '{$codigo_ejemplar}'."];
    }
    if ($ej['estado'] !== 'disponible') {
        return ['ok' => false, 'msg' => "El ejemplar {$codigo_ejemplar} no está disponible (estado: {$ej['estado']})."];
    }

    $cfg = obtener_config($pdo);
    $dias = (int)$cfg['dias_prestamo'];

    $pdo->beginTransaction();
    try {
        $ins = $pdo->prepare("
            INSERT INTO prestamos (ejemplar_id, lector_id, bibliotecario_id, fecha_prestamo, fecha_vencimiento, observaciones)
            VALUES (?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? DAY), ?)
        ");
        $ins->execute([
            $ej['id'],
            $lector_id,
            usuario_actual()['id'],
            $dias,
            trim($observaciones) ?: null,
        ]);
        $pdo->prepare("UPDATE ejemplares SET estado = 'prestado' WHERE id = ?")->execute([$ej['id']]);
        registrar_auditoria($pdo, 'PRESTAMO', "Prestó {$codigo_ejemplar} ({$ej['titulo']}) a {$lector['nombres']} {$lector['apellidos']}.");
        $pdo->commit();
        return ['ok' => true, 'msg' => "Préstamo registrado: {$ej['titulo']} ({$codigo_ejemplar}) para {$lector['nombres']} {$lector['apellidos']}."];
    } catch (PDOException $e) {
        $pdo->rollBack();
        return ['ok' => false, 'msg' => 'Error al registrar el préstamo.'];
    }
}

function registrar_devolucion(PDO $pdo, string $codigo_ejemplar, bool $danado = false, string $observaciones = ''): array {
    actualizar_vencidos($pdo);
    $codigo_ejemplar = strtoupper(trim($codigo_ejemplar));

    $stmt = $pdo->prepare("
        SELECT p.*, e.id AS ej_id, e.codigo_ejemplar, l.titulo
        FROM prestamos p
        JOIN ejemplares e ON e.id = p.ejemplar_id
        JOIN libros l ON l.id = e.libro_id
        WHERE e.codigo_ejemplar = ? AND p.estado IN ('activo','vencido')
        ORDER BY p.id DESC LIMIT 1
    ");
    $stmt->execute([$codigo_ejemplar]);
    $p = $stmt->fetch();
    if (!$p) {
        return ['ok' => false, 'msg' => "No hay un préstamo activo para el código '{$codigo_ejemplar}'."];
    }

    $cfg = obtener_config($pdo);
    $dias_atraso = max(0, (int)((strtotime(date('Y-m-d')) - strtotime($p['fecha_vencimiento'])) / 86400));
    $mora = round($dias_atraso * (float)$cfg['tarifa_mora_diaria'], 2);
    $nuevo_estado_ej = $danado ? 'mantenimiento' : 'disponible';

    $pdo->beginTransaction();
    try {
        $upd = $pdo->prepare("
            UPDATE prestamos
            SET fecha_devolucion = CURDATE(),
                mora_monto = ?,
                mora_pagada = CASE WHEN ? = 0 THEN 1 ELSE 0 END,
                estado = 'devuelto',
                observaciones = CONCAT(COALESCE(observaciones, ''), ?)
            WHERE id = ?
        ");
        $nota = trim($observaciones) !== '' ? ' | Dev: ' . trim($observaciones) : '';
        $upd->execute([$mora, $mora, $nota, $p['id']]);
        $pdo->prepare("UPDATE ejemplares SET estado = ? WHERE id = ?")->execute([$nuevo_estado_ej, $p['ej_id']]);
        registrar_auditoria($pdo, 'DEVOLUCION', "Devolución de {$codigo_ejemplar} ({$p['titulo']}). Mora: {$cfg['moneda']} {$mora}.");
        $pdo->commit();

        $extra = $mora > 0 ? " Mora generada: {$cfg['moneda']} " . number_format($mora, 2) . "." : ' Devuelto sin mora.';
        return ['ok' => true, 'msg' => "Devolución registrada para {$codigo_ejemplar}.{$extra}"];
    } catch (PDOException $e) {
        $pdo->rollBack();
        return ['ok' => false, 'msg' => 'Error al registrar la devolución.'];
    }
}

function renovar_prestamo(PDO $pdo, int $prestamo_id): array {
    actualizar_vencidos($pdo);
    $cfg = obtener_config($pdo);
    $stmt = $pdo->prepare("SELECT * FROM prestamos WHERE id = ?");
    $stmt->execute([$prestamo_id]);
    $p = $stmt->fetch();
    if (!$p || $p['estado'] !== 'activo') {
        return ['ok' => false, 'msg' => 'Solo se pueden renovar préstamos activos que no estén vencidos.'];
    }
    if ((int)$p['renovaciones'] >= (int)$cfg['max_renovaciones']) {
        return ['ok' => false, 'msg' => "Límite máximo de {$cfg['max_renovaciones']} renovaciones alcanzado."];
    }
    $dias = (int)$cfg['dias_prestamo'];
    $upd = $pdo->prepare("
        UPDATE prestamos
        SET fecha_vencimiento = DATE_ADD(fecha_vencimiento, INTERVAL ? DAY),
            renovaciones = renovaciones + 1
        WHERE id = ?
    ");
    $upd->execute([$dias, $prestamo_id]);
    registrar_auditoria($pdo, 'RENOVACION', "Renovó préstamo ID {$prestamo_id} por {$dias} días más.");
    return ['ok' => true, 'msg' => "Préstamo renovado por {$dias} días adicionales."];
}

function marcar_mora_pagada(PDO $pdo, int $prestamo_id): array {
    $pdo->prepare("UPDATE prestamos SET mora_pagada = 1 WHERE id = ?")->execute([$prestamo_id]);
    registrar_auditoria($pdo, 'PAGO_MORA', "Marcó como pagada la mora del préstamo ID {$prestamo_id}.");
    return ['ok' => true, 'msg' => 'Mora registrada como pagada.'];
}

function listar_prestamos_activos(PDO $pdo, ?int $lector_id = null): array {
    actualizar_vencidos($pdo);
    $sql = "
        SELECT p.*, e.codigo_ejemplar, l.titulo, l.portada,
               CONCAT(u.nombres, ' ', u.apellidos) AS lector, u.dni,
               DATEDIFF(p.fecha_vencimiento, CURDATE()) AS dias_restantes
        FROM prestamos p
        JOIN ejemplares e ON e.id = p.ejemplar_id
        JOIN libros l ON l.id = e.libro_id
        JOIN usuarios u ON u.id = p.lector_id
        WHERE p.estado IN ('activo','vencido')
    ";
    $params = [];
    if ($lector_id !== null) {
        $sql .= " AND p.lector_id = ?";
        $params[] = $lector_id;
    }
    $sql .= " ORDER BY p.fecha_vencimiento ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function listar_historial_lector(PDO $pdo, int $lector_id): array {
    $stmt = $pdo->prepare("
        SELECT p.*, e.codigo_ejemplar, l.titulo
        FROM prestamos p
        JOIN ejemplares e ON e.id = p.ejemplar_id
        JOIN libros l ON l.id = e.libro_id
        WHERE p.lector_id = ?
        ORDER BY p.id DESC
    ");
    $stmt->execute([$lector_id]);
    return $stmt->fetchAll();
}
