<?php
// login.php
require_once 'security.php';
require_once 'config.php';

bootstrap_security(false);
$csrf = ensure_csrf_token();

// If already logged in, go to list
if (current_user()) {
    header('Location: ver-passwords.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_from_request();

    $email = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $remember = isset($_POST['remember']);

    if ($email === '' || $password === '') {
        $error = 'Email y contraseña son obligatorios';
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare('SELECT id, email, password_hash, role FROM users WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $email]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($u && password_verify($password, $u['password_hash'])) {
                $_SESSION['user_id'] = (int)$u['id'];
                $_SESSION['authenticated'] = true;
                regenerate_session_id();
                if ($remember) {
                    issue_remember_token((int)$u['id']);
                }
                header('Location: ver-passwords.php');
                exit;
            } else {
                $error = 'Credenciales inválidas';
            }
        } catch (Throwable $e) {
            $error = 'Error interno';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestor de Contraseñas · Grupo Ebone</title>
  <link href='https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap' rel='stylesheet'>
  <link rel="stylesheet" href="style.css">
  <meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
  <main class="login-container">
    <div class="login-left">
      <div class="login-brand">
        <img src="https://ebone.es/wp-content/uploads/2024/11/Logo-Grupo-Lineas-cuadrado-1500px.png" alt="Logo Grupo Ebone" class="login-brand-logo">
      </div>
      
      <div class="login-welcome">
        <h1>Gestor de Contraseñas</h1>
        <p class="login-subtitle">Accede de forma segura a todas las credenciales del equipo</p>
      </div>

      <div class="login-features">
        <div class="feature-item">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
          </svg>
          <div>
            <h3>Seguridad total</h3>
            <p>Todas las contraseñas están encriptadas y protegidas</p>
          </div>
        </div>
        
        <div class="feature-item">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
          </svg>
          <div>
            <h3>Colaboración por equipos</h3>
            <p>Comparte credenciales con usuarios o departamentos completos</p>
          </div>
        </div>
        
        <div class="feature-item">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <polyline points="12 6 12 12 16 14"></polyline>
          </svg>
          <div>
            <h3>Acceso rápido y organizado</h3>
            <p>Encuentra cualquier contraseña en segundos con búsqueda inteligente</p>
          </div>
        </div>
      </div>
    </div>

    <div class="login-right">
      <div class="login-card">
        <h2>Iniciar sesión</h2>
        <p class="login-card-subtitle">Accede a tu cuenta para gestionar contraseñas</p>

        <?php if ($error): ?>
          <div class="alert-error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
          </div>
        <?php endif; ?>

        <form class="auth-form" method="post" action="login.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

          <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" autocomplete="username" placeholder="tu@email.com" required autofocus>
          </div>

          <div class="form-group">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" autocomplete="current-password" placeholder="••••••••" required>
          </div>

          <label class="auth-remember">
            <input type="checkbox" name="remember" checked>
            <span>Recuérdame durante 60 días</span>
          </label>

          <button type="submit" class="btn-primary btn-login">Entrar</button>
        </form>
      </div>
    </div>
  </main>
</body>
</html>
