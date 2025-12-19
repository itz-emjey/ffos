<?php
// api_teller_update_order.php
require_once 'config.php';
require_once 'api_auth.php';
header('Content-Type: application/json');

// $tellerTerminalId = $_SESSION['terminal_id'] ?? null;
// Require teller role for this endpoint and use DB-verified terminal id
$terminal = require_terminal_types(['TELLER']);
$tellerTerminalId = (int)$terminal['id'];

$payloadJson = $_POST['payload'] ?? '';
if (!$payloadJson) {
    echo json_encode(['success' => false, 'error' => 'No payload']);
    exit;
}

$data = json_decode($payloadJson, true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

$orderId = (int)($data['order_id'] ?? 0);
$items   = $data['items'] ?? [];
$cash    = (float)($data['cash'] ?? 0);

if ($orderId <= 0 || !is_array($items) || !count($items)) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

try {
    // Check current status to avoid paying non-UNPAID orders
    $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ? LIMIT 1");
    $stmt->execute([$orderId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        exit;
    }
    $currentStatus = strtoupper(trim($row['status'] ?? ''));
    if ($currentStatus !== 'UNPAID') {
        echo json_encode(['success' => false, 'error' => 'Only UNPAID orders can be paid']);
        exit;
    }

    $pdo->beginTransaction();

    // Compute total
    $total = 0;
    foreach ($items as $it) {
        $price = (float)($it['price'] ?? 0);
        $qty   = (int)($it['quantity'] ?? 0);
        if ($qty <= 0 || $price < 0) {
            continue;
        }
        $total += $price * $qty;
    }

    if ($total <= 0) {
        throw new Exception('Total cannot be zero.');
    }

    // Replace order items
    $del = $pdo->prepare("DELETE FROM order_items WHERE order_id = ?");
    $del->execute([$orderId]);

    $ins = $pdo->prepare("
        INSERT INTO order_items (order_id, menu_item_id, quantity, price, source)
        VALUES (?, ?, ?, ?, ?)
    ");
    foreach ($items as $it) {
        $menuId = (int)($it['menu_item_id'] ?? 0);
        $qty    = (int)($it['quantity'] ?? 0);
        $price  = (float)($it['price'] ?? 0);
        $source = strtoupper($it['source'] ?? 'CUSTOMER');
        if (!in_array($source, ['CUSTOMER','TELLER'], true)) {
            $source = 'CUSTOMER';
        }
        if ($menuId <= 0 || $qty <= 0 || $price < 0) continue;
        $ins->execute([$orderId, $menuId, $qty, $price, $source]);
    }

    $now    = date('Y-m-d H:i:s');
    $change = max(0, $cash - $total);

    // Update order: now IN_PROCESS, with cash + change + teller terminal
    $upd = $pdo->prepare("
        UPDATE orders
        SET total_amount      = ?,
            status            = 'IN_PROCESS',
            updated_at        = ?,
            paid_at           = ?,
            teller_terminal_id= ?,
            cash_received     = ?,
            change_amount     = ?
        WHERE id = ?
    ");
    $upd->execute([
        $total,
        $now,
        $now,
        $tellerTerminalId,
        $cash,
        $change,
        $orderId
    ]);

    $pdo->commit();

    if (function_exists('send_ws_message')) {
        send_ws_message([
            'type'     => 'order_updated',
            'order_id' => $orderId,
        ]);
    }

    echo json_encode([
        'success' => true,
        'change'  => $change,
        'total'   => $total,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
