<?php
// Reusable header include
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/config.php';

// Assume bootstrap_security() already called by the page.
$csrf = ensure_csrf_token();
$user = current_user();

// Determine active page for navigation
$currentPage = basename($_SERVER['PHP_SELF']);
$isVerPage = in_array($currentPage, ['ver-passwords.php', 'edit-password.php']);
$isIntroducirPage = $currentPage === 'introducir.php';
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
      <a class="nav-link<?= $isVerPage ? ' active' : '' ?>" href="ver-passwords.php">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
          <circle cx="12" cy="12" r="3"></circle>
        </svg>
        Ver
      </a>
      <a class="nav-link<?= $isIntroducirPage ? ' active' : '' ?>" href="introducir.php">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="12" y1="5" x2="12" y2="19"></line>
          <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
        Introducir
      </a>
    </nav>
    <div class="site-header__user">
      <?php if ($user): ?>
        <div class="user-info">
          <div class="user-avatar"><?= strtoupper(substr($user['email'] ?? 'U', 0, 1)) ?></div>
          <span class="user-label"><?= htmlspecialchars(($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?><?= isset($user['role']) && $user['role'] === 'admin' ? ' · Admin' : '' ?></span>
        </div>
        <form action="logout.php" method="post" class="logout-form">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
          <button type="submit" class="logout-btn" title="Cerrar sesión">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
              <polyline points="16,17 21,12 16,7"></polyline>
              <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
            Salir
          </button>
        </form>
      <?php else: ?>
        <a class="nav-link" href="login.php">Entrar</a>
      <?php endif; ?>
    </div>
  </div>
</header>
