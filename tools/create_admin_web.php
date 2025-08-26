<?php
// tools/create_admin_web.php
// Web helper para crear un usuario admin inicial sin SSH.
// 1) Sube este archivo al servidor
// 2) Accede por navegador, rellena email y contraseña
// 3) Crea el usuario
// 4) ELIMINA este archivo del servidor

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$root = dirname(__DIR__);
require_once $root . '/config.php'; // getDBConnection()

$message = '';
$ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $role = (string)($_POST['role'] ?? 'admin');

    if ($email === '' || $password === '') {
        $message = 'Email y contraseña son obligatorios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Email no válido.';
    } elseif (!in_array($role, ['admin','editor','lector'], true)) {
        $message = 'Rol no válido.';
    } else {
        try {
            $pdo = getDBConnection();
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // ¿Existe ya?
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
            $stmt->execute([':email' => $email]);
            $exists = $stmt->fetchColumn();

            if ($exists) {
                $message = 'Ya existe un usuario con ese email.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                if ($hash === false) {
                    throw new RuntimeException('No se pudo generar el hash de la contraseña.');
                }
                $ins = $pdo->prepare('INSERT INTO users (email, password_hash, role) VALUES (:email, :hash, :role)');
                $ins->execute([':email' => $email, ':hash' => $hash, ':role' => $role]);
                $ok = true;
                $message = 'Usuario creado correctamente. Por favor, ELIMINA este archivo ahora.';
            }
        } catch (Throwable $e) {
            $message = 'ERROR: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Crear usuario admin</title>
  <style>
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, Noto Sans, sans-serif; max-width: 560px; margin: 40px auto; padding: 0 16px; }
    .card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; }
    .ok { background: #d1fae5; border: 1px solid #10b981; padding: 10px; border-radius: 6px; }
    .err { background: #fee2e2; border: 1px solid #ef4444; padding: 10px; border-radius: 6px; }
    label { display: block; margin-top: 10px; font-weight: 600; }
    input, select { width: 100%; padding: 8px; margin-top: 6px; box-sizing: border-box; }
    button { margin-top: 14px; background: #111827; color: #fff; border: 0; padding: 10px 14px; border-radius: 6px; cursor: pointer; }
    .danger { background: #b91c1c; }
  </style>
</head>
<body>
  <h1>Crear usuario admin inicial</h1>
  <p class="card">Tras crear el usuario, <strong>ELIMINA</strong> este archivo <code>tools/create_admin_web.php</code> por seguridad.</p>

  <?php if ($message !== ''): ?>
    <div class="<?= $ok ? 'ok' : 'err' ?>">
      <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <form method="post" class="card">
    <label>Email</label>
    <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? 'it@ebone.es', ENT_QUOTES, 'UTF-8') ?>" required>

    <label>Contraseña</label>
    <input type="password" name="password" value="<?= htmlspecialchars($_POST['password'] ?? 'Cacaperr1', ENT_QUOTES, 'UTF-8') ?>" required>

    <label>Rol</label>
    <select name="role">
      <option value="admin" selected>admin</option>
      <option value="editor">editor</option>
      <option value="lector">lector</option>
    </select>

    <button type="submit">Crear usuario</button>
  </form>

  <p style="margin-top:16px;">Ruta esperada en el servidor: <code>/tools/create_admin_web.php</code></p>
</body>
</html>
