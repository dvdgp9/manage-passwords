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

// ============ User & Remember-me helpers ============

function current_user(): ?array {
    if (!empty($_SESSION['user_id'])) {
        try {
            if (!function_exists('getDBConnection')) return null;
            $pdo = getDBConnection();
            $stmt = $pdo->prepare('SELECT id, email, role FROM users WHERE id = :id');
            $stmt->execute([':id' => $_SESSION['user_id']]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);
            return $u ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }
    return null;
}

function is_admin(): bool {
    $u = current_user();
    return $u && ($u['role'] ?? '') === 'admin';
}

function require_auth(): void {
    if (!current_user()) {
        header('Location: login.php');
        exit;
    }
}

function remember_cookie_name(): string { return 'rm_token'; }

function issue_remember_token(int $userId, int $days = 60): void {
    if (!function_exists('getDBConnection')) return;
    $pdo = getDBConnection();
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ipHash = $ip ? hash('sha256', $ip) : null;
    $expiresAt = (new DateTimeImmutable("+{$days} days"))->format('Y-m-d H:i:s');

    // enforce max 5 tokens per user (delete oldest beyond limit)
    $pdo->prepare('DELETE us FROM user_sessions us
                   WHERE us.user_id = :uid AND us.id NOT IN (
                       SELECT id FROM (
                           SELECT id FROM user_sessions WHERE user_id = :uid2 AND revoked = 0 ORDER BY created_at DESC LIMIT 5
                       ) t
                   )')->execute([':uid' => $userId, ':uid2' => $userId]);

    $stmt = $pdo->prepare('INSERT INTO user_sessions (user_id, token_hash, user_agent, ip_hash, expires_at)
                           VALUES (:uid, :th, :ua, :ip, :exp)');
    $stmt->execute([':uid' => $userId, ':th' => $hash, ':ua' => $ua, ':ip' => $ipHash, ':exp' => $expiresAt]);

    setcookie(remember_cookie_name(), $token, [
        'expires' => time() + (60 * 60 * 24 * $days),
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function try_auto_login_from_cookie(): void {
    if (!empty($_SESSION['user_id'])) return; // already logged
    $token = $_COOKIE[remember_cookie_name()] ?? '';
    if (!$token || !function_exists('getDBConnection')) return;
    $hash = hash('sha256', $token);
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare('SELECT us.id, us.user_id FROM user_sessions us
                               JOIN users u ON u.id = us.user_id
                               WHERE us.token_hash = :th AND us.revoked = 0 AND us.expires_at > NOW() LIMIT 1');
        $stmt->execute([':th' => $hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $_SESSION['user_id'] = (int)$row['user_id'];
            $_SESSION['authenticated'] = true;
            regenerate_session_id();
            $pdo->prepare('UPDATE user_sessions SET last_used_at = NOW() WHERE id = :id')->execute([':id' => $row['id']]);
        }
    } catch (Throwable $e) {
        // ignore
    }
}

function do_logout_and_redirect(): void {
    // revoke remember token if present
    $token = $_COOKIE[remember_cookie_name()] ?? '';
    if ($token && function_exists('getDBConnection')) {
        try {
            $hash = hash('sha256', $token);
            $pdo = getDBConnection();
            $pdo->prepare('UPDATE user_sessions SET revoked = 1 WHERE token_hash = :th')->execute([':th' => $hash]);
        } catch (Throwable $e) {}
    }
    // clear cookie
    setcookie(remember_cookie_name(), '', time() - 3600, '/', '', true, true);

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: login.php');
    exit;
}

// Bootstrap helper to be included at top of pages
function bootstrap_security(bool $enforceAuth = false): void {
    set_security_headers();
    start_secure_session();
    session_sliding_timeout(900); // 15 minutes
    // auto-login via remember-me if possible
    try_auto_login_from_cookie();
    if ($enforceAuth) {
        require_auth();
    }
}
