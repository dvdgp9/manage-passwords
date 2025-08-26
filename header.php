<?php
// Reusable header include
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/config.php';

// Assume bootstrap_security() already called by the page.
$csrf = ensure_csrf_token();
$user = current_user();
?>
<header class="site-header">
  <div class="site-header__inner">
    <div class="site-header__brand">
      <a href="ver-passwords.php" class="brand-link">
        <img src="https://ebone.es/wp-content/uploads/2024/11/Logo-Grupo-Lineas-cuadrado-1500px.png" alt="Grupo Ebone" class="brand-logo">
        <span class="brand-title">Gestor de Contraseñas</span>
      </a>
    </div>
    <nav class="site-header__nav">
      <a class="nav-link" href="ver-passwords.php">Ver</a>
      <a class="nav-link" href="introducir.php">Introducir</a>
    </nav>
    <div class="site-header__user">
      <?php if ($user): ?>
        <span class="user-label"><?= htmlspecialchars(($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?><?= isset($user['role']) ? ' · ' . htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8') : '' ?></span>
        <form action="logout.php" method="post" class="logout-form">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
          <button type="submit" class="logout-btn">Cerrar sesión</button>
        </form>
      <?php else: ?>
        <a class="nav-link" href="login.php">Entrar</a>
      <?php endif; ?>
    </div>
  </div>
</header>
