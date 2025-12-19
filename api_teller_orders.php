<?php
// require_once 'auth_terminal.php';
require_once 'config.php';
require_once 'api_auth.php';

header('Content-Type: application/json');

// if ($_SESSION['terminal_type'] !== 'TELLER') {
//     echo json_encode(['success' => false, 'error' => 'Not authorized']);
//     exit;
// }
require_terminal_types(['TELLER']);

$stmt = $pdo->query(
    "SELECT id, total_amount, cash_received, change_amount, status
     FROM orders
     WHERE status IN ('NEW','CONFIRMED','PAID')
     ORDER BY created_at ASC"
);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'orders' => $orders]);
