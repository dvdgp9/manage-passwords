<?php
require_once 'security.php';
bootstrap_security(false);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ver-passwords.php');
    exit;
}
verify_csrf_from_request();
do_logout_and_redirect();
?>
