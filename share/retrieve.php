<?php
require 'includes/db.php';

// Use the encryption key from config.php
$encryption_key = ENCRYPTION_KEY;

$message = '';
$decrypted_password = null;
$remaining_uses = 0;
$is_last_use = false;

if (isset($_GET['hash'])) {
    $link_hash = $_GET['hash'];

    try {
        // Retrieve the password, IV, and usage info from the database
        $stmt = $pdo->prepare("
            SELECT password, iv, max_retrievals, retrieval_count, title
            FROM passwords
            WHERE link_hash = ?
              AND created_at >= NOW() - INTERVAL 7 DAY
        ");
        $stmt->execute([$link_hash]);
        $row = $stmt->fetch();

        if ($row) {
            $max_retrievals = (int)$row['max_retrievals'];
            $retrieval_count = (int)$row['retrieval_count'];
            $title = $row['title'] ?? '';
            
            // Check if there are remaining uses
            if ($retrieval_count >= $max_retrievals) {
                $message = "Este enlace ya ha alcanzado el número máximo de usos permitidos.";
            } elseif (isset($_POST['confirm'])) {
                // If the user confirms, decrypt the password
                $iv = base64_decode($row['iv']); // Decode the IV from base64
                $decrypted_password = openssl_decrypt(
                    $row['password'],        // Encrypted text
                    'AES-256-CBC',          // Cipher method
                    $encryption_key,        // Encryption key
                    0,                      // Options (0 for default)
                    $iv                     // Initialization vector
                );

                // Increment the retrieval count
                $new_count = $retrieval_count + 1;
                
                if ($new_count >= $max_retrievals) {
                    // This was the last use, delete the record
                    $stmt = $pdo->prepare("DELETE FROM passwords WHERE link_hash = ?");
                    $stmt->execute([$link_hash]);
                    $remaining_uses = 0;
                    $is_last_use = true;
                } else {
                    // Update the counter
                    $stmt = $pdo->prepare("UPDATE passwords SET retrieval_count = ? WHERE link_hash = ?");
                    $stmt->execute([$new_count, $link_hash]);
                    $remaining_uses = $max_retrievals - $new_count;
                }

                // Prepare the success message
                $message = "Tu información es:";
            } else {
                // Calculate remaining uses for the confirmation page
                $remaining_uses = $max_retrievals - $retrieval_count;
                $is_last_use = ($remaining_uses === 1);
                
                // Build confirmation message
                if ($remaining_uses === 1) {
                    $usage_msg = "Este enlace solo se puede usar <strong>1 vez más</strong>. Después de consultarlo, será eliminado.";
                } else {
                    $usage_msg = "Este enlace se puede usar <strong>" . $remaining_uses . " veces más</strong>.";
                }
                
                // Show the confirmation page
                $title_display = !empty($title) ? "<p><strong>Título:</strong> " . htmlspecialchars($title) . "</p>" : "";
                
                echo "<!DOCTYPE html>
                <html lang='es'>
                <head>
                    <meta charset='UTF-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                    <title>Recuperar Información</title>
                    <link href='https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap' rel='stylesheet'>
                    <link href='styles.css' rel='stylesheet'>
                    <link rel='icon' type='image/png' href='favicon.png'>
                </head>
                <body>
                    <div class='container'>
                        <img src='https://ebone.es/wp-content/uploads/2024/11/Logo-Grupo-Lineas-cuadrado-1500px.png' alt='Logo Grupo Ebone' class='logo'>
                        <h1>Recuperar Información</h1>
                        " . $title_display . "
                        <div class='remaining-uses" . ($is_last_use ? " last-use" : "") . "'>" . $usage_msg . "</div>
                        <p>¿Estás segura/o de que deseas recuperar la información?</p>
                        <form method='POST'>
                            <button type='submit' name='confirm' class='confirm-button'>Sí, mostrar información</button>
                        </form>
                        <div class='footer'>
                            © 2025 Grupo Ebone. Todos los derechos reservados.
                        </div>
                    </div>
                </body>
                </html>";
                exit;
            }
        } else {
            $message = "El enlace no es válido o ha expirado.";
        }
    } catch (PDOException $e) {
        $message = "Ocurrió algún error al recuperar la información: " . $e->getMessage();
    }
} else {
    header("Location: index.html");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Información</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="favicon.png">
    <style>
        .password-content {
            white-space: pre-wrap;
            word-wrap: break-word;
            text-align: left;
            font-family: 'Courier New', monospace;
            background-color: #f5f5f5;
            padding: 1rem;
            border-radius: 5px;
            border: 1px solid #ddd;
            margin: 1rem 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <img src="https://ebone.es/wp-content/uploads/2024/11/Logo-Grupo-Lineas-cuadrado-1500px.png" alt="Logo Grupo Ebone" class="logo">
        <h1>Recuperar Información</h1>
        <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php if (!empty($decrypted_password)): ?>
            <div class="password-content" id="password-content"><?php echo htmlspecialchars($decrypted_password); ?></div>
            <button class="copy-button" onclick="copyPassword()">Copiar Información</button>
            <?php if ($is_last_use): ?>
                <p class="remaining-uses last-use" style="margin-top: 1rem;">Este era el último uso. El enlace ha sido eliminado.</p>
            <?php elseif ($remaining_uses > 0): ?>
                <p class="remaining-uses" style="margin-top: 1rem;">Usos restantes: <?php echo $remaining_uses; ?></p>
            <?php endif; ?>
        <?php endif; ?>
        <div class="footer">
            © 2025 Grupo Ebone. Todos los derechos reservados.
        </div>
    </div>

    <script>
        function copyPassword() {
            const contentElement = document.getElementById('password-content');
            const text = contentElement.innerText;

            navigator.clipboard.writeText(text)
                .then(() => {
                    alert('Información copiada al portapapeles.');
                })
                .catch(() => {
                    alert('Error al copiar la información.');
                });
        }
    </script>
</body>
</html>
