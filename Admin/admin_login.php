<?php
session_start();
require_once dirname(__DIR__) . '../db.php';
require_once 'adminauth.php';

$auth = new Auth($conn);

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    header("Location: admindashboard.php");
    exit();
}

$error = "";

// Generate CSRF token
$csrf_token = $auth->generateCSRFToken();

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Validate CSRF
    if (!$auth->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        die("Invalid CSRF token. Please refresh and try again.");
    }

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $loginResult = $auth->login($username, $password);

    if ($loginResult === true) {
        header("Location: admindashboard.php");
        exit();
    } else {
        $error = $loginResult; // Shows lock message OR invalid credentials
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Admin Login - ApexCare</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../static/admin_login.css">
</head>

<body>

<div class="login-card">
    <h2>Admin Login</h2>

    <?php if (!empty($error)): ?>
        <div class="error">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">

        <!-- CSRF Token -->
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

        <div class="input-group">
            <label>Username or Email</label>
            <input type="text" name="username" placeholder="Enter your username or email" required>
        </div>

        <div class="input-group">
            <label>Password</label>
            <input type="password" name="password" placeholder="Enter your password" required>
        </div>

        <button type="submit">Login</button>

    </form>
</div>

</body>
</html>