<?php
// tools/rotate_encryption_key_web.php
// Web interface for encryption key rotation (for Plesk without SSH)
// Usage: Upload to server, access via browser, run rotation, then DELETE this file

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$root = dirname(__DIR__);
require_once $root . '/config.php';

function decrypt_with_key(string $data, string $method, string $key): string|false {
    $decoded = base64_decode($data, true);
    if ($decoded === false) return false;
    $parts = explode('::', $decoded, 2);
    if (count($parts) !== 2) return false;
    [$encrypted, $iv] = $parts;
    return openssl_decrypt($encrypted, $method, $key, 0, $iv);
}

function encrypt_with_key(string $data, string $method, string $key): string|false {
    $ivLen = openssl_cipher_iv_length($method);
    if ($ivLen === false) return false;
    $iv = random_bytes($ivLen);
    $encrypted = openssl_encrypt($data, $method, $key, 0, $iv);
    if ($encrypted === false) return false;
    return base64_encode($encrypted . '::' . $iv);
}

$method = defined('ENCRYPTION_METHOD') ? ENCRYPTION_METHOD : 'AES-256-CBC';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldKey = $_POST['old_key'] ?? '';
    $newKey = $_POST['new_key'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    $dryRun = isset($_POST['dry_run']);
    
    if ($confirm !== 'ROTAR') {
        $message = "ERROR: Debes escribir 'ROTAR' para confirmar.";
    } elseif (empty($oldKey) || empty($newKey)) {
        $message = "ERROR: Ambas claves son obligatorias.";
    } else {
        try {
            $pdo = getDBConnection();
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            if (!$dryRun) {
                $pdo->beginTransaction();
            }
            
            $select = $pdo->query("SELECT id, `password` FROM `passwords_manager` ORDER BY id ASC");
            $rows = $select->fetchAll(PDO::FETCH_ASSOC);
            
            $total = count($rows);
            $updated = 0;
            $skipped = 0;
            $failed = 0;
            
            $updateStmt = $pdo->prepare("UPDATE `passwords_manager` SET `password` = :pwd WHERE id = :id");
            
            foreach ($rows as $row) {
                $id = (int)$row['id'];
                $enc = (string)$row['password'];
                $plain = decrypt_with_key($enc, $method, $oldKey);
                if ($plain === false || $plain === '') {
                    $skipped++;
                    continue;
                }
                $reenc = encrypt_with_key($plain, $method, $newKey);
                if ($reenc === false) {
                    $failed++;
                    continue;
                }
                if (!$dryRun) {
                    $updateStmt->execute([':pwd' => $reenc, ':id' => $id]);
                }
                $updated++;
            }
            
            if ($dryRun) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $message = "DRY-RUN completado:\nTotal: {$total}\nActualizables: {$updated}\nOmitidos: {$skipped}\nFallidos: {$failed}";
            } else {
                $pdo->commit();
                $message = "ROTACIÓN COMPLETADA:\nTotal: {$total}\nActualizados: {$updated}\nOmitidos: {$skipped}\nFallidos: {$failed}\n\n¡AHORA CAMBIA EL .env A LA NUEVA CLAVE!";
            }
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = "ERROR: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rotación de Clave de Cifrado</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; margin: 20px 0; border-radius: 5px; }
        input[type="text"], input[type="password"] { width: 100%; padding: 8px; margin: 5px 0; box-sizing: border-box; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 5px; }
        button:hover { background: #0056b3; }
        .danger { background: #dc3545; }
        .danger:hover { background: #c82333; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; white-space: pre-wrap; }
    </style>
</head>
<body>
    <h1>🔐 Rotación de Clave de Cifrado</h1>
    
    <div class="warning">
        <strong>⚠️ IMPORTANTE:</strong>
        <ul>
            <li>Haz backup de la base de datos antes de proceder</li>
            <li>Usa primero "Dry Run" para verificar</li>
            <li>Después de la rotación, actualiza el .env con la nueva clave</li>
            <li>ELIMINA este archivo del servidor tras usarlo</li>
        </ul>
    </div>

    <?php if ($message): ?>
        <div class="<?= strpos($message, 'ERROR') === 0 ? 'error' : 'success' ?>">
            <pre><?= htmlspecialchars($message) ?></pre>
        </div>
    <?php endif; ?>

    <form method="post">
        <h3>Claves de Cifrado</h3>
        <label>Clave Antigua:</label>
        <input type="password" name="old_key" value="<?= htmlspecialchars($_POST['old_key'] ?? '') ?>" required>
        
        <label>Clave Nueva:</label>
        <input type="password" name="new_key" value="<?= htmlspecialchars($_POST['new_key'] ?? '') ?>" required>
        
        <h3>Confirmación</h3>
        <label>Escribe "ROTAR" para confirmar:</label>
        <input type="text" name="confirm" placeholder="ROTAR" required>
        
        <h3>Opciones</h3>
        <label>
            <input type="checkbox" name="dry_run" <?= isset($_POST['dry_run']) ? 'checked' : '' ?>>
            Dry Run (solo simular, no aplicar cambios)
        </label>
        
        <br><br>
        <button type="submit">🔄 Ejecutar Rotación</button>
    </form>

    <div class="warning">
        <strong>Método actual:</strong> <?= htmlspecialchars($method) ?><br>
        <strong>Tabla:</strong> passwords_manager
    </div>
</body>
</html>
