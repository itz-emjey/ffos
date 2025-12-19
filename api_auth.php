<?php
// api_auth.php
// Lightweight API authentication helpers for terminal/admin APIs

// Ensure session and (if necessary) config/DB are available.
if (!isset($_SESSION)) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
}

// If $pdo is not defined, try to include config.php (defensive)
if (!isset($pdo) && file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

function api_json_error(string $msg, int $httpCode = 401): void
{
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($httpCode);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

/**
 * Require that the current session belongs to a terminal with one of the allowed types.
 * Verifies the terminal exists and is active in the DB to prevent session tampering.
 * Returns the terminal DB row on success.
 */
function require_terminal_types(array $allowedTypes)
{
    if (empty($_SESSION['terminal_id']) || empty($_SESSION['terminal_type'])) {
        api_json_error('Authentication required', 401);
    }

    $termId = (int)($_SESSION['terminal_id'] ?? 0);
    if ($termId <= 0) {
        api_json_error('Authentication required', 401);
    }

    global $pdo;
    if (!isset($pdo)) {
        api_json_error('Server error', 500);
    }

    $stmt = $pdo->prepare('SELECT id, type, is_active FROM terminals WHERE id = ? LIMIT 1');
    $stmt->execute([$termId]);
    $t = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$t || empty($t['id']) || !$t['is_active']) {
        api_json_error('Terminal not found or inactive', 403);
    }

    if (!in_array($t['type'], $allowedTypes, true)) {
        api_json_error('Insufficient role', 403);
    }

    // Canonicalize session values from DB
    $_SESSION['terminal_id'] = $t['id'];
    $_SESSION['terminal_type'] = $t['type'];

    return $t;
}

function require_admin_api(): void
{
    if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
        api_json_error('Admin authentication required', 401);
    }
}

?>
