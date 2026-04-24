<?php
session_start();
require '../db.php';

/* -------------------------
   SESSION PROTECTION
--------------------------*/
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit();
}

$patient_id = $_SESSION['user_id'];

/* -------------------------
   HANDLE PROFILE PICTURE UPLOAD
--------------------------*/
if (isset($_POST['upload_pic'])) {

    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === 0) {

        $allowed  = ['jpg', 'jpeg', 'png'];
        $fileName = $_FILES['profile_pic']['name'];
        $fileTmp  = $_FILES['profile_pic']['tmp_name'];
        $fileSize = $_FILES['profile_pic']['size'];
        $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($fileExt, $allowed)) {
            $upload_error = "Only JPG, JPEG, PNG files are allowed.";
        } elseif ($fileSize > 2 * 1024 * 1024) {
            $upload_error = "File too large. Maximum size is 2MB.";
        } else {
            $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
            $stmt->execute([$patient_id]);
            $oldPic = $stmt->fetchColumn();

            $newFileName = "user_" . $patient_id . "_" . time() . "." . $fileExt;
            $uploadPath  = "../static/uploads/profile_pictures/" . $newFileName;

            if (move_uploaded_file($fileTmp, $uploadPath)) {
                if ($oldPic && $oldPic !== 'default.png') {
                    $oldPath = "../static/uploads/profile_pictures/" . $oldPic;
                    if (file_exists($oldPath)) unlink($oldPath);
                }
                $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                $stmt->execute([$newFileName, $patient_id]);
                $upload_success = "Profile picture updated successfully!";
            } else {
                $upload_error = "Upload failed. Please try again.";
            }
        }
    }
}

/* -------------------------
   HANDLE EDIT DETAILS
--------------------------*/
if (isset($_POST['edit_details'])) {
    $first_name = trim($_POST['first_name']);
    $last_name  = trim($_POST['last_name']);
    $phone      = trim($_POST['phone']);
    $gender     = trim($_POST['gender']);
    $dob_input  = trim($_POST['date_of_birth']);

    $edit_errors = [];

    if (empty($first_name))  $edit_errors[] = "First name is required.";
    if (empty($last_name))   $edit_errors[] = "Last name is required.";
    if (empty($phone))       $edit_errors[] = "Phone number is required.";
    if (!in_array($gender, ['male','female','other'])) $edit_errors[] = "Invalid gender selected.";

    // Validate DOB format
    $dob_obj = DateTime::createFromFormat('Y-m-d', $dob_input);
    if (!$dob_obj) {
        $edit_errors[] = "Invalid date of birth.";
    }

    if (empty($edit_errors)) {
        $stmt = $conn->prepare("
            UPDATE users
            SET first_name = ?, last_name = ?, phone = ?, gender = ?, date_of_birth = ?
            WHERE id = ?
        ");
        $stmt->execute([$first_name, $last_name, $phone, $gender, $dob_input, $patient_id]);
        $edit_success = "Profile details updated successfully!";
    } else {
        $edit_error = implode(" ", $edit_errors);
    }
}

/* -------------------------
   HANDLE CHANGE PASSWORD
--------------------------*/
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password     = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    $pass_errors = [];

    if (empty($current_password)) $pass_errors[] = "Current password is required.";
    if (empty($new_password))     $pass_errors[] = "New password is required.";
    if (strlen($new_password) < 8) $pass_errors[] = "New password must be at least 8 characters.";
    if ($new_password !== $confirm_password) $pass_errors[] = "Passwords do not match.";

    if (empty($pass_errors)) {
        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$patient_id]);
        $stored_hash = $stmt->fetchColumn();

        if (!password_verify($current_password, $stored_hash)) {
            $pass_error = "Current password is incorrect.";
        } else {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$new_hash, $patient_id]);
            $pass_success = "Password changed successfully!";
        }
    } else {
        $pass_error = implode(" ", $pass_errors);
    }
}

/* -------------------------
   FETCH PATIENT DATA
--------------------------*/
$stmt = $conn->prepare("
    SELECT first_name, last_name, email, phone, gender, date_of_birth, profile_picture
    FROM users
    WHERE id = ?
");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$_SESSION['first_name'] = $patient['first_name'];

$full_name = htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']);
$email     = htmlspecialchars($patient['email']);
$phone     = htmlspecialchars($patient['phone']);
$gender    = htmlspecialchars($patient['gender']);

$dob           = new DateTime($patient['date_of_birth']);
$today         = new DateTime();
$age           = $today->diff($dob)->y;
$dob_formatted = $dob->format('d M Y');
$dob_value     = $dob->format('Y-m-d'); // for input[type=date]

$profile_picture = htmlspecialchars($patient['profile_picture'] ?? 'default.png');
$pic_src = "../static/uploads/profile_pictures/" . $profile_picture;

/* Initials fallback */
$initials = strtoupper(substr($patient['first_name'], 0, 1) . substr($patient['last_name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — ApexCare Patient Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../static/patient_sidebar.css">
    <link rel="stylesheet" href="../static/patientprofile.css">
    <style>
        /* ── Modal Overlay ───────────────────────────────── */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(10, 20, 40, 0.55);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.open {
            display: flex;
        }

        /* ── Modal Box ───────────────────────────────────── */
        .modal-box {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 24px 60px rgba(0,0,0,0.18);
            width: 100%;
            max-width: 480px;
            padding: 0;
            overflow: hidden;
            animation: modalSlideIn 0.28s cubic-bezier(0.34,1.2,0.64,1);
        }
        @keyframes modalSlideIn {
            from { transform: translateY(28px) scale(0.97); opacity: 0; }
            to   { transform: translateY(0) scale(1); opacity: 1; }
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 22px 26px 18px;
            border-bottom: 1px solid #f0f3f8;
        }
        .modal-header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .modal-header-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
        }
        .modal-header-icon.teal {
            background: linear-gradient(135deg, var(--teal, #0e9488), var(--teal-light, #14b8a6));
            color: #fff;
        }
        .modal-header-icon.navy {
            background: linear-gradient(135deg, #1e3a5f, #2d5a8e);
            color: #fff;
        }
        .modal-title {
            font-size: 16px;
            font-weight: 700;
            color: #1a2b4a;
        }
        .modal-sub {
            font-size: 12px;
            color: #8898a8;
            margin-top: 1px;
        }
        .modal-close {
            background: #f4f6fa;
            border: none;
            border-radius: 8px;
            width: 32px;
            height: 32px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6b7a90;
            font-size: 14px;
            transition: background 0.18s, color 0.18s;
        }
        .modal-close:hover {
            background: #ffe4e4;
            color: #e03e3e;
        }

        .modal-body {
            padding: 22px 26px;
        }

        /* ── Form Fields ─────────────────────────────────── */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 14px;
        }
        .form-group.full {
            grid-column: 1 / -1;
        }
        .form-group label {
            font-size: 12px;
            font-weight: 600;
            color: #4a5c72;
            letter-spacing: 0.3px;
        }
        .form-group input,
        .form-group select {
            border: 1.5px solid #e2e8f2;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 14px;
            color: #1a2b4a;
            background: #f8fafd;
            transition: border-color 0.18s, box-shadow 0.18s;
            outline: none;
            width: 100%;
            box-sizing: border-box;
        }
        .form-group input:focus,
        .form-group select:focus {
            border-color: var(--teal, #0e9488);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(14,148,136,0.12);
        }
        .form-group input[readonly] {
            background: #f0f3f8;
            color: #8898a8;
            cursor: not-allowed;
        }

        /* Password input wrapper */
        .pass-input-wrap {
            position: relative;
        }
        .pass-input-wrap input {
            padding-right: 42px;
        }
        .pass-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #8898a8;
            font-size: 14px;
            padding: 0;
            display: flex;
            align-items: center;
            transition: color 0.15s;
        }
        .pass-toggle:hover { color: var(--teal, #0e9488); }

        /* Password strength bar */
        .strength-bar-wrap {
            margin-top: 6px;
        }
        .strength-bar-track {
            height: 4px;
            background: #e2e8f2;
            border-radius: 99px;
            overflow: hidden;
        }
        .strength-bar-fill {
            height: 100%;
            border-radius: 99px;
            width: 0%;
            transition: width 0.3s ease, background 0.3s ease;
        }
        .strength-label {
            font-size: 11px;
            margin-top: 4px;
            font-weight: 600;
        }

        /* ── Alert inside modal ──────────────────────────── */
        .modal-alert {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 14px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 16px;
        }
        .modal-alert.success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .modal-alert.error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* ── Modal Footer ────────────────────────────────── */
        .modal-footer {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            padding: 16px 26px 22px;
            border-top: 1px solid #f0f3f8;
        }
        .btn-modal-cancel {
            padding: 10px 20px;
            border-radius: 10px;
            border: 1.5px solid #e2e8f2;
            background: #fff;
            color: #4a5c72;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.18s;
        }
        .btn-modal-cancel:hover { background: #f4f6fa; }

        .btn-modal-save {
            padding: 10px 22px;
            border-radius: 10px;
            border: none;
            background: linear-gradient(135deg, var(--teal, #0e9488), var(--teal-light, #14b8a6));
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: opacity 0.18s, transform 0.15s;
            box-shadow: 0 4px 14px rgba(14,148,136,0.28);
        }
        .btn-modal-save:hover { opacity: 0.92; transform: translateY(-1px); }
        .btn-modal-save:active { transform: translateY(0); }
        .btn-modal-save.navy-btn {
            background: linear-gradient(135deg, #1e3a5f, #2d5a8e);
            box-shadow: 0 4px 14px rgba(30,58,95,0.22);
        }

        /* Spinner inside button */
        .btn-spinner {
            display: none;
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255,255,255,0.4);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Auto-open if PHP set a flag */
        .modal-overlay[data-auto-open="true"] {
            display: flex;
        }
    </style>
</head>
<body>

<div class="layout">

    <?php include "../static/includes/patient_sidebar.php"; ?>

    <main class="content">

        <!-- ── Upload / Edit / Password Alerts ───────────── -->
        <?php if (isset($upload_success)): ?>
            <div class="upload-alert success">
                <i class="fas fa-circle-check"></i>
                <?php echo htmlspecialchars($upload_success); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($upload_error)): ?>
            <div class="upload-alert error">
                <i class="fas fa-circle-exclamation"></i>
                <?php echo htmlspecialchars($upload_error); ?>
            </div>
        <?php endif; ?>

        <!-- ── Profile Hero Card ─────────────────────────── -->
        <div class="profile-hero">
            <div class="profile-banner"></div>

            <div class="profile-hero-body">

                <div class="avatar-wrap">
                    <div class="avatar-ring" id="avatarRing" onclick="togglePhotoPanel()">
                        <img src="<?php echo $pic_src; ?>"
                             alt="<?php echo $full_name; ?>"
                             onerror="this.style.display='none'; document.getElementById('avatarFallback').style.display='flex';">
                        <div id="avatarFallback" style="display:none;width:100%;height:100%;background:linear-gradient(135deg,var(--teal),var(--teal-light));align-items:center;justify-content:center;font-size:28px;font-weight:800;color:white;font-family:'Plus Jakarta Sans',sans-serif;">
                            <?php echo $initials; ?>
                        </div>
                        <div class="avatar-overlay">
                            <i class="fas fa-camera"></i>
                        </div>
                    </div>

                    <div class="patient-id-badge">
                        <i class="fas fa-id-badge"></i>
                        Patient ID: P<?php echo str_pad($patient_id, 4, '0', STR_PAD_LEFT); ?>
                    </div>
                </div>

                <div class="profile-name"><?php echo $full_name; ?></div>
                <div class="profile-role-chips">
                    <span class="profile-chip teal-chip">
                        <i class="fas fa-user-injured"></i> Patient
                    </span>
                    <span class="profile-chip">
                        <i class="fas fa-venus-mars"></i>
                        <?php echo ucfirst($gender); ?>
                    </span>
                    <span class="profile-chip">
                        <i class="fas fa-cake-candles"></i>
                        <?php echo $age; ?> years old
                    </span>
                </div>

            </div>
        </div>

        <!-- ── Main Content Grid ─────────────────────────── -->
        <div class="profile-grid">

            <!-- Personal Details -->
            <div class="p-card">
                <div class="p-card-head">
                    <div class="p-card-head-left">
                        <span class="p-card-icon teal"><i class="fas fa-id-card"></i></span>
                        <div>
                            <div class="p-card-title">Personal Details</div>
                            <div class="p-card-sub">Your account information</div>
                        </div>
                    </div>
                </div>
                <div class="p-card-body">
                    <div class="detail-list">

                        <div class="detail-row">
                            <div class="detail-icon-wrap"><i class="fas fa-user"></i></div>
                            <div>
                                <div class="detail-label">Full Name</div>
                                <div class="detail-value"><?php echo $full_name; ?></div>
                            </div>
                        </div>

                        <div class="detail-row">
                            <div class="detail-icon-wrap"><i class="fas fa-envelope"></i></div>
                            <div>
                                <div class="detail-label">Email Address</div>
                                <div class="detail-value mono"><?php echo $email; ?></div>
                            </div>
                        </div>

                        <div class="detail-row">
                            <div class="detail-icon-wrap"><i class="fas fa-phone"></i></div>
                            <div>
                                <div class="detail-label">Phone Number</div>
                                <div class="detail-value mono"><?php echo $phone; ?></div>
                            </div>
                        </div>

                        <div class="detail-row">
                            <div class="detail-icon-wrap"><i class="fas fa-venus-mars"></i></div>
                            <div>
                                <div class="detail-label">Gender</div>
                                <div class="detail-value"><?php echo ucfirst($gender); ?></div>
                            </div>
                        </div>

                        <div class="detail-row">
                            <div class="detail-icon-wrap"><i class="fas fa-calendar-days"></i></div>
                            <div>
                                <div class="detail-label">Date of Birth</div>
                                <div class="detail-value">
                                    <?php echo $dob_formatted; ?>
                                    <span style="color:var(--text-muted);font-size:12px;font-weight:400;">(<?php echo $age; ?> yrs)</span>
                                </div>
                            </div>
                        </div>


                    </div>
                </div>
            </div>

            <!-- Settings & Actions -->
            <div class="p-card settings-card">
                <div class="p-card-head">
                    <div class="p-card-head-left">
                        <span class="p-card-icon navy"><i class="fas fa-gear"></i></span>
                        <div>
                            <div class="p-card-title">Profile Settings</div>
                            <div class="p-card-sub">Manage your account</div>
                        </div>
                    </div>
                </div>
                <div class="p-card-body">
                    <div class="action-btn-row">

                        <!-- Edit Details -->
                        <button class="action-btn edit" onclick="openModal('editModal')">
                            <div class="action-btn-icon"><i class="fas fa-pen"></i></div>
                            <span>Edit Details</span>
                            <i class="fas fa-chevron-right action-btn-arrow"></i>
                        </button>

                        <!-- Change Password -->
                        <button class="action-btn pass" onclick="openModal('passModal')">
                            <div class="action-btn-icon"><i class="fas fa-lock"></i></div>
                            <span>Change Password</span>
                            <i class="fas fa-chevron-right action-btn-arrow"></i>
                        </button>

                        <!-- Change Photo -->
                        <button class="action-btn photo" onclick="togglePhotoPanel()">
                            <div class="action-btn-icon"><i class="fas fa-camera"></i></div>
                            <span>Change Profile Photo</span>
                            <i class="fas fa-chevron-right action-btn-arrow" id="photoArrow"></i>
                        </button>

                        <!-- Upload panel -->
                        <div class="photo-upload-panel" id="photoPanel">
                            <form method="POST" enctype="multipart/form-data">
                                <label>Select a new profile photo (JPG, PNG · max 2MB)</label>
                                <div class="file-input-wrap">
                                    <input type="file" name="profile_pic" accept=".jpg,.jpeg,.png" required>
                                    <button type="submit" name="upload_pic" class="btn-upload-submit">
                                        <i class="fas fa-cloud-arrow-up"></i>
                                        Upload
                                    </button>
                                </div>
                                <div class="upload-hint">
                                    <i class="fas fa-circle-info"></i>
                                    Click the avatar photo above for a quick shortcut
                                </div>
                            </form>
                        </div>

                    </div>
                </div>
            </div>

        </div>

    </main>
</div>

<!-- ══════════════════════════════════════════════
     MODAL 1 — Edit Details
══════════════════════════════════════════════ -->
<div class="modal-overlay" id="editModal"
     <?php if (isset($edit_success) || isset($edit_error)): ?>data-auto-open="true"<?php endif; ?>>
    <div class="modal-box">

        <div class="modal-header">
            <div class="modal-header-left">
                <div class="modal-header-icon teal"><i class="fas fa-pen"></i></div>
                <div>
                    <div class="modal-title">Edit Personal Details</div>
                    <div class="modal-sub">Update your profile information</div>
                </div>
            </div>
            <button class="modal-close" onclick="closeModal('editModal')" title="Close">
                <i class="fas fa-xmark"></i>
            </button>
        </div>

        <form method="POST">
            <div class="modal-body">

                <?php if (isset($edit_success)): ?>
                    <div class="modal-alert success">
                        <i class="fas fa-circle-check"></i>
                        <?php echo htmlspecialchars($edit_success); ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($edit_error)): ?>
                    <div class="modal-alert error">
                        <i class="fas fa-circle-exclamation"></i>
                        <?php echo htmlspecialchars($edit_error); ?>
                    </div>
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name"
                               value="<?php echo htmlspecialchars($patient['first_name']); ?>"
                               placeholder="First name" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name"
                               value="<?php echo htmlspecialchars($patient['last_name']); ?>"
                               placeholder="Last name" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email_ro">Email Address</label>
                    <input type="email" id="email_ro"
                           value="<?php echo $email; ?>"
                           readonly title="Email cannot be changed">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone"
                               value="<?php echo htmlspecialchars($patient['phone']); ?>"
                               placeholder="+254 7XX XXX XXX" required>
                    </div>
                    <div class="form-group">
                        <label for="gender">Gender</label>
                        <select id="gender" name="gender" required>
                            <option value="male"   <?php if ($patient['gender'] === 'male')   echo 'selected'; ?>>Male</option>
                            <option value="female" <?php if ($patient['gender'] === 'female') echo 'selected'; ?>>Female</option>
                            <option value="other"  <?php if ($patient['gender'] === 'other')  echo 'selected'; ?>>Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="date_of_birth">Date of Birth</label>
                    <input type="date" id="date_of_birth" name="date_of_birth"
                           value="<?php echo $dob_value; ?>"
                           max="<?php echo date('Y-m-d'); ?>" required>
                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn-modal-cancel" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" name="edit_details" class="btn-modal-save" id="editSaveBtn">
                    <div class="btn-spinner" id="editSpinner"></div>
                    <i class="fas fa-floppy-disk" id="editIcon"></i>
                    Save Changes
                </button>
            </div>
        </form>

    </div>
</div>

<!-- ══════════════════════════════════════════════
     MODAL 2 — Change Password
══════════════════════════════════════════════ -->
<div class="modal-overlay" id="passModal"
     <?php if (isset($pass_success) || isset($pass_error)): ?>data-auto-open="true"<?php endif; ?>>
    <div class="modal-box">

        <div class="modal-header">
            <div class="modal-header-left">
                <div class="modal-header-icon navy"><i class="fas fa-lock"></i></div>
                <div>
                    <div class="modal-title">Change Password</div>
                    <div class="modal-sub">Keep your account secure</div>
                </div>
            </div>
            <button class="modal-close" onclick="closeModal('passModal')" title="Close">
                <i class="fas fa-xmark"></i>
            </button>
        </div>

        <form method="POST" onsubmit="handlePassSubmit(event)">
            <div class="modal-body">

                <?php if (isset($pass_success)): ?>
                    <div class="modal-alert success">
                        <i class="fas fa-circle-check"></i>
                        <?php echo htmlspecialchars($pass_success); ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($pass_error)): ?>
                    <div class="modal-alert error">
                        <i class="fas fa-circle-exclamation"></i>
                        <?php echo htmlspecialchars($pass_error); ?>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <div class="pass-input-wrap">
                        <input type="password" id="current_password" name="current_password"
                               placeholder="Enter current password" required>
                        <button type="button" class="pass-toggle" onclick="togglePass('current_password', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <div class="pass-input-wrap">
                        <input type="password" id="new_password" name="new_password"
                               placeholder="Min. 8 characters" required
                               oninput="checkStrength(this.value)">
                        <button type="button" class="pass-toggle" onclick="togglePass('new_password', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <!-- Strength bar -->
                    <div class="strength-bar-wrap">
                        <div class="strength-bar-track">
                            <div class="strength-bar-fill" id="strengthFill"></div>
                        </div>
                        <div class="strength-label" id="strengthLabel" style="color:#aab4c4;"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <div class="pass-input-wrap">
                        <input type="password" id="confirm_password" name="confirm_password"
                               placeholder="Repeat new password" required
                               oninput="checkMatch()">
                        <button type="button" class="pass-toggle" onclick="togglePass('confirm_password', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="strength-label" id="matchLabel" style="margin-top:4px;"></div>
                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn-modal-cancel" onclick="closeModal('passModal')">Cancel</button>
                <button type="submit" name="change_password" class="btn-modal-save navy-btn" id="passSaveBtn">
                    <div class="btn-spinner" id="passSpinner"></div>
                    <i class="fas fa-shield-halved" id="passIcon"></i>
                    Update Password
                </button>
            </div>
        </form>

    </div>
</div>

<script>
    /* ── Photo panel toggle ───────────────────────── */
    function togglePhotoPanel() {
        const panel = document.getElementById('photoPanel');
        const arrow = document.getElementById('photoArrow');
        panel.classList.toggle('open');
        arrow.style.transform = panel.classList.contains('open') ? 'rotate(90deg)' : 'rotate(0deg)';
        arrow.style.transition = 'transform 0.25s ease';
        if (panel.classList.contains('open')) {
            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    /* ── Modal open/close ────────────────────────── */
    function openModal(id) {
        document.getElementById(id).classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeModal(id) {
        document.getElementById(id).classList.remove('open');
        document.body.style.overflow = '';
    }

    // Close on overlay click
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) closeModal(this.id);
        });
    });

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.open').forEach(m => closeModal(m.id));
        }
    });

    /* ── Password visibility toggle ──────────────── */
    function togglePass(inputId, btn) {
        const input = document.getElementById(inputId);
        const icon  = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }

    /* ── Password strength meter ─────────────────── */
    function checkStrength(val) {
        const fill  = document.getElementById('strengthFill');
        const label = document.getElementById('strengthLabel');

        let score = 0;
        if (val.length >= 8)          score++;
        if (/[A-Z]/.test(val))        score++;
        if (/[0-9]/.test(val))        score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        const levels = [
            { pct: '20%',  color: '#ef4444', text: 'Very Weak'  },
            { pct: '40%',  color: '#f97316', text: 'Weak'       },
            { pct: '65%',  color: '#eab308', text: 'Fair'       },
            { pct: '85%',  color: '#22c55e', text: 'Strong'     },
            { pct: '100%', color: '#0e9488', text: 'Very Strong' },
        ];

        if (val.length === 0) {
            fill.style.width = '0%';
            label.textContent = '';
            return;
        }

        const lvl = levels[Math.min(score, 4)];
        fill.style.width     = lvl.pct;
        fill.style.background = lvl.color;
        label.textContent    = lvl.text;
        label.style.color    = lvl.color;
    }

    /* ── Password match check ────────────────────── */
    function checkMatch() {
        const np = document.getElementById('new_password').value;
        const cp = document.getElementById('confirm_password').value;
        const lbl = document.getElementById('matchLabel');
        if (cp.length === 0) { lbl.textContent = ''; return; }
        if (np === cp) {
            lbl.textContent = '✓ Passwords match';
            lbl.style.color = '#22c55e';
        } else {
            lbl.textContent = '✗ Passwords do not match';
            lbl.style.color = '#ef4444';
        }
    }

    /* ── Spinner on password submit ──────────────── */
    function handlePassSubmit(e) {
        const np = document.getElementById('new_password').value;
        const cp = document.getElementById('confirm_password').value;
        if (np !== cp) {
            e.preventDefault();
            return;
        }
        document.getElementById('passSpinner').style.display = 'block';
        document.getElementById('passIcon').style.display    = 'none';
    }

    /* ── Spinner on edit submit ──────────────────── */
    document.querySelector('#editModal form').addEventListener('submit', function() {
        document.getElementById('editSpinner').style.display = 'block';
        document.getElementById('editIcon').style.display    = 'none';
    });
</script>

</body>
</html>