<?php
session_start();
require '../db.php';

$error = "";

/* ── Already logged in — redirect ───────────────── */
if (isset($_SESSION["user_id"])) {
    switch ($_SESSION["role"]) {
        case "receptionist": header("Location: receptionist_adduser.php"); break;
        case "doctor":       header("Location: doctor_dashboard.php");     break;
        default:             header("Location: patienthome.php");
    }
    exit();
}

/* ── Handle login submission ─────────────────────── */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email    = trim($_POST["email"]    ?? '');
    $password = trim($_POST["password"] ?? '');

    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {

        /*
         * Fetch the user by email only — never put the password
         * in the SQL WHERE clause. Always verify the hash in PHP.
         */
        $stmt = $conn->prepare("
            SELECT id, first_name, role, password
            FROM users
            WHERE email = :email
            LIMIT 1
        ");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {

            /* Rehash silently if the bcrypt cost factor was upgraded */
            if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $conn->prepare("UPDATE users SET password = ? WHERE id = ?")
                     ->execute([$newHash, $user['id']]);
            }

            /* Regenerate session ID to prevent session-fixation attacks */
            session_regenerate_id(true);
            $_SESSION["user_id"]    = $user["id"];
            $_SESSION["role"]       = $user["role"];
            $_SESSION["first_name"] = $user["first_name"];

            switch ($user["role"]) {
                case "receptionist": header("Location: receptionist_adduser.php"); break;
                case "doctor":       header("Location: doctor_dashboard.php");     break;
                default:             header("Location: patienthome.php");
            }
            exit();

        } else {
            /*
             * Same error for wrong email AND wrong password
             * so attackers cannot enumerate valid email addresses.
             */
            $error = "Invalid email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Login — ApexCare Hospital</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../static/login.css?v=2">
</head>
<body>

    <div class="glow-orb glow-orb-1"></div>
    <div class="glow-orb glow-orb-2"></div>
    <div class="glow-orb glow-orb-3"></div>

    <div class="login-wrapper">

        <div class="login-brand">
            <div class="brand-icon"><i class="fas fa-heartbeat"></i></div>
            <h1>APEX<span>CARE</span></h1>
            <p>Health Record System</p>
        </div>

        <div class="login-container">

            <div class="login-header">
                <h2>Welcome back</h2>
                <p class="subtitle">Sign in to access your portal</p>
            </div>

            <div class="form-divider"></div>

            <?php if (!empty($error)) : ?>
                <div class="error">
                    <i class="fas fa-exclamation-circle"></i>
                    <div class="error-text">
                        <strong>Login Failed</strong>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="on">

                <div class="input-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i>
                        Email Address
                    </label>
                    <div class="input-wrap">
                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="you@example.com"
                            autocomplete="email"
                            required
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                        >
                        <i class="fas fa-envelope input-icon"></i>
                    </div>
                </div>

                <div class="input-group">
                    <label for="password">
                        <i class="fas fa-lock"></i>
                        Password
                    </label>
                    <div class="input-wrap">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >
                        <i class="fas fa-lock input-icon"></i>
                        <button type="button" class="toggle-password" onclick="togglePassword()" aria-label="Toggle password visibility">
                            <i class="fas fa-eye" id="eye-icon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i>
                    Sign In
                </button>

            </form>

            <div class="card-footer">
                <div class="back-home">
                    <a href="home.php">
                        <i class="fas fa-arrow-left"></i>
                        Back to Main Website
                    </a>
                </div>
                <div class="security-note">
                    <i class="fas fa-lock"></i>
                    Secure Access &bull; ApexCare Hospital
                </div>
            </div>

        </div>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const icon  = document.getElementById('eye-icon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>

</body>
</html>