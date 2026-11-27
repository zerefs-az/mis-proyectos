<?php
require_once __DIR__ . '/php/auth.php';

if ($actual = usuario_actual()) {
    header('Location: ' . ($actual['rol'] === 'administrador' ? 'panel.php' : 'catalogo.php'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $correo = strtolower(trim($_POST['correo'] ?? ''));
    $clave  = $_POST['password'] ?? '';

    asegurar_columna_foto($pdo);
    $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE correo = ? LIMIT 1');
    $stmt->execute([$correo]);
    $u = $stmt->fetch();

    if (!$u || !password_verify($clave, $u['password_hash'])) {
        $error = 'Correo electrónico o contraseña incorrectos.';
    } elseif (!$u['estado']) {
        $error = 'Tu cuenta se encuentra desactivada. Comunícate con la biblioteca.';
    } else {
        session_regenerate_id(true);
        $_SESSION['usuario'] = [
            'id'        => (int)$u['id'],
            'nombres'   => $u['nombres'],
            'apellidos' => $u['apellidos'],
            'correo'    => $u['correo'],
            'rol'       => $u['rol'],
            'foto'      => $u['foto'] ?? null,
        ];
        registrar_auditoria($pdo, 'LOGIN', "Inicio de sesión de {$u['correo']}.");
        header('Location: ' . ($u['rol'] === 'administrador' ? 'panel.php' : 'catalogo.php'));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Iniciar sesión — SIBI Biblioteca</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Lora:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="icon" type="image/svg+xml" href="assets/img/sibi-icono-color.svg">
  <link rel="stylesheet" href="css/styles.css">
</head>
<body>
<main class="login-page">
  <section class="login-visual">
    <div class="login-visual-top">
      <img src="assets/img/logo-recoleta-blanco.svg" alt="IEST La Recoleta" class="login-inst-logo">
      <span class="login-inst-pill">IEST La Recoleta · Arequipa</span>
    </div>
    <div class="login-visual-body">
      <h1>SIBI</h1>
      <div class="login-visual-accent"></div>
      <p>Sistema de Gestión Bibliotecaria para consulta de catálogo, circulación en mostrador y control de ejemplares.</p>
    </div>
    <div class="login-visual-foot">
      Biblioteca Institucional · Plataforma Web
    </div>
  </section>

  <section class="login-form-panel">
    <div class="login-card">
      <img src="assets/img/sibi-icono-color.svg" alt="SIBI" class="login-card-icon">
      <h2>Iniciar sesión</h2>
      <p class="subtitle">Ingresa tus credenciales institucionales para acceder.</p>

      <?php if ($error): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
      <?php endif; ?>

      <form method="post" autocomplete="on">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <div class="field">
          <label for="correo">Correo electrónico</label>
          <input class="input" type="email" id="correo" name="correo" required placeholder="usuario@sibi.edu.pe" value="<?= h($_POST['correo'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="password">Contraseña</label>
          <div class="pass-wrap">
            <input class="input" type="password" id="password" name="password" required placeholder="••••••••">
            <button type="button" class="pass-toggle" data-toggle-pass="password">Ver</button>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-block" style="margin-top:8px;">Ingresar</button>
      </form>
    </div>
  </section>
</main>
<script src="js/app.js"></script>
</body>
</html>
