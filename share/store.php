<?php
// Force HTTPS
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit;
}

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

require 'includes/db.php';
require 'includes/rate_limit.php';
require 'includes/phpmailer/Exception.php';
require 'includes/phpmailer/PHPMailer.php';
require 'includes/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get real IP address even behind proxy
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
    
    // Check rate limit (10 passwords per 5 minutes)
    if (isRateLimited($ip, 10, 300)) {
        http_response_code(429); // Too Many Requests
        echo "<!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Demasiados intentos</title>
            <link href='https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap' rel='stylesheet'>
            <link href='styles.css' rel='stylesheet'>
            <link rel='icon' type='image/png' href='favicon.png'>
        </head>
        <body>
            <div class='container'>
                <img src='https://ebone.es/wp-content/uploads/2024/11/Logo-Grupo-Lineas-cuadrado-1500px.png' alt='Logo Grupo Ebone' class='logo'>
                <h1>Demasiados intentos</h1>
                <p>Has creado demasiadas contraseñas. Por favor, espera 5 minutos antes de crear otra.</p>
                <div class='footer'>
                    © 2025 Grupo Ebone. Todos los derechos reservados.
                </div>
            </div>
        </body>
        </html>";
        exit;
    }

    $password = $_POST['password'];
    $email = $_POST['email'] ?? ''; // Make email optional
    $max_retrievals = max(1, min(100, (int)($_POST['max_retrievals'] ?? 1))); // Between 1 and 100
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    // Limit title and message length
    if (strlen($title) > 255) {
        $title = substr($title, 0, 255);
    }
    if (strlen($message) > 2000) {
        $message = substr($message, 0, 2000);
    }
    
    $link_hash = hash('sha256', uniqid());

    // 1) Generate IV
    $cipher_method = 'AES-256-CBC';
    $iv_length     = openssl_cipher_iv_length($cipher_method);
    $iv            = openssl_random_pseudo_bytes($iv_length);

    // 2) Encrypt the password
    $encrypted_password = openssl_encrypt(
        $password,
        $cipher_method,
        ENCRYPTION_KEY,   // from config.php
        0,
        $iv
    );

    // 3) Base64-encode the IV (so it's safe to store in a text column)
    $iv_encoded = base64_encode($iv);

    try {
        // Insert into the DB (note the iv column!)
        $stmt = $pdo->prepare("
        INSERT INTO passwords (password, iv, link_hash, email, max_retrievals, retrieval_count, title, message)
        VALUES (?, ?, ?, ?, ?, 0, ?, ?)
        ");
        $stmt->execute([
        $encrypted_password,
        $iv_encoded,
        $link_hash,
        $email,
        $max_retrievals,
        $title ?: null,
        $message ?: null
        ]);

        // Generate the shareable link
        $shareable_link = "https://passwords.ebone.es/share/retrieve.php?hash=" . $link_hash;

        // If email is provided, send the email
        if (!empty($email)) {
            $mail = new PHPMailer(true);

            // SMTP configuration from config.php
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = SMTP_SECURE;
            $mail->Port = SMTP_PORT;

            //Character encoding
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->isHTML(false); // If you want a plain-text email


            // Email content
            $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
            $mail->addAddress($email);
            
            // Dynamic subject based on title
            $mail->Subject = !empty($title) ? $title : 'Te han compartido información de forma segura';
            
            // Build email body
            $greeting = "¡Hola!\n\n";
            
            // Add custom message if provided
            if (!empty($message)) {
                $body_message = $message . "\n\n";
            } else {
                $body_message = "Te han enviado información de forma segura.\n\n";
            }
            
            // Usage info
            if ($max_retrievals > 1) {
                $usage_info = "Este enlace permite hasta " . $max_retrievals . " consultas. Cada vez que se accede, se consume un uso. ";
            } else {
                $usage_info = "Este enlace permite una única consulta. Una vez que accedas, el enlace se eliminará automáticamente. ";
            }
            $usage_info .= "Además, el enlace expirará automáticamente pasados 7 días por razones de seguridad.\n\n";
            $usage_info .= "Si crees que se trata de un error o no esperabas este mensaje, ignóralo.\n\n";
            
            $link_section = "Accede aquí:\n" . $shareable_link . "\n\n";
            $signature = "Que tengas un buen día,\nEl equipo de Marketing/IT del Grupo Ebone";
            
            $mail->Body = $greeting . $body_message . $usage_info . $link_section . $signature;

            $mail->send();
            $email_sent = true;
        } else {
            $email_sent = false;
        }

        // Show the link to the user
        echo "<!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Enlace Generado</title>
            <link href='https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap' rel='stylesheet'>
            <link href='styles.css' rel='stylesheet'>
            <link rel='icon' type='image/png' href='favicon.png'>
        </head>
        <body>
            <div class='container'>
                <img src='https://ebone.es/wp-content/uploads/2024/11/Logo-Grupo-Lineas-cuadrado-1500px.png' alt='Logo Grupo Ebone' class='logo'>
                <h1>Enlace Generado</h1>
                <div class='link' id='shareable-link'>" . $shareable_link . "</div>
                <button class='copy-button' onclick='copyLink()'>Copiar Enlace</button>";

        if ($email_sent) {
            echo "<p class='success-message'>El enlace ha sido enviado por correo electrónico pero, si lo necesitas, puedes copiarlo pulsando el botón.</p>";
        }

        echo "<div class='footer'>
                    © 2025 Grupo Ebone. Todos los derechos reservados.
                </div>
            </div>

            <script>
                function copyLink() {
                    const linkElement = document.getElementById('shareable-link');
                    const linkText = linkElement.innerText;

                    // Copy to clipboard
                    navigator.clipboard.writeText(linkText)
                        .then(() => {
                            alert('Enlace copiado al portapapeles.');
                        })
                        .catch(() => {
                            alert('Error al copiar el enlace.');
                        });
                }
            </script>
        </body>
        </html>";
    } catch (Exception $e) {
        echo "Error al enviar el correo electrónico: " . $e->getMessage();
    } catch (PDOException $e) {
        die("Error almacenando la contraseña: " . $e->getMessage());
    }
} else {
    header("Location: index.html");
    exit;
}
?>
