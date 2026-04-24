<?php
session_start();

require_once dirname(__DIR__) . '../db.php';
require_once 'adminauth.php';

$auth = new Auth($conn);
$auth->redirectIfNotLoggedIn();

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $first_name     = trim($_POST['first_name']);
    $last_name      = trim($_POST['last_name']);
    $email          = trim($_POST['email']);
    $national_id    = trim($_POST['national_id']);
    $phone          = trim($_POST['phone']);
    $date_of_birth  = $_POST['date_of_birth'];
    $gender         = $_POST['gender'];
    $password       = $_POST['password'];
    $specialization = trim($_POST['specialization']);
    $status         = $_POST['status'];

    if (
        empty($first_name) || empty($last_name) || empty($email) ||
        empty($national_id) || empty($phone) || empty($date_of_birth) ||
        empty($gender) || empty($password) || empty($specialization)
    ) {
        $error = "All fields are required.";
    } else {

        $check = $conn->prepare("
            SELECT id FROM users 
            WHERE email = :email OR national_id = :national_id
        ");
        $check->execute([':email' => $email, ':national_id' => $national_id]);

        if ($check->rowCount() > 0) {
            $error = "A user with this Email or National ID already exists.";
        } else {
            try {
                $conn->beginTransaction();

                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("
                    INSERT INTO users 
                    (first_name, last_name, email, national_id, phone, date_of_birth, gender, password, role)
                    VALUES 
                    (:first_name, :last_name, :email, :national_id, :phone, :dob, :gender, :password, 'doctor')
                ");
                $stmt->execute([
                    ':first_name'  => $first_name,
                    ':last_name'   => $last_name,
                    ':email'       => $email,
                    ':national_id' => $national_id,
                    ':phone'       => $phone,
                    ':dob'         => $date_of_birth,
                    ':gender'      => $gender,
                    ':password'    => $hashedPassword
                ]);

                $doctor_id = $conn->lastInsertId();

                $profile = $conn->prepare("
                    INSERT INTO doctor_profiles (doctor_id, specialization, status)
                    VALUES (:doctor_id, :specialization, :status)
                ");
                $profile->execute([
                    ':doctor_id'      => $doctor_id,
                    ':specialization' => $specialization,
                    ':status'         => $status
                ]);

                $conn->commit();
                header("Location: adminregisterdoc.php?success=Doctor registered successfully");
                exit();

            } catch (Exception $e) {
                $conn->rollBack();
                $error = "Something went wrong. Please try again.";
            }
        }
    }
}

$success = $_GET['success'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Doctor — ApexCare Admin</title>
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
            <h2>
                <span class="page-header-icon"><i class="fas fa-user-md"></i></span>
                Register New Doctor
            </h2>
            <p class="page-header-sub">Add a new doctor to the ApexCare system</p>
        </div>
        <a href="adminregisterdoc.php" class="btn-back">
            <i class="fas fa-arrow-left"></i>
            Back to Management
        </a>
    </div>

    <!-- ── Alerts ──────────────────────────────────── -->
    <?php if (!empty($error)): ?>
        <div class="alert alert-error">
            <i class="fas fa-circle-exclamation"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success">
            <i class="fas fa-circle-check"></i>
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <form method="POST" novalidate>

        <!-- ── Section 1: Personal Information ─────── -->
        <div class="form-card">
            <div class="form-card-header">
                <span class="section-icon blue"><i class="fas fa-id-card"></i></span>
                <div>
                    <h3>Personal Information</h3>
                    <p>Basic identity and contact details</p>
                </div>
            </div>
            <div class="form-card-body">
                <div class="form-grid">

                    <div class="field-group">
                        <label class="field-label" for="first_name">
                            <i class="fas fa-user"></i> First Name <span class="required-dot"></span>
                        </label>
                        <input
                            class="field-input"
                            type="text"
                            id="first_name"
                            name="first_name"
                            placeholder="e.g. James"
                            value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
                            required
                        >
                    </div>

                    <div class="field-group">
                        <label class="field-label" for="last_name">
                            <i class="fas fa-user"></i> Last Name <span class="required-dot"></span>
                        </label>
                        <input
                            class="field-input"
                            type="text"
                            id="last_name"
                            name="last_name"
                            placeholder="e.g. Mwangi"
                            value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
                            required
                        >
                    </div>

                    <div class="field-group">
                        <label class="field-label" for="email">
                            <i class="fas fa-envelope"></i> Email Address <span class="required-dot"></span>
                        </label>
                        <input
                            class="field-input"
                            type="email"
                            id="email"
                            name="email"
                            placeholder="doctor@apexcare.com"
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                            required
                        >
                    </div>

                    <div class="field-group">
                        <label class="field-label" for="national_id">
                            <i class="fas fa-fingerprint"></i> National ID <span class="required-dot"></span>
                        </label>
                        <input
                            class="field-input"
                            type="text"
                            id="national_id"
                            name="national_id"
                            placeholder="e.g. 12345678"
                            value="<?php echo htmlspecialchars($_POST['national_id'] ?? ''); ?>"
                            required
                        >
                    </div>

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

                    <div class="field-group">
                        <label class="field-label" for="date_of_birth">
                            <i class="fas fa-calendar"></i> Date of Birth <span class="required-dot"></span>
                        </label>
                        <input
                            class="field-input"
                            type="date"
                            id="date_of_birth"
                            name="date_of_birth"
                            value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>"
                            required
                        >
                    </div>

                    <div class="field-group">
                        <label class="field-label" for="gender">
                            <i class="fas fa-venus-mars"></i> Gender <span class="required-dot"></span>
                        </label>
                        <div class="select-wrap">
                            <select class="field-select" id="gender" name="gender" required>
                                <option value="">Select gender</option>
                                <option value="male"   <?php echo (($_POST['gender'] ?? '') === 'male')   ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo (($_POST['gender'] ?? '') === 'female') ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                    </div>

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
                            <button type="button" class="password-toggle" onclick="togglePassword()" aria-label="Toggle password visibility">
                                <i class="fas fa-eye" id="password-eye"></i>
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- ── Section 2: Professional Information ─── -->
        <div class="form-card">
            <div class="form-card-header">
                <span class="section-icon orange"><i class="fas fa-stethoscope"></i></span>
                <div>
                    <h3>Professional Information</h3>
                    <p>Specialization and employment status</p>
                </div>
            </div>
            <div class="form-card-body">
                <div class="form-grid">

                    <div class="field-group field-full">
                        <label class="field-label" for="specialization">
                            <i class="fas fa-microscope"></i> Specialization <span class="required-dot"></span>
                        </label>
                        <input
                            class="field-input"
                            type="text"
                            id="specialization"
                            name="specialization"
                            placeholder="e.g. Cardiology, Pediatrics, General Practice"
                            value="<?php echo htmlspecialchars($_POST['specialization'] ?? ''); ?>"
                            required
                        >
                    </div>

                    <div class="field-group">
                        <label class="field-label" for="status">
                            <i class="fas fa-circle-dot"></i> Employment Status
                        </label>
                        <div class="select-wrap">
                            <select class="field-select" id="status" name="status">
                                <option value="working" <?php echo (($_POST['status'] ?? 'working') === 'working') ? 'selected' : ''; ?>>Working</option>
                                <option value="onleave" <?php echo (($_POST['status'] ?? '') === 'onleave') ? 'selected' : ''; ?>>On Leave</option>
                                <option value="absent"  <?php echo (($_POST['status'] ?? '') === 'absent')  ? 'selected' : ''; ?>>Absent</option>
                            </select>
                        </div>
                    </div>

                </div>
            </div>

            <!-- ── Form Actions ───────────────────── -->
            <div class="form-actions">
                <button type="reset" class="btn-reset">
                    <i class="fas fa-rotate-left"></i>
                    Clear Form
                </button>
                <button type="submit" class="btn-submit">
                    Register Doctor
                    <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>

    </form>
</div>

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
</script>

</body>
</html>