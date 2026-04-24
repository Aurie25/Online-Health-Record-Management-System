<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "receptionist") {
    header("Location: login.php");
    exit();
}

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_user'])) {
    $first_name   = trim($_POST['first_name']   ?? '');
    $last_name    = trim($_POST['last_name']     ?? '');
    $email        = trim($_POST['email']         ?? '');
    $national_id  = trim($_POST['national_id']   ?? '');
    $phone        = trim($_POST['phone']         ?? '');
    $dob          = trim($_POST['date_of_birth'] ?? '');
    $gender       = trim($_POST['gender']        ?? '');
    $role         = trim($_POST['role']          ?? '');
    $raw_password = trim($_POST['password']      ?? '');

    $errors = [];
    if (empty($first_name))  $errors[] = "First name is required.";
    if (empty($last_name))   $errors[] = "Last name is required.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "A valid email address is required.";
    if (empty($national_id)) $errors[] = "National ID is required.";
    if (empty($phone))       $errors[] = "Phone number is required.";
    if (empty($dob))         $errors[] = "Date of birth is required.";
    if (!in_array($gender, ['male','female','other'])) $errors[] = "Please select a valid gender.";
    if (!in_array($role, ['patient','doctor','receptionist'])) $errors[] = "Please select a valid role.";
    if (strlen($raw_password) < 8) $errors[] = "Password must be at least 8 characters.";

    if (empty($errors)) {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ? OR national_id = ? LIMIT 1");
        $check->execute([$email, $national_id]);
        if ($check->fetch()) {
            $error = "A user with that email or National ID already exists.";
        } else {
            $hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, national_id, phone, date_of_birth, gender, password, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$first_name, $last_name, $email, $national_id, $phone, $dob, $gender, $hashed_password, $role]);
            header("Location: receptionist_adduser.php?msg=created&name=" . urlencode($first_name . ' ' . $last_name) . "&role=" . urlencode($role));
            exit();
        }
    } else {
        $error = implode(" ", $errors);
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'created') {
    $createdName = htmlspecialchars($_GET['name'] ?? 'User');
    $createdRole = htmlspecialchars($_GET['role'] ?? '');
    $success = "User <strong>{$createdName}</strong> registered successfully as <strong>" . ucfirst($createdRole) . "</strong>.";
}

$users = $conn->query("SELECT id, first_name, last_name, email, phone, gender, role, date_of_birth FROM users ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User - ApexCare</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../static/receptionist_sidebar.css">
    <link rel="stylesheet" href="../static/receptionist.css">
    <style>
        /* Card icon helpers */
        .fcard-icon { width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0; }
        .fcard-icon.teal { background:#e6f7f5;color:#0d9488; }
        .fcard-icon.navy { background:var(--slate-light,#f1f5f9);color:var(--slate,#1e293b); }
        .fcard-title { font-size:14px;font-weight:700;color:var(--text-primary); }
        .fcard-sub   { font-size:11.5px;color:var(--text-muted);margin-top:2px; }

        /* Password eye */
        .pass-input-wrap { position:relative; }
        .pass-input-wrap input { padding-right:44px!important; }
        .pass-eye { position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:14px;padding:0;display:flex;align-items:center;transition:color .15s; }
        .pass-eye:hover { color:var(--amber-dark,#b45309); }

        /* Strength bar */
        .strength-wrap { display:flex;align-items:center;gap:10px;margin-top:7px; }
        .strength-track { flex:1;height:4px;background:var(--border);border-radius:99px;overflow:hidden; }
        .strength-fill  { height:100%;width:0%;border-radius:99px;transition:width .3s ease,background .3s ease; }
        .strength-text  { font-size:11.5px;font-weight:600;min-width:72px;text-align:right; }

        /* Secure note */
        .secure-note { display:inline-flex;align-items:center;gap:5px;font-size:12px;color:var(--text-muted);margin-left:auto; }
        .secure-note i { color:var(--amber-dark,#b45309);font-size:11px; }

        /* Users table card */
        .table-card { background:var(--surface);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);overflow:hidden;margin-top:24px;animation:fadeUp .5s ease .08s both; }
        .table-card-head { display:flex;align-items:center;justify-content:space-between;padding:16px 28px;background:var(--surface-alt);border-bottom:1px solid var(--border);flex-wrap:wrap;gap:12px; }
        .table-card-head-left { display:flex;align-items:center;gap:10px; }

        /* Table search */
        .table-search-wrap { position:relative;display:flex;align-items:center; }
        .table-search-wrap i { position:absolute;left:11px;color:var(--text-muted);font-size:12px;pointer-events:none; }
        .table-search-wrap input { padding:8px 12px 8px 32px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-family:inherit;outline:none;background:var(--surface-alt);color:var(--text-primary);width:220px;transition:border-color .18s,box-shadow .18s; }
        .table-search-wrap input:focus { border-color:var(--amber,#f59e0b);box-shadow:0 0 0 3px rgba(245,158,11,.1);background:var(--surface); }

        .table-wrap { overflow-x:auto; }
        .users-table { width:100%;border-collapse:collapse;font-size:13px; }
        .users-table thead th { background:var(--surface-alt);padding:10px 16px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text-muted);border-bottom:2px solid var(--border);white-space:nowrap; }
        .users-table tbody td { padding:12px 16px;border-bottom:1px solid var(--border);color:var(--text-primary);vertical-align:middle; }
        .users-table tbody tr:last-child td { border-bottom:none; }
        .users-table tbody tr:hover td { background:var(--surface-alt); }
        .td-id   { font-family:'DM Mono',monospace;font-size:12px;color:var(--text-muted); }
        .td-mono { font-family:'DM Mono',monospace;font-size:12.5px; }
        .user-name-cell { display:flex;align-items:center;gap:10px; }
        .user-initials { width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,var(--amber-dark,#b45309),var(--amber,#f59e0b));color:#fff;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
        .role-badge { display:inline-flex;align-items:center;padding:3px 10px;border-radius:50px;font-size:11.5px;font-weight:600; }
        .role-patient      { background:#e0f2fe;color:#0369a1; }
        .role-doctor       { background:#f0fdf4;color:#166534; }
        .role-receptionist { background:#fdf4ff;color:#7e22ce; }
    </style>
</head>
<body>
<div class="layout">
    <?php include "../static/includes/receptionist_sidebar.php"; ?>
    <main class="content">

        <div class="page-header">
            <div class="page-header-left">
                <h1>
                    <span class="r-header-icon"><i class="fas fa-user-plus"></i></span>
                    Register New User
                </h1>
                <p class="page-header-sub">Create accounts for patients, doctors, and receptionists</p>
            </div>
            <div class="header-badge">
                <i class="fas fa-calendar-day"></i>
                <?php echo date('d M Y'); ?>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="r-alert success" id="successAlert">
                <i class="fas fa-circle-check"></i>
                <span><?php echo $success; ?></span>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="r-alert error" id="errorAlert">
                <i class="fas fa-circle-exclamation"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <div class="form-card">
            <div class="form-card-head">
                <div class="form-card-head-left">
                    <span class="fcard-icon teal"><i class="fas fa-user-plus"></i></span>
                    <div>
                        <div class="fcard-title">New User Registration</div>
                        <div class="fcard-sub">Fields marked <span style="color:#ef4444;">*</span> are required &nbsp;&middot;&nbsp; Passwords stored as bcrypt hashes</div>
                    </div>
                </div>
            </div>
            <div class="form-card-body">
                <form method="POST" id="registerForm">

                    <div class="r-form-grid">
                        <div class="r-form-group">
                            <label class="r-label"><i class="fas fa-user"></i> First Name <span class="req">*</span></label>
                            <div class="r-input-wrap">
                                <i class="fas fa-user r-input-icon"></i>
                                <input type="text" name="first_name" placeholder="e.g. Jane" value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
                            </div>
                        </div>
                        <div class="r-form-group">
                            <label class="r-label"><i class="fas fa-user"></i> Last Name <span class="req">*</span></label>
                            <div class="r-input-wrap">
                                <i class="fas fa-user r-input-icon"></i>
                                <input type="text" name="last_name" placeholder="e.g. Mwangi" value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="r-form-grid">
                        <div class="r-form-group">
                            <label class="r-label"><i class="fas fa-envelope"></i> Email Address <span class="req">*</span></label>
                            <div class="r-input-wrap">
                                <i class="fas fa-envelope r-input-icon"></i>
                                <input type="email" name="email" placeholder="jane@example.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                            </div>
                        </div>
                        <div class="r-form-group">
                            <label class="r-label"><i class="fas fa-phone"></i> Phone Number <span class="req">*</span></label>
                            <div class="r-input-wrap">
                                <i class="fas fa-phone r-input-icon"></i>
                                <input type="tel" name="phone" placeholder="+254 7XX XXX XXX" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="r-form-grid">
                        <div class="r-form-group">
                            <label class="r-label"><i class="fas fa-id-card"></i> National ID <span class="req">*</span></label>
                            <div class="r-input-wrap">
                                <i class="fas fa-id-card r-input-icon"></i>
                                <input type="text" name="national_id" placeholder="e.g. 12345678" value="<?php echo isset($_POST['national_id']) ? htmlspecialchars($_POST['national_id']) : ''; ?>" required>
                            </div>
                        </div>
                        <div class="r-form-group">
                            <label class="r-label"><i class="fas fa-calendar-days"></i> Date of Birth <span class="req">*</span></label>
                            <div class="r-input-wrap no-icon">
                                <input type="date" name="date_of_birth" max="<?php echo date('Y-m-d'); ?>" value="<?php echo isset($_POST['date_of_birth']) ? htmlspecialchars($_POST['date_of_birth']) : ''; ?>" onchange="showAge(this.value)" required>
                            </div>
                            <div class="age-preview" id="agePreview">
                                <i class="fas fa-cake-candles"></i>
                                <span id="ageText"></span>
                            </div>
                        </div>
                    </div>

                    <div class="r-form-grid">
                        <div class="r-form-group">
                            <label class="r-label"><i class="fas fa-venus-mars"></i> Gender <span class="req">*</span></label>
                            <div class="r-input-wrap r-select-wrap">
                                <i class="fas fa-venus-mars r-input-icon"></i>
                                <select name="gender" required>
                                    <option value="" disabled <?php echo !isset($_POST['gender']) ? 'selected' : ''; ?>>Select gender</option>
                                    <option value="male"   <?php echo (($_POST['gender'] ?? '') === 'male')   ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo (($_POST['gender'] ?? '') === 'female') ? 'selected' : ''; ?>>Female</option>
                                    <option value="other"  <?php echo (($_POST['gender'] ?? '') === 'other')  ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="r-form-group">
                            <label class="r-label"><i class="fas fa-user-tag"></i> Role <span class="req">*</span></label>
                            <div class="r-input-wrap r-select-wrap">
                                <i class="fas fa-user-tag r-input-icon"></i>
                                <select name="role" required>
                                    <option value="" disabled <?php echo !isset($_POST['role']) ? 'selected' : ''; ?>>Select role</option>
                                    <option value="patient"      <?php echo (($_POST['role'] ?? '') === 'patient')      ? 'selected' : ''; ?>>Patient</option>
                                    <option value="doctor"       <?php echo (($_POST['role'] ?? '') === 'doctor')       ? 'selected' : ''; ?>>Doctor</option>
                                    <option value="receptionist" <?php echo (($_POST['role'] ?? '') === 'receptionist') ? 'selected' : ''; ?>>Receptionist</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="r-form-group" style="margin-bottom:20px;">
                        <label class="r-label">
                            <i class="fas fa-lock"></i> Password <span class="req">*</span>
                            <span style="font-size:11px;font-weight:400;color:var(--text-muted);text-transform:none;letter-spacing:0;margin-left:4px;">(min. 8 characters)</span>
                        </label>
                        <div class="r-input-wrap pass-input-wrap">
                            <i class="fas fa-lock r-input-icon"></i>
                            <input type="password" name="password" id="newUserPass" placeholder="Create a strong password" minlength="8" oninput="checkStrength(this.value)" required>
                            <button type="button" class="pass-eye" onclick="toggleNewPass()">
                                <i class="fas fa-eye" id="newPassEye"></i>
                            </button>
                        </div>
                        <div class="strength-wrap">
                            <div class="strength-track"><div class="strength-fill" id="strengthFill"></div></div>
                            <span class="strength-text" id="strengthText"></span>
                        </div>
                    </div>

                    <div class="r-form-actions">
                        <button type="submit" name="register_user" class="btn-submit">
                            <i class="fas fa-user-plus"></i> Register User
                        </button>
                        <button type="reset" class="btn-secondary-link" onclick="resetForm()">
                            <i class="fas fa-rotate-left"></i> Clear Form
                        </button>
                        <span class="secure-note">
                            <i class="fas fa-shield-halved"></i> Passwords hashed with bcrypt
                        </span>
                    </div>

                </form>
            </div>
        </div>

        <?php if (!empty($users)): ?>
        <div class="table-card">
            <div class="table-card-head">
                <div class="table-card-head-left">
                    <span class="fcard-icon navy"><i class="fas fa-users"></i></span>
                    <div>
                        <div class="fcard-title">Registered Users</div>
                        <div class="fcard-sub"><?php echo count($users); ?> user<?php echo count($users) !== 1 ? 's' : ''; ?> in the system</div>
                    </div>
                </div>
                <div class="table-search-wrap">
                    <i class="fas fa-magnifying-glass"></i>
                    <input type="text" id="userSearch" placeholder="Search users..." oninput="searchUsers()">
                </div>
            </div>
            <div class="table-wrap">
                <table class="users-table" id="usersTable">
                    <thead>
                        <tr>
                            <th>#</th><th>Name</th><th>Email</th><th>Phone</th><th>Gender</th><th>Role</th><th>Date of Birth</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr data-search="<?php echo strtolower(htmlspecialchars($u['first_name'].' '.$u['last_name'].' '.$u['email'].' '.$u['role'])); ?>">
                            <td class="td-id">P<?php echo str_pad($u['id'],4,'0',STR_PAD_LEFT); ?></td>
                            <td>
                                <div class="user-name-cell">
                                    <div class="user-initials"><?php echo strtoupper(substr($u['first_name'],0,1).substr($u['last_name'],0,1)); ?></div>
                                    <?php echo htmlspecialchars($u['first_name'].' '.$u['last_name']); ?>
                                </div>
                            </td>
                            <td class="td-mono"><?php echo htmlspecialchars($u['email']); ?></td>
                            <td class="td-mono"><?php echo htmlspecialchars($u['phone']); ?></td>
                            <td><?php echo ucfirst(htmlspecialchars($u['gender'])); ?></td>
                            <td><span class="role-badge role-<?php echo $u['role']; ?>"><?php echo ucfirst($u['role']); ?></span></td>
                            <td><?php echo date('d M Y', strtotime($u['date_of_birth'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </main>
</div>

<script>
    function toggleNewPass() {
        const input = document.getElementById('newUserPass');
        const icon  = document.getElementById('newPassEye');
        if (input.type === 'password') { input.type='text'; icon.classList.replace('fa-eye','fa-eye-slash'); }
        else { input.type='password'; icon.classList.replace('fa-eye-slash','fa-eye'); }
    }

    function checkStrength(val) {
        const fill=document.getElementById('strengthFill'), label=document.getElementById('strengthText');
        let score=0;
        if(val.length>=8) score++;
        if(/[A-Z]/.test(val)) score++;
        if(/[0-9]/.test(val)) score++;
        if(/[^A-Za-z0-9]/.test(val)) score++;
        const levels=[
            {pct:'20%',color:'#ef4444',text:'Very Weak'},
            {pct:'40%',color:'#f97316',text:'Weak'},
            {pct:'65%',color:'#eab308',text:'Fair'},
            {pct:'85%',color:'#22c55e',text:'Strong'},
            {pct:'100%',color:'#0d9488',text:'Very Strong'}
        ];
        if(val.length===0){fill.style.width='0%';label.textContent='';return;}
        const lvl=levels[Math.min(score,4)];
        fill.style.width=lvl.pct; fill.style.background=lvl.color;
        label.textContent=lvl.text; label.style.color=lvl.color;
    }

    function showAge(v) {
        if(!v) return;
        const dob=new Date(v), today=new Date();
        let age=today.getFullYear()-dob.getFullYear();
        const m=today.getMonth()-dob.getMonth();
        if(m<0||(m===0&&today.getDate()<dob.getDate())) age--;
        const p=document.getElementById('agePreview'), t=document.getElementById('ageText');
        if(age>=0&&age<=130){t.textContent=age+' years old';p.classList.add('visible');}
        else{p.classList.remove('visible');}
    }

    function resetForm() {
        document.getElementById('strengthFill').style.width='0%';
        document.getElementById('strengthText').textContent='';
        document.getElementById('agePreview').classList.remove('visible');
    }

    function searchUsers() {
        const q=document.getElementById('userSearch').value.toLowerCase().trim();
        document.querySelectorAll('#usersTable tbody tr').forEach(r=>{
            r.style.display=!q||r.dataset.search.includes(q)?'':'none';
        });
    }

    ['successAlert','errorAlert'].forEach(id=>{
        const el=document.getElementById(id);
        if(!el) return;
        setTimeout(()=>{el.style.transition='opacity .5s ease';el.style.opacity='0';setTimeout(()=>el.remove(),500);},7000);
    });
</script>
</body>
</html>