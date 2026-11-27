<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

function h(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf_token'];
}

function verificar_csrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        die('Token de seguridad inválido. Recarga la página e intenta nuevamente.');
    }
}

function usuario_actual(): ?array {
    return $_SESSION['usuario'] ?? null;
}

function exigir_login(): void {
    if (!usuario_actual()) {
        header('Location: index.php');
        exit;
    }
}

function exigir_rol(array $roles): void {
    exigir_login();
    if (!in_array(usuario_actual()['rol'], $roles, true)) {
        header('Location: catalogo.php?msg=sin_permiso');
        exit;
    }
}

function registrar_auditoria(PDO $pdo, string $accion, string $detalle): void {
    $uid = usuario_actual()['id'] ?? null;
    $stmt = $pdo->prepare('INSERT INTO auditoria (usuario_id, accion, detalle) VALUES (?, ?, ?)');
    $stmt->execute([$uid, $accion, mb_substr($detalle, 0, 250)]);
}

function obtener_config(PDO $pdo): array {
    $cfg = $pdo->query('SELECT * FROM configuracion LIMIT 1')->fetch();
    return $cfg ?: ['dias_prestamo' => 7, 'tarifa_mora_diaria' => 1.50, 'max_renovaciones' => 2, 'moneda' => 'S/'];
}

function flash_set(string $tipo, string $texto): void {
    $_SESSION['flash'] = ['tipo' => $tipo, 'texto' => $texto];
}

function flash_get(): ?array {
    if (!isset($_SESSION['flash'])) return null;
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

function etiqueta_rol(string $rol): string {
    return match ($rol) {
        'administrador' => 'Administrador',
        'bibliotecario' => 'Bibliotecario',
        default         => 'Lector',
    };
}

function asegurar_columna_foto(PDO $pdo): void {
    static $verificado = false;
    if ($verificado) return;
    $verificado = true;
    try {
        $col = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'foto'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE usuarios ADD COLUMN foto VARCHAR(255) DEFAULT NULL AFTER telefono");
        }
    } catch (Throwable $e) {
        // Ignorar si no tiene permisos ALTER
    }
}
