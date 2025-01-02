<?php
// Configuración de la base de datos
        $host = 'localhost'; // Usually 'localhost'
        $dbname = 'passworddb';
        $user = 'passuser';
        $pass = 'userpassdb';
        $charset = 'utf8mb4';


// Conexión directa a la base de datos
try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Error al conectar a la base de datos: ' . $e->getMessage());
}

// Procesar archivo CSV
if (($handle = fopen('accesos.csv', 'r')) !== false) {
    // Leer encabezados y avanzar al contenido
    fgetcsv($handle);

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('
            INSERT INTO `passwords-manager`
            (linea_de_negocio, descripcion, nombre, usuario, password, enlace, info_adicional)
            VALUES (:linea_de_negocio, :descripcion, :nombre, :usuario, :password, :enlace, :info_adicional)
        ');

        while (($data = fgetcsv($handle, 1000, ',')) !== false) { // Cambia a ';' si es necesario
            $stmt->execute([
                ':linea_de_negocio' => $data[0],
                ':descripcion' => $data[1],
                ':nombre' => $data[2],
                ':usuario' => $data[3],
                ':password' => $data[4],
                ':enlace' => $data[5],
                ':info_adicional' => $data[6],
            ]);
        }

        $pdo->commit();
        echo 'Datos importados correctamente.';
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo 'Error al importar datos: ' . $e->getMessage();
    }

    fclose($handle);
} else {
    echo 'No se pudo abrir el archivo CSV.';
}
?>
