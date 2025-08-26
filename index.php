<?php
require_once 'security.php';
require_once 'config.php';

// Initialize security without forcing auth to decide where to send the user
bootstrap_security(false);

if (current_user()) {
    header('Location: ver-passwords.php');
    exit;
}

header('Location: login.php');
exit;
