<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: login.php");
    exit();
}

$doctorId = $_SESSION['user_id'];
$success  = '';
$error    = '';

/* ── FETCH DOCTOR ────────────────────────────────── */
$stmt = $conn->prepare("
    SELECT u.*, d.specialization, d.status AS work_status
    FROM users u
    LEFT JOIN doctor_profiles d ON u.id = d.doctor_id
    WHERE u.id = :id
");
$stmt->execute([':id' => $doctorId]);
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doctor) die("Doctor not found.");

/* ── HANDLE SUBMISSION ───────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $firstName      = trim($_POST['first_name']    ?? '');
    $lastName       = trim($_POST['last_name']     ?? '');
    $email          = trim($_POST['email']         ?? '');
    $phone          = trim($_POST['phone']         ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $workStatus     = $_POST['status']             ?? 'working';

    $currentPw  = $_POST['current_password']  ?? '';
    $newPw      = $_POST['new_password']       ?? '';
    $confirmPw  = $_POST['confirm_password']   ?? '';

    try {
        $conn->beginTransaction();

        /* Update users */
        $conn->prepare("
            UPDATE users
            SET first_name = :fname,
                last_name  = :lname,
                email      = :email,
                phone      = :phone
            WHERE id = :id
        ")->execute([
            ':fname' => $firstName,
            ':lname' => $lastName,
            ':email' => $email,
            ':phone' => $phone,
            ':id'    => $doctorId,
        ]);

        /* Upsert doctor_profiles */
        $exists = $conn->prepare("SELECT id FROM doctor_profiles WHERE doctor_id = :id");
        $exists->execute([':id' => $doctorId]);

        if ($exists->fetch()) {
            $conn->prepare("
                UPDATE doctor_profiles
                SET specialization = :spec, status = :status
                WHERE doctor_id = :id
            ")->execute([':spec' => $specialization, ':status' => $workStatus, ':id' => $doctorId]);
        } else {
            $conn->prepare("
                INSERT INTO doctor_profiles (doctor_id, specialization, status)
                VALUES (:id, :spec, :status)
            ")->execute([':id' => $doctorId, ':spec' => $specialization, ':status' => $workStatus]);
        }

        /* Password change */
        if (!empty($currentPw)) {
            if (!password_verify($currentPw, $doctor['password'])) {
                throw new Exception("Current password is incorrect.");
            }
            if (empty($newPw)) {
                throw new Exception("Please enter a new password.");
            }
            if ($newPw !== $confirmPw) {
                throw new Exception("New passwords do not match.");
            }
            if (strlen($newPw) < 8) {
                throw new Exception("New password must be at least 8 characters.");
            }
            $conn->prepare("UPDATE users SET password = :pw WHERE id = :id")
                 ->execute([':pw' => password_hash($newPw, PASSWORD_DEFAULT), ':id' => $doctorId]);
        }

        $conn->commit();
        header("Location: dsettings.php?saved=1");
        exit();

    } catch (Exception $e) {
        $conn->rollBack();
        $error = $e->getMessage();
    }

    /* Refresh doctor data after failed attempt */
    $stmt->execute([':id' => $doctorId]);
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
}

$saved = isset($_GET['saved']);

/* ── DISPLAY HELPERS ─────────────────────────────── */
$fullName   = htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']);
$initials   = strtoupper(substr($doctor['first_name'], 0, 1) . substr($doctor['last_name'], 0, 1));
$workStatus = $doctor['work_status'] ?? 'working';

$statusLabel = ['working' => 'Working', 'onleave' => 'On Leave', 'absent' => 'Absent'];
$statusDot   = ['working' => '#16a34a', 'onleave' => '#f59e0b', 'absent' => '#dc2626'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings — ApexCare</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../static/doctor_sidebar.css">
    <link rel="stylesheet" href="../static/dsettings.css">
</head>
<body>

<div class="layout">
    <?php include "../static/includes/doctor_sidebar.php"; ?>

    <main class="content">

        <!-- ── Page Header ─────────────────────────────── -->
        <div class="page-header">
            <div>
                <h1>
                    <span class="header-icon"><i class="fas fa-gear"></i></span>
                    Settings
                </h1>
                <p class="page-header-sub">Manage your profile, credentials and working status</p>
            </div>
            <div class="header-badge">
                <i class="fas fa-calendar-day"></i>
                <?php echo date('d M Y'); ?>
            </div>
        </div>

        <!-- ── Alerts ──────────────────────────────────── -->
        <?php if ($saved): ?>
            <div class="alert success" id="successAlert">
                <i class="fas fa-circle-check"></i>
                Settings updated successfully.
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert error" id="errorAlert">
                <i class="fas fa-circle-exclamation"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="settingsForm">

        <!-- ── Main layout ──────────────────────────────── -->
        <div class="settings-grid-layout">

            <!-- ── LEFT: Profile Aside ────────────────── -->
            <div class="profile-aside">
                <div class="profile-banner">
                    <div class="profile-avatar-wrap">
                        <div class="profile-avatar"><?php echo $initials; ?></div>
                    </div>
                </div>

                <div class="profile-aside-body">
                    <div class="profile-name"><?php echo $fullName; ?></div>
                    <div class="profile-spec"><?php echo htmlspecialchars($doctor['specialization'] ?? 'No specialization set'); ?></div>

                    <div class="status-badge <?php echo $workStatus; ?>">
                        <span class="status-dot"></span>
                        <?php echo $statusLabel[$workStatus] ?? 'Unknown'; ?>
                    </div>

                    <div class="profile-divider"></div>

                    <div class="profile-info-row">
                        <div class="pinfo-icon teal"><i class="fas fa-envelope"></i></div>
                        <div>
                            <div class="pinfo-label">Email</div>
                            <div class="pinfo-value"><?php echo htmlspecialchars($doctor['email']); ?></div>
                        </div>
                    </div>

                    <div class="profile-info-row">
                        <div class="pinfo-icon purple"><i class="fas fa-phone"></i></div>
                        <div>
                            <div class="pinfo-label">Phone</div>
                            <div class="pinfo-value"><?php echo htmlspecialchars($doctor['phone'] ?: '—'); ?></div>
                        </div>
                    </div>

                    <div class="profile-info-row">
                        <div class="pinfo-icon amber"><i class="fas fa-stethoscope"></i></div>
                        <div>
                            <div class="pinfo-label">Specialization</div>
                            <div class="pinfo-value"><?php echo htmlspecialchars($doctor['specialization'] ?? '—'); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── RIGHT: Section Cards ────────────────── -->
            <div class="settings-sections">

                <!-- Profile Settings -->
                <div class="section-card">
                    <div class="section-card-head teal-bar">
                        <span class="s-icon teal"><i class="fas fa-user"></i></span>
                        <div>
                            <div class="section-title">Profile Information</div>
                            <div class="section-sub">Your name, contact and specialization</div>
                        </div>
                    </div>
                    <div class="section-card-body">

                        <div class="form-grid-2">
                            <div class="form-group">
                                <label class="f-label">
                                    <i class="fas fa-user"></i>
                                    First Name <span class="req">*</span>
                                </label>
                                <input type="text" name="first_name" class="f-input"
                                       value="<?php echo htmlspecialchars($doctor['first_name']); ?>"
                                       required>
                            </div>

                            <div class="form-group">
                                <label class="f-label">
                                    <i class="fas fa-user"></i>
                                    Last Name <span class="req">*</span>
                                </label>
                                <input type="text" name="last_name" class="f-input"
                                       value="<?php echo htmlspecialchars($doctor['last_name']); ?>"
                                       required>
                            </div>

                            <div class="form-group">
                                <label class="f-label">
                                    <i class="fas fa-envelope"></i>
                                    Email <span class="req">*</span>
                                </label>
                                <input type="email" name="email" class="f-input"
                                       value="<?php echo htmlspecialchars($doctor['email']); ?>"
                                       required>
                            </div>

                            <div class="form-group">
                                <label class="f-label">
                                    <i class="fas fa-phone"></i>
                                    Phone
                                </label>
                                <input type="text" name="phone" class="f-input"
                                       value="<?php echo htmlspecialchars($doctor['phone'] ?? ''); ?>">
                            </div>

                            <div class="form-group full">
                                <label class="f-label">
                                    <i class="fas fa-stethoscope"></i>
                                    Specialization <span class="req">*</span>
                                </label>
                                <input type="text" name="specialization" class="f-input"
                                       value="<?php echo htmlspecialchars($doctor['specialization'] ?? ''); ?>"
                                       placeholder="e.g. Cardiology, General Practice…"
                                       required>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Working Status -->
                <div class="section-card">
                    <div class="section-card-head amber-bar">
                        <span class="s-icon amber"><i class="fas fa-briefcase-medical"></i></span>
                        <div>
                            <div class="section-title">Working Status</div>
                            <div class="section-sub">Controls whether patients can book with you</div>
                        </div>
                    </div>
                    <div class="section-card-body">

                        <div class="form-group" style="max-width:280px;">
                            <label class="f-label">
                                <i class="fas fa-circle-dot"></i>
                                Current Status
                            </label>
                            <select name="status" class="f-select" id="statusSelect" onchange="updateStatusPreview()">
                                <option value="working" <?php echo $workStatus === 'working' ? 'selected' : ''; ?>>Working</option>
                                <option value="onleave" <?php echo $workStatus === 'onleave' ? 'selected' : ''; ?>>On Leave</option>
                                <option value="absent"  <?php echo $workStatus === 'absent'  ? 'selected' : ''; ?>>Absent</option>
                            </select>
                            <div class="status-preview">
                                <span class="status-dot-preview" id="statusDotPreview"
                                      style="background: <?php echo $statusDot[$workStatus] ?? '#16a34a'; ?>;"></span>
                                <span id="statusPreviewText"><?php echo $statusLabel[$workStatus] ?? 'Working'; ?></span>
                            </div>
                            <div class="field-hint">
                                <i class="fas fa-info-circle"></i>
                                Patients can only book when status is <strong>Working</strong>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Change Password -->
                <div class="section-card">
                    <div class="section-card-head red-bar">
                        <span class="s-icon red"><i class="fas fa-lock"></i></span>
                        <div>
                            <div class="section-title">Change Password</div>
                            <div class="section-sub">Leave blank to keep your current password</div>
                        </div>
                    </div>
                    <div class="section-card-body">

                        <div class="form-grid-3">
                            <div class="form-group">
                                <label class="f-label">
                                    <i class="fas fa-lock"></i>
                                    Current Password
                                </label>
                                <div class="pw-wrap">
                                    <input type="password" name="current_password"
                                           class="f-input" id="currentPw"
                                           placeholder="••••••••"
                                           autocomplete="current-password">
                                    <button type="button" class="pw-toggle" onclick="togglePw('currentPw', this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="f-label">
                                    <i class="fas fa-key"></i>
                                    New Password
                                </label>
                                <div class="pw-wrap">
                                    <input type="password" name="new_password"
                                           class="f-input" id="newPw"
                                           placeholder="Min. 8 characters"
                                           autocomplete="new-password"
                                           oninput="checkStrength(this.value)">
                                    <button type="button" class="pw-toggle" onclick="togglePw('newPw', this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="pw-strength">
                                    <div class="pw-strength-bar" id="strengthBar"></div>
                                </div>
                                <div class="pw-strength-label" id="strengthLabel"></div>
                            </div>

                            <div class="form-group">
                                <label class="f-label">
                                    <i class="fas fa-check-double"></i>
                                    Confirm Password
                                </label>
                                <div class="pw-wrap">
                                    <input type="password" name="confirm_password"
                                           class="f-input" id="confirmPw"
                                           placeholder="Repeat new password"
                                           autocomplete="new-password"
                                           oninput="checkMatch()">
                                    <button type="button" class="pw-toggle" onclick="togglePw('confirmPw', this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="field-hint" id="matchHint" style="display:none;"></div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Save Actions -->
                <div class="form-actions-card">
                    <div class="save-note">
                        <i class="fas fa-shield-halved"></i>
                        Changes are saved securely to your profile
                    </div>
                    <button type="submit" class="btn-save" id="saveBtn">
                        <i class="fas fa-floppy-disk"></i>
                        Save Changes
                    </button>
                </div>

            </div><!-- /.settings-sections -->
        </div><!-- /.settings-grid-layout -->

        </form>

    </main>
</div>

<script>
    /* ── Password visibility toggle ── */
    function togglePw(fieldId, btn) {
        const input = document.getElementById(fieldId);
        const icon  = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'fas fa-eye';
        }
    }

    /* ── Password strength ── */
    function checkStrength(val) {
        const bar   = document.getElementById('strengthBar');
        const label = document.getElementById('strengthLabel');
        if (!val) { bar.style.width = '0'; label.textContent = ''; return; }

        let score = 0;
        if (val.length >= 8)  score++;
        if (val.length >= 12) score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        const levels = [
            { w: '20%',  bg: '#dc2626', text: 'Very weak' },
            { w: '40%',  bg: '#f59e0b', text: 'Weak' },
            { w: '60%',  bg: '#d97706', text: 'Fair' },
            { w: '80%',  bg: '#16a34a', text: 'Strong' },
            { w: '100%', bg: '#0d9488', text: 'Very strong' },
        ];

        const lvl = levels[Math.min(score, 4)];
        bar.style.width      = lvl.w;
        bar.style.background = lvl.bg;
        label.textContent    = lvl.text;
        label.style.color    = lvl.bg;
    }

    /* ── Password match check ── */
    function checkMatch() {
        const newPw  = document.getElementById('newPw').value;
        const confPw = document.getElementById('confirmPw').value;
        const hint   = document.getElementById('matchHint');

        if (!confPw) { hint.style.display = 'none'; return; }

        hint.style.display = 'flex';
        if (newPw === confPw) {
            hint.innerHTML = '<i class="fas fa-circle-check" style="color:var(--green)"></i> <span style="color:var(--green);">Passwords match</span>';
        } else {
            hint.innerHTML = '<i class="fas fa-circle-xmark" style="color:var(--red)"></i> <span style="color:var(--red);">Passwords do not match</span>';
        }
    }

    /* ── Status preview dot ── */
    const statusColors = { working: '#16a34a', onleave: '#f59e0b', absent: '#dc2626' };
    const statusLabels = { working: 'Working', onleave: 'On Leave', absent: 'Absent' };

    function updateStatusPreview() {
        const val  = document.getElementById('statusSelect').value;
        document.getElementById('statusDotPreview').style.background = statusColors[val];
        document.getElementById('statusPreviewText').textContent = statusLabels[val];
    }

    /* ── Save loading ── */
    document.getElementById('settingsForm').addEventListener('submit', function () {
        const btn = document.getElementById('saveBtn');
        btn.classList.add('loading');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
    });

    /* ── Auto-dismiss alerts ── */
    ['successAlert','errorAlert'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        setTimeout(() => {
            el.style.transition = 'opacity 0.5s ease';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 500);
        }, 6000);
    });
</script>

</body>
</html>