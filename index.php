<?php
require_once 'security.php';
require_once 'config.php';

// Initialize security; do not auto-redirect to the list
bootstrap_security(false);

// Always send to login as the canonical entry point
header('Location: login.php');
exit;
