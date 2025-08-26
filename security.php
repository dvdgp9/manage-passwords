<?php
// security.php
// Centralized security: headers, session hardening, CSRF utilities, auth gate.

function set_security_headers(): void {
    // Basic hardened headers; adjust CSP as needed for external resources
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-XSS-Protection: 0'); // modern browsers
    header('Strict-Transport-Security: max-age=63072000; includeSubDomains; preload');
    // Minimal CSP allowing current page, inline styles from our CSS and Google Fonts used by the app
    $csp = "default-src 'self'; script-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' https: data:; frame-ancestors 'self'; base-uri 'self'";
    header("Content-Security-Policy: $csp");
}

function start_secure_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $secure = true; // site runs under HTTPS
        $httponly = true;
        $samesite = 'Lax';
        $cookieParams = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $cookieParams['path'] ?? '/',
            'domain' => $cookieParams['domain'] ?? '',
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => $samesite,
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_secure', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_only_cookies', '1');
        session_start();
    }
}

function session_sliding_timeout(int $inactiveSeconds = 900): void {
    if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = time();
        return;
    }
    if (time() - (int)$_SESSION['last_activity'] > $inactiveSeconds) {
        // expire session
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        header('Location: ver-passwords.php');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

function regenerate_session_id(): void {
    if (!isset($_SESSION['__regenerated'])) {
        session_regenerate_id(true);
        $_SESSION['__regenerated'] = true;
    }
}

function ensure_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_from_request(): void {
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $valid = isset($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
    if (!$valid) {
        http_response_code(403);
        echo 'CSRF validation failed';
        exit;
    }
}

function require_auth(): void {
    if (empty($_SESSION['authenticated'])) {
        header('Location: ver-passwords.php');
        exit;
    }
}

function do_logout_and_redirect(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: ver-passwords.php');
    exit;
}

// Bootstrap helper to be included at top of pages
function bootstrap_security(bool $enforceAuth = false): void {
    set_security_headers();
    start_secure_session();
    session_sliding_timeout(900); // 15 minutes
    if ($enforceAuth) {
        require_auth();
    }
}
