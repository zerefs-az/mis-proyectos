<?php
require_once __DIR__ . '/auth.php';

function procesar_foto_usuario(?array $archivo): array {
    if (!$archivo || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'ruta' => null];
    }
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'msg' => 'No se pudo subir la imagen de perfil. Intenta con otro archivo.'];
    }
    if ($archivo['size'] > 2 * 1024 * 1024) {
        return ['ok' => false, 'msg' => 'La foto de perfil supera el tamaño máximo permitido de 2 MB.'];
    }

    $info = @getimagesize($archivo['tmp_name']);
    $permitidos = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    if (!$info || !isset($permitidos[$info['mime']])) {
        return ['ok' => false, 'msg' => 'Formato de imagen no válido. Usa JPG, PNG o WebP.'];
    }

    $ext = $permitidos[$info['mime']];
    $dir = __DIR__ . '/../assets/img/avatares';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $nombreArchivo = 'avatar-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destino = $dir . '/' . $nombreArchivo;

    // Recorte proporcional 1:1 centrado (400x400) si GD está disponible
    $recortado = false;
    if (function_exists('imagecreatetruecolor')) {
        $src = match ($info['mime']) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($archivo['tmp_name']) : false,
            'image/png'  => function_exists('imagecreatefrompng')  ? @imagecreatefrompng($archivo['tmp_name'])  : false,
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($archivo['tmp_name']) : false,
            default      => false,
        };
        if ($src) {
            $w = imagesx($src);
            $h = imagesy($src);
            $lado = min($w, $h);
            $sx = (int)(($w - $lado) / 2);
            $sy = (int)(($h - $lado) / 2);
            $tam = 400;
            $dst = imagecreatetruecolor($tam, $tam);
            if ($ext === 'png' || $ext === 'webp') {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                $transp = imagecolorallocatealpha($dst, 255, 255, 255, 127);
                imagefilledrectangle($dst, 0, 0, $tam, $tam, $transp);
            }
            imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $tam, $tam, $lado, $lado);
            $recortado = match ($ext) {
                'jpg'  => imagejpeg($dst, $destino, 88),
                'png'  => imagepng($dst, $destino, 6),
                'webp' => function_exists('imagewebp') ? imagewebp($dst, $destino, 88) : false,
                default => false,
            };
            imagedestroy($src);
            imagedestroy($dst);
        }
    }

    if (!$recortado) {
        if (!move_uploaded_file($archivo['tmp_name'], $destino)) {
            return ['ok' => false, 'msg' => 'No se pudo guardar la foto de perfil en el servidor.'];
        }
    }

    return ['ok' => true, 'ruta' => 'assets/img/avatares/' . $nombreArchivo];
}

function eliminar_archivo_avatar(?string $ruta): void {
    if (!$ruta || !str_starts_with($ruta, 'assets/img/avatares/')) return;
    $abs = __DIR__ . '/../' . $ruta;
    if (is_file($abs)) {
        @unlink($abs);
    }
}

function obtener_usuario_por_id(PDO $pdo, int $id): ?array {
    asegurar_columna_foto($pdo);
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $u = $stmt->fetch();
    return $u ?: null;
}

function listar_usuarios(PDO $pdo, string $q = ''): array {
    asegurar_columna_foto($pdo);
    $sql = "
        SELECT u.*,
               (SELECT COUNT(*) FROM prestamos p WHERE p.lector_id = u.id AND p.estado IN ('activo','vencido')) AS prestamos_activos,
               (SELECT COALESCE(SUM(mora_monto), 0) FROM prestamos p WHERE p.lector_id = u.id AND p.mora_monto > 0 AND p.mora_pagada = 0) AS mora_pendiente
        FROM usuarios u
        WHERE 1=1
    ";
    $params = [];
    if ($q !== '') {
        $sql .= " AND (u.nombres LIKE ? OR u.apellidos LIKE ? OR u.dni LIKE ? OR u.correo LIKE ?)";
        $like = "%{$q}%";
        array_push($params, $like, $like, $like, $like);
    }
    $sql .= " ORDER BY u.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function crear_usuario(PDO $pdo, array $d, ?array $archivoFoto = null): array {
    asegurar_columna_foto($pdo);
    $nombres   = trim($d['nombres'] ?? '');
    $apellidos = trim($d['apellidos'] ?? '');
    $dni       = trim($d['dni'] ?? '');
    $correo    = strtolower(trim($d['correo'] ?? ''));
    $telefono  = trim($d['telefono'] ?? '');
    $clave     = $d['password'] ?? '123456';
    $rol       = $d['rol'] ?? 'lector';

    if (usuario_actual()['rol'] !== 'administrador') {
        $rol = 'lector';
    }
    if (!in_array($rol, ['administrador', 'bibliotecario', 'lector'], true)) {
        $rol = 'lector';
    }
    if ($nombres === '' || $apellidos === '' || $dni === '' || $correo === '') {
        return ['ok' => false, 'msg' => 'Completa nombres, apellidos, DNI y correo.'];
    }
    if (strlen($clave) < 6) {
        return ['ok' => false, 'msg' => 'La contraseña inicial debe tener al menos 6 caracteres.'];
    }

    $fotoRes = procesar_foto_usuario($archivoFoto);
    if (!$fotoRes['ok']) {
        return $fotoRes;
    }

    try {
        $hash = password_hash($clave, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO usuarios (nombres, apellidos, dni, correo, telefono, foto, password_hash, rol, estado)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([$nombres, $apellidos, $dni, $correo, $telefono ?: null, $fotoRes['ruta'], $hash, $rol]);
        registrar_auditoria($pdo, 'CREAR_USUARIO', "Registró al usuario {$nombres} {$apellidos} ({$rol}).");
        return ['ok' => true, 'msg' => "Usuario {$nombres} {$apellidos} registrado correctamente."];
    } catch (PDOException $e) {
        eliminar_archivo_avatar($fotoRes['ruta']);
        return ['ok' => false, 'msg' => 'El DNI o correo electrónico ya se encuentra registrado.'];
    }
}

function actualizar_perfil_propio(PDO $pdo, int $userId, array $d, ?array $archivoFoto = null): array {
    asegurar_columna_foto($pdo);
    $actual = obtener_usuario_por_id($pdo, $userId);
    if (!$actual) {
        return ['ok' => false, 'msg' => 'Usuario no encontrado.'];
    }

    $nombres   = trim($d['nombres'] ?? '');
    $apellidos = trim($d['apellidos'] ?? '');
    $correo    = strtolower(trim($d['correo'] ?? ''));
    $telefono  = trim($d['telefono'] ?? '');
    $quitarFoto = !empty($d['quitar_foto']);

    if ($nombres === '' || $apellidos === '' || $correo === '') {
        return ['ok' => false, 'msg' => 'Nombres, apellidos y correo electrónico son obligatorios.'];
    }
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'msg' => 'Ingresa un correo electrónico válido.'];
    }

    $nuevaFoto = $actual['foto'] ?? null;
    $subidaNueva = null;

    if ($quitarFoto) {
        $nuevaFoto = null;
    }
    if ($archivoFoto && ($archivoFoto['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $fotoRes = procesar_foto_usuario($archivoFoto);
        if (!$fotoRes['ok']) {
            return $fotoRes;
        }
        $subidaNueva = $fotoRes['ruta'];
        $nuevaFoto = $subidaNueva;
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE usuarios
            SET nombres = ?, apellidos = ?, correo = ?, telefono = ?, foto = ?
            WHERE id = ?
        ");
        $stmt->execute([$nombres, $apellidos, $correo, $telefono ?: null, $nuevaFoto, $userId]);

        if (($quitarFoto || $subidaNueva) && !empty($actual['foto']) && $actual['foto'] !== $nuevaFoto) {
            eliminar_archivo_avatar($actual['foto']);
        }

        $_SESSION['usuario']['nombres']   = $nombres;
        $_SESSION['usuario']['apellidos'] = $apellidos;
        $_SESSION['usuario']['correo']    = $correo;
        $_SESSION['usuario']['foto']      = $nuevaFoto;

        registrar_auditoria($pdo, 'EDITAR_PERFIL', "Actualizó su perfil personal ({$correo}).");
        return ['ok' => true, 'msg' => 'Tu perfil ha sido actualizado correctamente.'];
    } catch (PDOException $e) {
        eliminar_archivo_avatar($subidaNueva);
        return ['ok' => false, 'msg' => 'Ese correo electrónico ya está registrado por otro usuario.'];
    }
}

function cambiar_clave_propia(PDO $pdo, int $userId, string $claveActual, string $claveNueva, string $claveConf): array {
    $u = obtener_usuario_por_id($pdo, $userId);
    if (!$u) {
        return ['ok' => false, 'msg' => 'Usuario no encontrado.'];
    }
    if (!password_verify($claveActual, $u['password_hash'])) {
        return ['ok' => false, 'msg' => 'La contraseña actual es incorrecta.'];
    }
    if (strlen($claveNueva) < 6) {
        return ['ok' => false, 'msg' => 'La nueva contraseña debe tener al menos 6 caracteres.'];
    }
    if ($claveNueva !== $claveConf) {
        return ['ok' => false, 'msg' => 'La confirmación de la nueva contraseña no coincide.'];
    }

    $hash = password_hash($claveNueva, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ?")->execute([$hash, $userId]);
    registrar_auditoria($pdo, 'CAMBIAR_CLAVE', "Cambió su contraseña de acceso.");
    return ['ok' => true, 'msg' => 'Tu contraseña se actualizó correctamente.'];
}

function cambiar_estado_usuario(PDO $pdo, int $id): array {
    if ($id === (int)usuario_actual()['id']) {
        return ['ok' => false, 'msg' => 'No puedes desactivar tu propia cuenta.'];
    }
    $stmt = $pdo->prepare("SELECT nombres, apellidos, estado FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    $u = $stmt->fetch();
    if (!$u) return ['ok' => false, 'msg' => 'Usuario no encontrado.'];

    $nuevo = $u['estado'] ? 0 : 1;
    $pdo->prepare("UPDATE usuarios SET estado = ? WHERE id = ?")->execute([$nuevo, $id]);
    $txt = $nuevo ? 'activó' : 'desactivó';
    registrar_auditoria($pdo, 'ESTADO_USUARIO', "Se {$txt} al usuario {$u['nombres']} {$u['apellidos']}.");
    return ['ok' => true, 'msg' => "Usuario {$u['nombres']} {$u['apellidos']} " . ($nuevo ? 'activado.' : 'desactivado.')];
}

function eliminar_usuario(PDO $pdo, int $id): array {
    if (usuario_actual()['rol'] !== 'administrador') {
        return ['ok' => false, 'msg' => 'Solo el administrador puede eliminar usuarios.'];
    }
    if ($id === (int)usuario_actual()['id']) {
        return ['ok' => false, 'msg' => 'No puedes eliminar tu propia cuenta activa.'];
    }

    $stmt = $pdo->prepare("SELECT id, nombres, apellidos, rol, foto FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    $u = $stmt->fetch();
    if (!$u) return ['ok' => false, 'msg' => 'Usuario no encontrado.'];

    $activos = $pdo->prepare("SELECT COUNT(*) FROM prestamos WHERE lector_id = ? AND estado IN ('activo','vencido')");
    $activos->execute([$id]);
    if ((int)$activos->fetchColumn() > 0) {
        return ['ok' => false, 'msg' => 'No se puede eliminar este usuario porque tiene préstamos activos o vencidos.'];
    }

    $moras = $pdo->prepare("SELECT COALESCE(SUM(mora_monto), 0) FROM prestamos WHERE lector_id = ? AND mora_monto > 0 AND mora_pagada = 0");
    $moras->execute([$id]);
    if ((float)$moras->fetchColumn() > 0) {
        return ['ok' => false, 'msg' => 'No se puede eliminar este usuario porque tiene moras pendientes de pago.'];
    }

    $esBiblioConPrestamos = $pdo->prepare("SELECT COUNT(*) FROM prestamos WHERE bibliotecario_id = ?");
    $esBiblioConPrestamos->execute([$id]);
    if ((int)$esBiblioConPrestamos->fetchColumn() > 0) {
        return ['ok' => false, 'msg' => 'No se puede eliminar este bibliotecario porque registró préstamos en el sistema. Puedes desactivarlo en su lugar.'];
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM prestamos WHERE lector_id = ? AND estado = 'devuelto'")->execute([$id]);
        $pdo->prepare("UPDATE auditoria SET usuario_id = NULL WHERE usuario_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$id]);
        eliminar_archivo_avatar($u['foto'] ?? null);
        registrar_auditoria($pdo, 'ELIMINAR_USUARIO', "Eliminó al usuario {$u['nombres']} {$u['apellidos']} ({$u['rol']}).");
        $pdo->commit();
        return ['ok' => true, 'msg' => "Usuario {$u['nombres']} {$u['apellidos']} eliminado correctamente."];
    } catch (PDOException $e) {
        $pdo->rollBack();
        return ['ok' => false, 'msg' => 'No se pudo eliminar el usuario por registros vinculados.'];
    }
}

function actualizar_usuario_admin(PDO $pdo, int $id, array $d, ?array $archivoFoto = null): array {
    asegurar_columna_foto($pdo);
    $actualSesion = usuario_actual();
    $esAdmin = ($actualSesion['rol'] ?? '') === 'administrador';

    $objetivo = obtener_usuario_por_id($pdo, $id);
    if (!$objetivo) {
        return ['ok' => false, 'msg' => 'Usuario no encontrado.'];
    }

    if (!$esAdmin && $objetivo['rol'] !== 'lector') {
        return ['ok' => false, 'msg' => 'Solo el administrador puede editar cuentas del personal.'];
    }

    $nombres    = trim($d['nombres'] ?? '');
    $apellidos  = trim($d['apellidos'] ?? '');
    $dni        = trim($d['dni'] ?? '');
    $correo     = strtolower(trim($d['correo'] ?? ''));
    $telefono   = trim($d['telefono'] ?? '');
    $nuevaClave = $d['password'] ?? '';
    $quitarFoto = !empty($d['quitar_foto']);

    if ($nombres === '' || $apellidos === '' || $dni === '' || $correo === '') {
        return ['ok' => false, 'msg' => 'Completa nombres, apellidos, DNI y correo electrónico.'];
    }
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'msg' => 'Ingresa un correo electrónico válido.'];
    }

    $rol = $objetivo['rol'];
    if ($esAdmin && $id !== (int)$actualSesion['id']) {
        $rolSolicitado = $d['rol'] ?? $rol;
        if (in_array($rolSolicitado, ['administrador', 'bibliotecario', 'lector'], true)) {
            $rol = $rolSolicitado;
        }
    }

    $cambiaClave = ($esAdmin && $nuevaClave !== '');
    if ($cambiaClave && strlen($nuevaClave) < 6) {
        return ['ok' => false, 'msg' => 'La nueva contraseña debe tener al menos 6 caracteres.'];
    }

    $nuevaFoto = $objetivo['foto'] ?? null;
    $subidaNueva = null;
    if ($quitarFoto) {
        $nuevaFoto = null;
    }
    if ($archivoFoto && ($archivoFoto['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $fotoRes = procesar_foto_usuario($archivoFoto);
        if (!$fotoRes['ok']) {
            return $fotoRes;
        }
        $subidaNueva = $fotoRes['ruta'];
        $nuevaFoto = $subidaNueva;
    }

    try {
        if ($cambiaClave) {
            $hash = password_hash($nuevaClave, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                UPDATE usuarios
                SET nombres = ?, apellidos = ?, dni = ?, correo = ?, telefono = ?, foto = ?, rol = ?, password_hash = ?
                WHERE id = ?
            ");
            $stmt->execute([$nombres, $apellidos, $dni, $correo, $telefono ?: null, $nuevaFoto, $rol, $hash, $id]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE usuarios
                SET nombres = ?, apellidos = ?, dni = ?, correo = ?, telefono = ?, foto = ?, rol = ?
                WHERE id = ?
            ");
            $stmt->execute([$nombres, $apellidos, $dni, $correo, $telefono ?: null, $nuevaFoto, $rol, $id]);
        }

        if (($quitarFoto || $subidaNueva) && !empty($objetivo['foto']) && $objetivo['foto'] !== $nuevaFoto) {
            eliminar_archivo_avatar($objetivo['foto']);
        }

        // Sincronizar sesión si el administrador editó su propia cuenta
        if ($id === (int)$actualSesion['id']) {
            $_SESSION['usuario']['nombres']   = $nombres;
            $_SESSION['usuario']['apellidos'] = $apellidos;
            $_SESSION['usuario']['correo']    = $correo;
            $_SESSION['usuario']['foto']      = $nuevaFoto;
        }

        if ($cambiaClave) {
            registrar_auditoria($pdo, 'RESTABLECER_CLAVE', "Editó perfil y restableció la contraseña de {$nombres} {$apellidos} ({$correo}).");
            return ['ok' => true, 'msg' => "Perfil de {$nombres} {$apellidos} actualizado y nueva contraseña asignada correctamente."];
        }
        registrar_auditoria($pdo, 'EDITAR_USUARIO', "Actualizó los datos del usuario {$nombres} {$apellidos} ({$rol}).");
        return ['ok' => true, 'msg' => "Perfil de {$nombres} {$apellidos} actualizado correctamente."];
    } catch (PDOException $e) {
        eliminar_archivo_avatar($subidaNueva);
        return ['ok' => false, 'msg' => 'El DNI o correo electrónico ya pertenece a otro usuario.'];
    }
}
