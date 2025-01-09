<?php
session_start();
session_destroy(); // Clear the session
header('Location: ver-passwords.php'); // Redirect back to the password page
exit;
?>
