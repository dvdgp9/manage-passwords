<?php
require_once 'security.php';
require_once 'config.php';
bootstrap_security(true); // requires authenticated session
$csrf = ensure_csrf_token();
// Prepare reusable header HTML
ob_start();
include __DIR__ . '/header.php';
$headerHtml = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Introducir Contraseña</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script>
        function formatLink() {
            const linkInput = document.getElementById('enlace');
            let link = linkInput.value.trim();
            if (link && !link.startsWith('http://') && !link.startsWith('https://')) {
                linkInput.value = 'https://' + link;
            }
        }
    </script>
</head>
<body>
    <?= $headerHtml ?>
    <main class="page">
    <h1>Almacenar nueva contraseña</h1>
    <form action="guardar.php" method="post" onsubmit="formatLink()">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
        <label for="linea_de_negocio">Línea de Negocio:</label>

        <input type="text" id="linea_de_negocio" name="linea_de_negocio" placeholder="General, ES, CF, EFit,..." required><br>

        <label for="nombre">Nombre:</label>
        <input type="text" id="nombre" name="nombre" placeholder="Ej: Facebook, Gmail, Canva,..." required><br>

        <label for="descripcion">Descripción:</label>
        <textarea id="descripcion" name="descripcion" placeholder="Describe para qué es esta cuenta"></textarea><br>

        <label for="usuario">Usuario:</label>
        <input type="text" id="usuario" name="usuario" placeholder="Ej: usuario@example.com" required><br>

        <label for="password">Contraseña:</label>
        <input type="password" id="password" name="password" placeholder="Introduce la contraseña" required>
        <div class="password-buttons">
            <button type="button" onclick="togglePasswordVisibility()">Mostrar</button>
            <button type="button" onclick="pastePassword()">Pegar Contraseña</button>
        </div><br>

        <label for="enlace">Enlace:</label>
        <input type="text" id="enlace" name="enlace" placeholder="Ej: example.com" required><br>

        <label for="info_adicional">Info Adicional:</label>
        <textarea id="info_adicional" name="info_adicional" placeholder="Ej: Pregunta de seguridad: Nombre de tu mascota"></textarea><br>
        <button type="submit">Guardar</button>
    </form>
    </main>

    <script>
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
            } else {
                passwordInput.type = 'password';
            }
        }

        function pastePassword() {
            navigator.clipboard.readText().then(text => {
                document.getElementById('password').value = text;
            }).catch(err => {
                alert('No se pudo pegar la contraseña. Asegúrate de que el portapapeles tenga texto.');
            });
        }
    </script>
</body>
</html>
