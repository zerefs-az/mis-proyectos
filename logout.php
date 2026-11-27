<?php
require_once __DIR__ . '/php/auth.php';
if (usuario_actual()) {
    registrar_auditoria($pdo, 'LOGOUT', 'Cerró sesión.');
}
session_destroy();
header('Location: index.php');
exit;
