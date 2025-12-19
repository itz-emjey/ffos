<?php
// api_teller_cancel_order.php
require_once 'config.php';
require_once 'api_auth.php';
// $tellerTerminalId = $_SESSION['terminal_id'] ?? null;
header('Content-Type: application/json');
// Require teller role and canonicalize teller terminal id
$terminal = require_terminal_types(['TELLER']);
$tellerTerminalId = (int)$terminal['id'];

$orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
if ($orderId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid order ID']);
    exit;
}

try {
    // Check current status
    $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ? LIMIT 1");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        exit;
    }

    if ($order['status'] !== 'UNPAID') {
        echo json_encode(['success' => false, 'error' => 'Only UNPAID orders can be cancelled']);
        exit;
    }

    $now = date('Y-m-d H:i:s');
    $upd = $pdo->prepare("
		UPDATE orders
		SET status = 'CANCELLED', updated_at = ?, teller_terminal_id = ?
		WHERE id = ?
	");
	$upd->execute([$now, $tellerTerminalId, $orderId]);


    if (function_exists('send_ws_message')) {
        send_ws_message([
            'type'     => 'order_updated',
            'order_id' => $orderId,
        ]);
    }

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
