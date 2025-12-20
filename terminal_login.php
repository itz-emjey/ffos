<?php
require_once 'config.php';
require_once 'rate_limit.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pin = $_POST['pin'] ?? '';
    // Rate-limit by IP and PIN fingerprint
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $pinFp = isset($pin) ? substr(sha1((string)$pin), 0, 8) : 'none';
    $ipKey = 'term_ip:' . $ip;
    $pinKey = 'term_pin:' . $pinFp;
    $ipStatus = rl_check($ipKey, 30, 900); // 30 attempts per 15 min per IP
    $pinStatus = rl_check($pinKey, 8, 900); // 8 attempts per 15 min per PIN fingerprint
    if (!$ipStatus['allowed'] || !$pinStatus['allowed']) {
        error_log(sprintf("Rate limit block for terminal login: pin_fp=%s, ip=%s, time=%s", $pinFp, $ip, date('c')));
        $error = 'Too many attempts. Try again later.';
    } else {
    $stmt = $pdo->prepare("SELECT * FROM terminals WHERE pin_code = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$pin]);
    $terminal = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($terminal) {
        session_regenerate_id(true);
        $_SESSION['terminal_id'] = $terminal['id'];
        $_SESSION['terminal_type'] = $terminal['type'];
        $_SESSION['employee_name'] = $terminal['employee_name'];
        
        // Clear rate limit counters on successful terminal login
        if (isset($ipKey)) rl_clear($ipKey);
        if (isset($pinKey)) rl_clear($pinKey);

        switch ($terminal['type']) {
            case 'CUSTOMER':
                header('Location: customer_kiosk.php');
                break;
            case 'TELLER':
                header('Location: teller_dashboard.php');
                break;
            case 'KITCHEN':
                header('Location: kitchen_dashboard.php');
                break;
            case 'CLAIM':
                header('Location: claim_display.php');
                break;
        }
        exit;
    } else {
        // Log failed terminal login attempt. Store a non-reversible fingerprint of the PIN, not the raw PIN.
        // $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        // $pinFingerprint = isset($pin) ? substr(sha1((string)$pin), 0, 8) : 'none';
        // error_log(sprintf("Failed terminal login attempt: pin_fp=%s, ip=%s, ua=%s, time=%s", $pinFingerprint, $ip, $ua, date('c')));
         error_log(sprintf("Failed terminal login attempt: pin_fp=%s, ip=%s, ua=%s, time=%s", $pinFp, $ip, $ua, date('c')));
        $error = 'Invalid PIN or inactive terminal.';
         // increment rate limiter keys
        rl_increment($ipKey);
        rl_increment($pinKey);
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Terminal Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center" style="min-height:100vh;">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h4 mb-3 text-center">Terminal Login</h2>
                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <div class="mb-3">
                            <label for="pin" class="form-label">Terminal PIN</label>
                            <input type="password" name="pin" id="pin" maxlength="6"
                                   class="form-control form-control-lg text-center"
                                   autofocus required>
                        </div>
                        <button type="submit" class="btn btn-warning w-100">Login</button>
                    </form>
                    <div class="text-center mt-3">
                        <a href="index.php" class="text-decoration-none">&larr; Back to Home</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
