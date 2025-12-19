<?php
// api_kitchen_ready_for_claim.php
require_once 'config.php';
require_once 'api_auth.php';

header('Content-Type: application/json; charset=utf-8');
// Only kitchen terminals should mark orders ready for claim
require_terminal_types(['KITCHEN']);

$orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
if ($orderId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid order ID']);
    exit;
}

try {
    // Only IN_PROCESS can be moved to READY_FOR_CLAIM
    $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ? LIMIT 1");
    $stmt->execute([$orderId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        exit;
    }

    $currentStatus = strtoupper(trim($row['status'] ?? ''));
    if ($currentStatus !== 'IN_PROCESS') {
        echo json_encode(['success' => false, 'error' => 'Only IN_PROCESS orders can be marked READY_FOR_CLAIM']);
        exit;
    }

    $now = date('Y-m-d H:i:s');

    $upd = $pdo->prepare("
        UPDATE orders
        SET status = 'READY_FOR_CLAIM', updated_at = ?
        WHERE id = ?
    ");
    $upd->execute([$now, $orderId]);

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
