<?php
require_once 'config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = $_POST['username'] ?? '';
    $p = $_POST['password'] ?? '';
        // Rate-limit by IP and username to prevent brute-force
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ipKey = 'admin_ip:' . $ip;
    $userKey = 'admin_user:' . $u;
    $ipStatus = rl_check($ipKey, 20, 900); // 20 attempts per 15 min per IP
    $userStatus = rl_check($userKey, 10, 900); // 10 attempts per 15 min per username
    if (!$ipStatus['allowed'] || !$userStatus['allowed']) {
        // Too many attempts
        error_log(sprintf("Rate limit block for admin login: username=%s, ip=%s, time=%s", $u, $ip, date('c')));
        $error = 'Too many attempts. Try again later.';
    } else {
    if ($u === ADMIN_USER && $p === ADMIN_PASS) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        // Clear rate limit counters on success
        rl_clear($ipKey);
        rl_clear($userKey);
        header('Location: admin_dashboard.php');
        exit;
    } else {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        error_log(sprintf("Failed admin login attempt: username=%s, ip=%s, ua=%s, time=%s", $u, $ip, $ua, date('c')));
        $error = 'Invalid credentials';
                // increment rate limiter keys
        rl_increment($ipKey);
        rl_increment($userKey);
    }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center" style="min-height:100vh;">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h4 mb-3 text-center">Admin Login</h2>
                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-danger w-100">Login</button>
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
