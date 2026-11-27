<?php
require_once __DIR__ . '/auth.php';

function listar_categorias(PDO $pdo): array {
    return $pdo->query('SELECT * FROM categorias ORDER BY nombre')->fetchAll();
}

function listar_autores(PDO $pdo): array {
    return $pdo->query('SELECT * FROM autores ORDER BY nombre')->fetchAll();
}

function procesar_portada_subida(?array $archivo): array {
    if (!$archivo || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'ruta' => null];
    }
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'msg' => 'No se pudo subir la portada. Intenta con otra imagen.'];
    }
    if ($archivo['size'] > 2 * 1024 * 1024) {
        return ['ok' => false, 'msg' => 'La portada supera el tamaño máximo permitido de 2 MB.'];
    }

    $info = @getimagesize($archivo['tmp_name']);
    $permitidos = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    if (!$info || !isset($permitidos[$info['mime']])) {
        return ['ok' => false, 'msg' => 'Formato de portada no válido. Usa JPG, PNG o WebP.'];
    }

    $ext = $permitidos[$info['mime']];
    $dir = __DIR__ . '/../assets/img/portadas';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $nombreArchivo = 'portada-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destino = $dir . '/' . $nombreArchivo;
    if (!move_uploaded_file($archivo['tmp_name'], $destino)) {
        return ['ok' => false, 'msg' => 'No se pudo guardar la imagen en el servidor.'];
    }

    return ['ok' => true, 'ruta' => 'assets/img/portadas/' . $nombreArchivo];
}

function buscar_catalogo(PDO $pdo, string $q = '', int $categoria_id = 0, bool $solo_disponibles = false): array {
    $sql = "
        SELECT l.*, a.nombre AS autor, c.nombre AS categoria,
               SUM(CASE WHEN e.estado = 'disponible' THEN 1 ELSE 0 END) AS disponibles,
               COUNT(e.id) AS total_ejemplares
        FROM libros l
        JOIN autores a ON a.id = l.autor_id
        JOIN categorias c ON c.id = l.categoria_id
        LEFT JOIN ejemplares e ON e.libro_id = l.id AND e.estado <> 'baja'
        WHERE l.estado = 1
    ";
    $params = [];

    if ($q !== '') {
        $sql .= " AND (l.titulo LIKE ? OR a.nombre LIKE ? OR l.isbn LIKE ? OR c.nombre LIKE ?)";
        $like = "%{$q}%";
        array_push($params, $like, $like, $like, $like);
    }
    if ($categoria_id > 0) {
        $sql .= " AND l.categoria_id = ?";
        $params[] = $categoria_id;
    }

    $sql .= " GROUP BY l.id";
    if ($solo_disponibles) {
        $sql .= " HAVING disponibles > 0";
    }
    $sql .= " ORDER BY l.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function obtener_libro(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("
        SELECT l.*, a.nombre AS autor, c.nombre AS categoria
        FROM libros l
        JOIN autores a ON a.id = l.autor_id
        JOIN categorias c ON c.id = l.categoria_id
        WHERE l.id = ? AND l.estado = 1
    ");
    $stmt->execute([$id]);
    $libro = $stmt->fetch();
    if (!$libro) return null;

    $ej = $pdo->prepare("SELECT * FROM ejemplares WHERE libro_id = ? AND estado <> 'baja' ORDER BY codigo_ejemplar");
    $ej->execute([$id]);
    $libro['ejemplares'] = $ej->fetchAll();
    return $libro;
}

function crear_libro(PDO $pdo, array $datos, ?array $archivo_portada = null): array {
    $titulo = trim($datos['titulo'] ?? '');
    $autor_id = (int)($datos['autor_id'] ?? 0);
    $categoria_id = (int)($datos['categoria_id'] ?? 0);
    $codigo = strtoupper(trim($datos['codigo_ejemplar'] ?? ''));

    if ($titulo === '' || $autor_id <= 0 || $categoria_id <= 0) {
        return ['ok' => false, 'msg' => 'Completa título, autor y categoría.'];
    }

    $subida = procesar_portada_subida($archivo_portada);
    if (!$subida['ok']) {
        return $subida;
    }
    $portada = $subida['ruta'] ?: 'assets/img/portada-default.jpg';

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO libros (titulo, isbn, editorial, anio_publicacion, autor_id, categoria_id, sinopsis, portada)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $anio = !empty($datos['anio_publicacion']) ? (int)$datos['anio_publicacion'] : null;
        $stmt->execute([
            $titulo,
            trim($datos['isbn'] ?? '') ?: null,
            trim($datos['editorial'] ?? '') ?: null,
            $anio,
            $autor_id,
            $categoria_id,
            trim($datos['sinopsis'] ?? '') ?: null,
            $portada,
        ]);
        $libro_id = (int)$pdo->lastInsertId();

        if ($codigo !== '') {
            $ej = $pdo->prepare("INSERT INTO ejemplares (libro_id, codigo_ejemplar, estado) VALUES (?, ?, 'disponible')");
            $ej->execute([$libro_id, $codigo]);
        }

        registrar_auditoria($pdo, 'CREAR_LIBRO', "Registró el libro '{$titulo}' (ID {$libro_id}).");
        $pdo->commit();
        return ['ok' => true, 'msg' => 'Libro registrado correctamente en el catálogo.', 'id' => $libro_id];
    } catch (PDOException $e) {
        $pdo->rollBack();
        if (str_contains($e->getMessage(), 'Duplicate')) {
            return ['ok' => false, 'msg' => 'El código de ejemplar ya existe en otro libro.'];
        }
        return ['ok' => false, 'msg' => 'No se pudo registrar el libro.'];
    }
}

function actualizar_libro(PDO $pdo, int $libro_id, array $datos, ?array $archivo_portada = null): array {
    $actual = obtener_libro($pdo, $libro_id);
    if (!$actual) return ['ok' => false, 'msg' => 'Libro no encontrado.'];

    $titulo = trim($datos['titulo'] ?? '');
    $autor_id = (int)($datos['autor_id'] ?? 0);
    $categoria_id = (int)($datos['categoria_id'] ?? 0);
    if ($titulo === '' || $autor_id <= 0 || $categoria_id <= 0) {
        return ['ok' => false, 'msg' => 'Completa título, autor y categoría.'];
    }

    $subida = procesar_portada_subida($archivo_portada);
    if (!$subida['ok']) {
        return $subida;
    }
    $portada = $subida['ruta'] ?: $actual['portada'];
    $anio = !empty($datos['anio_publicacion']) ? (int)$datos['anio_publicacion'] : null;

    $stmt = $pdo->prepare("
        UPDATE libros
        SET titulo = ?, isbn = ?, editorial = ?, anio_publicacion = ?, autor_id = ?, categoria_id = ?, sinopsis = ?, portada = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $titulo,
        trim($datos['isbn'] ?? '') ?: null,
        trim($datos['editorial'] ?? '') ?: null,
        $anio,
        $autor_id,
        $categoria_id,
        trim($datos['sinopsis'] ?? '') ?: null,
        $portada,
        $libro_id,
    ]);

    registrar_auditoria($pdo, 'EDITAR_LIBRO', "Actualizó el libro '{$titulo}' (ID {$libro_id}).");
    return ['ok' => true, 'msg' => 'Datos y portada del libro actualizados.'];
}

function agregar_ejemplar(PDO $pdo, int $libro_id, string $codigo): array {
    $codigo = strtoupper(trim($codigo));
    if ($codigo === '') {
        return ['ok' => false, 'msg' => 'Ingresa un código de ejemplar válido.'];
    }
    try {
        $stmt = $pdo->prepare("INSERT INTO ejemplares (libro_id, codigo_ejemplar, estado) VALUES (?, ?, 'disponible')");
        $stmt->execute([$libro_id, $codigo]);
        registrar_auditoria($pdo, 'NUEVO_EJEMPLAR', "Agregó copia {$codigo} al libro ID {$libro_id}.");
        return ['ok' => true, 'msg' => "Ejemplar {$codigo} agregado correctamente."];
    } catch (PDOException $e) {
        return ['ok' => false, 'msg' => "El código {$codigo} ya está registrado."];
    }
}

function eliminar_libro(PDO $pdo, int $libro_id): array {
    $stmt = $pdo->prepare("SELECT titulo, portada FROM libros WHERE id = ? AND estado = 1");
    $stmt->execute([$libro_id]);
    $libro = $stmt->fetch();
    if (!$libro) return ['ok' => false, 'msg' => 'El libro no existe o ya fue eliminado.'];

    $checkActivos = $pdo->prepare("SELECT COUNT(*) FROM ejemplares WHERE libro_id = ? AND estado = 'prestado'");
    $checkActivos->execute([$libro_id]);
    if ((int)$checkActivos->fetchColumn() > 0) {
        return ['ok' => false, 'msg' => 'No se puede eliminar este libro porque tiene ejemplares actualmente prestados.'];
    }

    $checkHistorial = $pdo->prepare("
        SELECT COUNT(*) FROM prestamos p
        JOIN ejemplares e ON e.id = p.ejemplar_id
        WHERE e.libro_id = ?
    ");
    $checkHistorial->execute([$libro_id]);
    $tieneHistorial = (int)$checkHistorial->fetchColumn() > 0;

    $pdo->beginTransaction();
    try {
        if ($tieneHistorial) {
            // Conserva integridad historica de devoluciones cerradas y retira del catalogo
            $pdo->prepare("UPDATE libros SET estado = 0 WHERE id = ?")->execute([$libro_id]);
            $pdo->prepare("UPDATE ejemplares SET estado = 'baja' WHERE libro_id = ?")->execute([$libro_id]);
            registrar_auditoria($pdo, 'ELIMINAR_LIBRO', "Retiró del catálogo el libro '{$libro['titulo']}' (conservando historial cerrado).");
            $pdo->commit();
            return ['ok' => true, 'msg' => "Libro '{$libro['titulo']}' eliminado del catálogo."];
        } else {
            // Eliminacion fisica completa cuando no tiene prestamos
            $pdo->prepare("DELETE FROM ejemplares WHERE libro_id = ?")->execute([$libro_id]);
            $pdo->prepare("DELETE FROM libros WHERE id = ?")->execute([$libro_id]);
            if (str_starts_with($libro['portada'], 'assets/img/portadas/')) {
                $rutaFisica = __DIR__ . '/../' . $libro['portada'];
                if (is_file($rutaFisica)) @unlink($rutaFisica);
            }
            registrar_auditoria($pdo, 'ELIMINAR_LIBRO', "Eliminó definitivamente el libro '{$libro['titulo']}' (ID {$libro_id}).");
            $pdo->commit();
            return ['ok' => true, 'msg' => "Libro '{$libro['titulo']}' eliminado definitivamente."];
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        return ['ok' => false, 'msg' => 'No se pudo eliminar el libro.'];
    }
}
