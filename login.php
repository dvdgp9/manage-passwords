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
  <title>Iniciar sesión</title>
  <link href='https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700&display=swap' rel='stylesheet'>
  <link rel="stylesheet" href="style.css">
  <meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
  </head>
<body>
  <main class="page auth-page">
    <img src="https://ebone.es/wp-content/uploads/2024/11/Logo-Grupo-Lineas-cuadrado-1500px.png" alt="Logo Grupo Ebone" class="auth-logo">

    <section class="auth-card">
      <h1>Iniciar sesión</h1>

      <?php if ($error): ?>
        <div class="alert-error">
          <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <form class="auth-form" method="post" action="login.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

        <label for="email">Email</label>
        <input type="email" id="email" name="email" autocomplete="username" required>

        <label for="password">Contraseña</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>

        <label class="auth-remember">
          <input type="checkbox" name="remember" checked>
          Recuérdame 60 días
        </label>

        <div class="auth-actions">
          <button type="submit" class="btn-primary" style="width:100%">Entrar</button>
        </div>
      </form>
    </section>
  </main>
</body>
</html>
