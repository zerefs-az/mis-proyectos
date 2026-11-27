<?php
require_once __DIR__ . '/auth.php';

function render_head(string $titulo = 'SIBI — Biblioteca'): void {
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($titulo) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Lora:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="icon" type="image/svg+xml" href="assets/img/sibi-icono-color.svg">
  <link rel="stylesheet" href="css/styles.css">
</head>
<body>
<?php
}

function render_topbar(string $activa = 'catalogo'): void {
    $u = usuario_actual();
    $rol = $u['rol'] ?? 'lector';
    $inicial = strtoupper(mb_substr($u['nombres'] ?? 'U', 0, 1));
    $foto = $u['foto'] ?? '';
?>
<header class="topbar">
  <div class="topbar-inner">
    <a href="catalogo.php" class="brand" aria-label="SIBI Inicio">
      <img src="assets/img/sibi-logo-color.svg" alt="SIBI" class="brand-logo">
    </a>

    <nav class="topnav">
      <a href="catalogo.php" class="<?= $activa === 'catalogo' ? 'active' : '' ?>">Catálogo</a>
      <?php if (in_array($rol, ['administrador', 'bibliotecario'], true)): ?>
        <a href="mostrador.php" class="<?= $activa === 'mostrador' ? 'active' : '' ?>">Mostrador</a>
        <a href="catalogo_admin.php" class="<?= $activa === 'libros' ? 'active' : '' ?>">Libros</a>
        <a href="lectores.php" class="<?= $activa === 'lectores' ? 'active' : '' ?>">Lectores</a>
      <?php endif; ?>
      <?php if ($rol === 'administrador'): ?>
        <a href="panel.php" class="<?= $activa === 'panel' ? 'active' : '' ?>">Panel Admin</a>
      <?php endif; ?>
    </nav>

    <div class="user-menu">
      <a href="perfil.php" class="user-chip <?= $activa === 'perfil' ? 'active' : '' ?>" title="Editar perfil y foto">
        <span class="user-avatar-wrap">
          <span class="user-avatar">
            <?php if ($foto): ?>
              <img src="<?= h($foto) ?>" alt="<?= h($u['nombres'] ?? 'Usuario') ?>" class="user-avatar-img">
            <?php else: ?>
              <?= h($inicial) ?>
            <?php endif; ?>
          </span>
          <span class="avatar-edit-badge" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 20h9"/>
              <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
            </svg>
          </span>
        </span>
        <div class="user-meta">
          <strong><?= h(($u['nombres'] ?? '') . ' ' . ($u['apellidos'] ?? '')) ?></strong>
          <span><?= h(etiqueta_rol($rol)) ?></span>
        </div>
      </a>
      <a href="logout.php" class="btn-logout">Salir</a>
    </div>
  </div>
</header>
<?php
    $flash = flash_get();
    if ($flash):
?>
<div class="container">
  <div class="alert alert-<?= h($flash['tipo']) ?>"><?= h($flash['texto']) ?></div>
</div>
<?php
    endif;
}

function render_footer(): void {
?>
<script src="js/app.js"></script>
</body>
</html>
<?php
}
