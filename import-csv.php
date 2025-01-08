<?php
// Include the config file
require_once 'config.php';

// Connect to the database
$pdo = getDBConnection();

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
