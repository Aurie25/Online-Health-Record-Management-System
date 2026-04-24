<?php
session_start();

require_once '../db.php';        /* ← Fixed: was dirname(__DIR__).'../db.php' which broke the path */
require_once 'adminauth.php';

$auth = new Auth($conn);
$auth->redirectIfNotLoggedIn();

$success = "";
$error   = "";

/* ==================================
   HANDLE FORM SUBMISSION
================================== */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $first_name   = trim($_POST['first_name']   ?? '');
    $last_name    = trim($_POST['last_name']     ?? '');
    $email        = trim($_POST['email']         ?? '');
    $phone        = trim($_POST['phone']         ?? '');
    $national_id  = trim($_POST['national_id']   ?? '');
    $gender       = trim($_POST['gender']        ?? '');
    $dob          = trim($_POST['date_of_birth'] ?? '');
    $raw_password = trim($_POST['password']      ?? '');

    /* ── Server-side validation ── */
    $errors = [];
    if (empty($first_name))  $errors[] = "First name is required.";
    if (empty($last_name))   $errors[] = "Last name is required.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
                             $errors[] = "A valid email address is required.";
    if (empty($phone))       $errors[] = "Phone number is required.";
    if (empty($national_id)) $errors[] = "National ID is required.";
    if (!in_array($gender, ['male','female','other']))
                             $errors[] = "Please select a gender.";
    if (empty($dob))         $errors[] = "Date of birth is required.";
    if (strlen($raw_password) < 8)
                             $errors[] = "Password must be at least 8 characters.";

    if (!empty($errors)) {
        $error = implode(" ", $errors);
    } else {

        /* ── Check for duplicate email OR national ID before inserting ── */
        $check = $conn->prepare("
            SELECT id FROM users
            WHERE email = ? OR national_id = ?
            LIMIT 1
        ");
        $check->execute([$email, $national_id]);

        if ($check->fetch()) {
            $error = "A user with that email or National ID already exists.";
        } else {

            try {
                /*
                 * Insert matches the EXACT columns in the users table:
                 * id, first_name, last_name, email, national_id, phone,
                 * date_of_birth, gender, password, role, profile_picture
                 *
                 * ← Removed created_at (not in users table — only in admins)
                 * ← Added date_of_birth and gender (both NOT NULL in users)
                 */
                $stmt = $conn->prepare("
                    INSERT INTO users
                        (first_name, last_name, email, national_id, phone,
                         date_of_birth, gender, password, role)
                    VALUES
                        (:first_name, :last_name, :email, :national_id, :phone,
                         :dob, :gender, :password, 'receptionist')
                ");

                $stmt->execute([
                    ':first_name'  => $first_name,
                    ':last_name'   => $last_name,
                    ':email'       => $email,
                    ':national_id' => $national_id,
                    ':phone'       => $phone,
                    ':dob'         => $dob,
                    ':gender'      => $gender,
                    ':password'    => password_hash($raw_password, PASSWORD_DEFAULT),
                ]);

                $newUserId = $conn->lastInsertId();

                /* ── Activity log (uses admin_activity_logs table) ── */
                $log = $conn->prepare("
                    INSERT INTO admin_activity_logs
                        (admin_id, action, details, ip_address, timestamp)
                    VALUES
                        (:admin_id, :action, :details, :ip, NOW())
                ");
                $log->execute([
                    ':admin_id' => $_SESSION['admin_id'],
                    ':action'   => 'receptionist_register',
                    ':details'  => "Registered receptionist ID {$newUserId} ({$first_name} {$last_name})",
                    ':ip'       => $_SERVER['REMOTE_ADDR'],
                ]);

                $success = "Receptionist <strong>" . htmlspecialchars($first_name . ' ' . $last_name) . "</strong> registered successfully!";

            } catch (PDOException $e) {
                /*
                 * Show the real DB error in development so you can debug.
                 * In production, replace this with a generic message.
                 */
                $error = "Database error: " . htmlspecialchars($e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Receptionist — ApexCare Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../static/admin_sidebar.css">
    <link rel="stylesheet" href="../static/register-doctor.css">
</head>
<body>

<?php include '../static/includes/admin_sidebar.php'; ?>

<button class="sidebar-toggle" onclick="toggleSidebar()" aria-label="Toggle sidebar">
    <i class="fas fa-bars"></i>
</button>
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<div class="main-content">

    <!-- ── Page Header ─────────────────────────────── -->
    <div class="page-header">
        <div class="page-header-left">
            <div class="date-strip">
                <i class="far fa-calendar"></i>
                <?php echo date('l, F j, Y'); ?>
            </div>
            <h2>
                <span class="page-header-icon" style="background:var(--orange-light);">
                    <i class="fas fa-user-tie" style="color:var(--orange);"></i>
                </span>
                Register New Receptionist
            </h2>
            <p class="page-header-sub">Add a new receptionist to the ApexCare system</p>
        </div>
        <a href="usermanagement.php" class="btn-back">
            <i class="fas fa-arrow-left"></i>
            Back to Management
        </a>
    </div>

    <!-- ── Alerts ──────────────────────────────────── -->
    <?php if (!empty($success)): ?>
        <div class="alert alert-success" id="successAlert">
            <i class="fas fa-circle-check"></i>
            <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error" id="errorAlert">
            <i class="fas fa-circle-exclamation"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" novalidate>

        <!-- ── Section: Personal Details ──────────── -->
        <div class="form-card">
            <div class="form-card-header">
                <span class="section-icon orange"><i class="fas fa-id-card"></i></span>
                <div>
                    <h3>Personal Information</h3>
                    <p>Identity and contact details for the receptionist</p>
                </div>
            </div>

            <div class="form-card-body">
                <div class="form-grid">

                    <!-- First Name -->
                    <div class="field-group">
                        <label class="field-label" for="first_name">
                            <i class="fas fa-user"></i> First Name <span class="required-dot"></span>
                        </label>
                        <input
                            class="field-input"
                            type="text"
                            id="first_name"
                            name="first_name"
                            placeholder="e.g. Grace"
                            value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
                            required
                        >
                    </div>

                    <!-- Last Name -->
                    <div class="field-group">
                        <label class="field-label" for="last_name">
                            <i class="fas fa-user"></i> Last Name <span class="required-dot"></span>
                        </label>
                        <input
                            class="field-input"
                            type="text"
                            id="last_name"
                            name="last_name"
                            placeholder="e.g. Kamau"
                            value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
                            required
                        >
                    </div>

                    <!-- Email -->
                    <div class="field-group">
                        <label class="field-label" for="email">
                            <i class="fas fa-envelope"></i> Email Address <span class="required-dot"></span>
                        </label>
                        <input
                            class="field-input"
                            type="email"
                            id="email"
                            name="email"
                            placeholder="receptionist@apexcare.com"
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                            required
                        >
                    </div>

                    <!-- Phone -->
                    <div class="field-group">
                        <label class="field-label" for="phone">
                            <i class="fas fa-phone"></i> Phone Number <span class="required-dot"></span>
                        </label>
                        <input
                            class="field-input"
                            type="text"
                            id="phone"
                            name="phone"
                            placeholder="e.g. +254 712 345678"
                            value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                            required
                        >
                    </div>

                    <!-- National ID -->
                    <div class="field-group">
                        <label class="field-label" for="national_id">
                            <i class="fas fa-fingerprint"></i> National ID <span class="required-dot"></span>
                        </label>
                        <input
                            class="field-input"
                            type="text"
                            id="national_id"
                            name="national_id"
                            placeholder="e.g. 34567890"
                            value="<?php echo htmlspecialchars($_POST['national_id'] ?? ''); ?>"
                            required
                        >
                    </div>

                    <!-- Date of Birth -->
                    <div class="field-group">
                        <label class="field-label" for="date_of_birth">
                            <i class="fas fa-calendar-days"></i> Date of Birth <span class="required-dot"></span>
                        </label>
                        <input
                            class="field-input"
                            type="date"
                            id="date_of_birth"
                            name="date_of_birth"
                            max="<?php echo date('Y-m-d'); ?>"
                            value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>"
                            required
                        >
                    </div>

                    <!-- Gender -->
                    <div class="field-group">
                        <label class="field-label" for="gender">
                            <i class="fas fa-venus-mars"></i> Gender <span class="required-dot"></span>
                        </label>
                        <select
                            class="field-input"
                            id="gender"
                            name="gender"
                            required
                        >
                            <option value="" disabled <?php echo empty($_POST['gender']) ? 'selected' : ''; ?>>Select gender</option>
                            <option value="male"   <?php echo (($_POST['gender'] ?? '') === 'male')   ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo (($_POST['gender'] ?? '') === 'female') ? 'selected' : ''; ?>>Female</option>
                            <option value="other"  <?php echo (($_POST['gender'] ?? '') === 'other')  ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>

                    <!-- Password -->
                    <div class="field-group">
                        <label class="field-label" for="password">
                            <i class="fas fa-lock"></i> Password <span class="required-dot"></span>
                        </label>
                        <div class="password-wrap">
                            <input
                                class="field-input"
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Minimum 8 characters"
                                required
                            >
                            <button type="button" class="password-toggle" onclick="togglePassword()" aria-label="Toggle visibility">
                                <i class="fas fa-eye" id="password-eye"></i>
                            </button>
                        </div>
                    </div>

                </div><!-- /.form-grid -->
            </div><!-- /.form-card-body -->

            <!-- ── Actions ────────────────────────── -->
            <div class="form-actions">
                <a href="usermanagement.php" class="btn-reset" style="text-decoration:none;">
                    <i class="fas fa-xmark"></i>
                    Cancel
                </a>
                <button type="reset" class="btn-reset">
                    <i class="fas fa-rotate-left"></i>
                    Clear Form
                </button>
                <button type="submit" class="btn-submit" style="background:linear-gradient(135deg,var(--orange) 0%,#f59e0b 100%);box-shadow:0 8px 24px rgba(249,115,22,0.25);">
                    <i class="fas fa-user-plus"></i>
                    Register Receptionist
                </button>
            </div>

        </div><!-- /.form-card -->

    </form>

    <!-- ── Info Note ───────────────────────────────── -->
    <div style="
        margin-top: 16px;
        padding: 14px 18px;
        background: var(--orange-light);
        border: 1px solid rgba(249,115,22,0.2);
        border-radius: var(--radius-sm);
        display: flex;
        align-items: flex-start;
        gap: 10px;
        font-size: 13px;
        color: #92400e;
        animation: fadeUp 0.5s ease 0.3s both;
    ">
        <i class="fas fa-circle-info" style="color:var(--orange);font-size:15px;margin-top:1px;flex-shrink:0;"></i>
        <span>
            Receptionists log in using their <strong>email and password</strong>.
            They have access to patient check-in, appointment scheduling, and front-desk management features.
        </span>
    </div>

</div><!-- /.main-content -->

<script>
    function togglePassword() {
        const input = document.getElementById('password');
        const eye   = document.getElementById('password-eye');
        if (input.type === 'password') {
            input.type = 'text';
            eye.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            eye.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }

    function toggleSidebar() {
        document.querySelector('.sidebar').classList.toggle('active');
        document.querySelector('.sidebar-overlay').classList.toggle('active');
    }

    document.addEventListener('click', function(e) {
        const sidebar = document.querySelector('.sidebar');
        const toggle  = document.querySelector('.sidebar-toggle');
        if (window.innerWidth <= 768 && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
            sidebar.classList.remove('active');
            document.querySelector('.sidebar-overlay').classList.remove('active');
        }
    });

    /* Auto-dismiss alerts after 7s */
    ['successAlert', 'errorAlert'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        setTimeout(() => {
            el.style.transition = 'opacity 0.5s ease';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 500);
        }, 7000);
    });
</script>

</body>
</html>