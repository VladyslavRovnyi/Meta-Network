<?php
// Generate or return the CSRF token for this session
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

// Verify CSRF token from either form field or header; halt with JSON or 419
function verify_csrf_or_die(bool $json = false): void {
    $sent = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $valid = isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $sent);
    if ($valid) return;

    if ($json) {
        while (ob_get_level()) { ob_end_clean(); }
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(419);
        echo json_encode(['ok' => false, 'msg' => 'Invalid CSRF token']);
    } else {
        http_response_code(419);
        echo 'Invalid CSRF token';
    }
    exit;
}

// Require a logged-in user; halt with JSON 401 or redirect to login
function require_auth(bool $json = false): void {
    if (!isset($_SESSION['id'])) {
        if ($json) {
            while (ob_get_level()) { ob_end_clean(); }
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode(['ok' => false, 'msg' => 'Not authenticated']);
            exit;
        }
        $_SESSION['message'] = 'Please login';
        header('location: /login');
        exit;
    }
}

// For JSON endpoints: clean buffers + JSON header
function json_mode(): void {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
}
