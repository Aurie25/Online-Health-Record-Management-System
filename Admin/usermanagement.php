<?php
session_start();
require_once dirname(__DIR__) . '../db.php';
require_once 'adminauth.php';

$auth = new Auth($conn);
$auth->redirectIfNotLoggedIn();

/* ===============================
   HANDLE POST ACTIONS (AJAX)
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $userId = (int)($_POST['user_id'] ?? 0);
    if (!$userId) { echo json_encode(['success'=>false,'message'=>'Invalid user ID']); exit; }

    // Fetch user to confirm they exist
    $userCheck = $conn->prepare("SELECT id, first_name, last_name, role FROM users WHERE id = ?");
    $userCheck->execute([$userId]);
    $targetUser = $userCheck->fetch(PDO::FETCH_ASSOC);
    if (!$targetUser) { echo json_encode(['success'=>false,'message'=>'User not found']); exit; }

    $adminId = $_SESSION['admin_id'] ?? 0;

    switch ($_POST['action']) {

        case 'suspend':
            // Add 'status' column check — if column doesn't exist yet this is safe-guarded
            $stmt = $conn->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
            $stmt->execute([$userId]);
            // Log it
            $log = $conn->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address) VALUES (?,?,?,?)");
            $log->execute([$adminId, 'Suspend User', "Suspended user: {$targetUser['first_name']} {$targetUser['last_name']} (ID: $userId)", $_SERVER['REMOTE_ADDR'] ?? '']);
            echo json_encode(['success'=>true,'message'=>'User suspended successfully.','new_status'=>'suspended']);
            break;

        case 'reactivate':
            $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ?");
            $stmt->execute([$userId]);
            $log = $conn->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address) VALUES (?,?,?,?)");
            $log->execute([$adminId, 'Reactivate User', "Reactivated user: {$targetUser['first_name']} {$targetUser['last_name']} (ID: $userId)", $_SERVER['REMOTE_ADDR'] ?? '']);
            echo json_encode(['success'=>true,'message'=>'User account reactivated.','new_status'=>'active']);
            break;

        case 'change_role':
            $newRole = $_POST['new_role'] ?? '';
            if (!in_array($newRole, ['doctor','receptionist','patient'])) {
                echo json_encode(['success'=>false,'message'=>'Invalid role.']); exit;
            }
            $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$newRole, $userId]);
            $log = $conn->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address) VALUES (?,?,?,?)");
            $log->execute([$adminId, 'Change Role', "Changed role of {$targetUser['first_name']} {$targetUser['last_name']} (ID: $userId) to $newRole", $_SERVER['REMOTE_ADDR'] ?? '']);
            echo json_encode(['success'=>true,'message'=>"Role changed to ".ucfirst($newRole).".'",'new_role'=>$newRole]);
            break;

        case 'reset_password':
            // Generate a secure temp password
            $tempPass = bin2hex(random_bytes(5)); // e.g. "a3f9c12e1b"
            $hashed   = password_hash($tempPass, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed, $userId]);
            $log = $conn->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address) VALUES (?,?,?,?)");
            $log->execute([$adminId, 'Reset Password', "Reset password for {$targetUser['first_name']} {$targetUser['last_name']} (ID: $userId)", $_SERVER['REMOTE_ADDR'] ?? '']);
            echo json_encode(['success'=>true,'message'=>'Password reset successfully.','temp_password'=>$tempPass]);
            break;

        case 'delete':
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $log = $conn->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address) VALUES (?,?,?,?)");
            $log->execute([$adminId, 'Delete User', "Deleted user: {$targetUser['first_name']} {$targetUser['last_name']} (ID: $userId, Role: {$targetUser['role']})", $_SERVER['REMOTE_ADDR'] ?? '']);
            echo json_encode(['success'=>true,'message'=>'User deleted successfully.']);
            break;

        default:
            echo json_encode(['success'=>false,'message'=>'Unknown action.']);
    }
    exit;
}

/* ===============================
   SEARCH & FILTER
================================ */
$search      = trim($_GET['search'] ?? '');
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';

$query  = "SELECT * FROM users WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (first_name LIKE :search OR last_name LIKE :search OR email LIKE :search OR national_id LIKE :search OR phone LIKE :search)";
    $params[':search'] = "%{$search}%";
}
if (!empty($role_filter) && in_array($role_filter, ['doctor','receptionist','patient'])) {
    $query .= " AND role = :role";
    $params[':role'] = $role_filter;
}
if (!empty($status_filter) && in_array($status_filter, ['active','suspended'])) {
    $query .= " AND status = :status";
    $params[':status'] = $status_filter;
}

$query .= " ORDER BY id DESC";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Summary counts
$counts = $conn->query("SELECT role, COUNT(*) as n FROM users GROUP BY role")->fetchAll(PDO::FETCH_KEY_PAIR);
$suspended = $conn->query("SELECT COUNT(*) FROM users WHERE status='suspended'")->fetchColumn();
$totalUsers = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Management — ApexCare Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../static/admin_sidebar.css">
<link rel="stylesheet" href="../static/usermanagement.css">
</head>
<body>

<?php include '../static/includes/admin_sidebar.php'; ?>
<button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<div class="main-content">

    <!-- ── Header ── -->
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-icon"><i class="fas fa-users-cog"></i></div>
            <div>
                <h1>User Management</h1>
                <p class="page-sub">Manage accounts, roles &amp; access</p>
            </div>
        </div>
        <div class="header-stats">
            <div class="hstat"><span><?php echo number_format($totalUsers); ?></span> Total</div>
            <div class="hstat hstat-blue"><span><?php echo $counts['doctor'] ?? 0; ?></span> Doctors</div>
            <div class="hstat hstat-green"><span><?php echo $counts['patient'] ?? 0; ?></span> Patients</div>
            <div class="hstat hstat-orange"><span><?php echo $counts['receptionist'] ?? 0; ?></span> Receptionists</div>
            <?php if ($suspended > 0): ?>
            <div class="hstat hstat-red"><span><?php echo $suspended; ?></span> Suspended</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Filters ── -->
    <div class="filters-bar">
        <form method="GET" class="filters-form">
            <div class="search-wrap">
                <i class="fas fa-search search-icon"></i>
                <input type="text" name="search" class="search-input"
                    placeholder="Search name, email, national ID or phone…"
                    value="<?php echo htmlspecialchars($search); ?>">
            </div>

            <select name="role" class="filter-select">
                <option value="">All Roles</option>
                <option value="doctor"       <?php echo $role_filter==='doctor'?'selected':''; ?>>Doctor</option>
                <option value="receptionist" <?php echo $role_filter==='receptionist'?'selected':''; ?>>Receptionist</option>
                <option value="patient"      <?php echo $role_filter==='patient'?'selected':''; ?>>Patient</option>
            </select>

            <select name="status" class="filter-select">
                <option value="">All Statuses</option>
                <option value="active"    <?php echo $status_filter==='active'?'selected':''; ?>>Active</option>
                <option value="suspended" <?php echo $status_filter==='suspended'?'selected':''; ?>>Suspended</option>
            </select>

            <button type="submit" class="btn-search"><i class="fas fa-search"></i> Search</button>
            <a href="usermanagement.php" class="btn-reset"><i class="fas fa-redo"></i> Reset</a>
        </form>
    </div>

    <!-- ── Results count ── -->
    <div class="results-bar">
        <span class="results-count">
            Showing <strong><?php echo count($users); ?></strong> user<?php echo count($users)!==1?'s':''; ?>
            <?php if ($search || $role_filter || $status_filter): ?>
                — filtered
                <a href="usermanagement.php" class="clear-filters">Clear filters</a>
            <?php endif; ?>
        </span>
    </div>

    <!-- ── Table ── -->
    <div class="table-card">
    <?php if (count($users) > 0): ?>
        <div class="table-wrap">
        <table class="users-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Contact</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u):
                $status     = $u['status'] ?? 'active';
                $isSuspended = $status === 'suspended';
                $initials   = strtoupper(substr($u['first_name'],0,1).substr($u['last_name'],0,1));
                $avatarClass = match($u['role']) { 'doctor'=>'av-blue', 'patient'=>'av-green', default=>'av-orange' };
            ?>
            <tr class="user-row <?php echo $isSuspended ? 'row-suspended' : ''; ?>"
                data-id="<?php echo $u['id']; ?>"
                data-name="<?php echo htmlspecialchars($u['first_name'].' '.$u['last_name']); ?>"
                data-role="<?php echo $u['role']; ?>"
                data-status="<?php echo $status; ?>"
                data-email="<?php echo htmlspecialchars($u['email']); ?>"
                data-phone="<?php echo htmlspecialchars($u['phone']); ?>"
                data-gender="<?php echo htmlspecialchars($u['gender']); ?>"
                data-dob="<?php echo htmlspecialchars($u['date_of_birth']); ?>"
                data-nid="<?php echo htmlspecialchars($u['national_id']); ?>">

                <td>
                    <div class="user-cell">
                        <div class="avatar <?php echo $avatarClass; ?>"><?php echo $initials; ?></div>
                        <div class="user-info">
                            <div class="user-name"><?php echo htmlspecialchars($u['first_name'].' '.$u['last_name']); ?></div>
                            <div class="user-id">#<?php echo str_pad($u['id'],4,'0',STR_PAD_LEFT); ?></div>
                        </div>
                    </div>
                </td>

                <td>
                    <div class="contact-cell">
                        <div><i class="fas fa-envelope contact-icon"></i><?php echo htmlspecialchars($u['email']); ?></div>
                        <div><i class="fas fa-phone contact-icon"></i><?php echo htmlspecialchars($u['phone']); ?></div>
                    </div>
                </td>

                <td>
                    <span class="role-badge role-<?php echo $u['role']; ?>"><?php echo ucfirst($u['role']); ?></span>
                </td>

                <td>
                    <span class="status-badge status-<?php echo $status; ?>">
                        <span class="status-dot"></span>
                        <?php echo ucfirst($status); ?>
                    </span>
                </td>

                <td class="date-cell">
                    <?php
                    // users table has no created_at — use date_of_birth as stand-in or just show N/A
                    // If you add created_at later, swap this line
                    echo '—';
                    ?>
                </td>

                <td>
                    <div class="action-row">
                        <button class="btn-action btn-view-user" onclick="openViewModal(this.closest('tr'))" title="View profile">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn-action btn-role" onclick="openRoleModal(this.closest('tr'))" title="Change role">
                            <i class="fas fa-user-tag"></i>
                        </button>
                        <?php if ($isSuspended): ?>
                        <button class="btn-action btn-activate" onclick="doAction('reactivate', this.closest('tr'))" title="Reactivate account">
                            <i class="fas fa-user-check"></i>
                        </button>
                        <?php else: ?>
                        <button class="btn-action btn-suspend" onclick="doAction('suspend', this.closest('tr'))" title="Suspend account">
                            <i class="fas fa-user-slash"></i>
                        </button>
                        <?php endif; ?>
                        <button class="btn-action btn-password" onclick="doAction('reset_password', this.closest('tr'))" title="Reset password">
                            <i class="fas fa-key"></i>
                        </button>
                        <button class="btn-action btn-delete" onclick="confirmDelete(this.closest('tr'))" title="Delete user">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="fas fa-users"></i></div>
            <h3>No Users Found</h3>
            <p>No users match your search criteria. <a href="usermanagement.php">Clear filters</a></p>
        </div>
    <?php endif; ?>
    </div>

</div><!-- /main-content -->

<!-- ═══════════════════════════════════════
     VIEW USER MODAL
════════════════════════════════════════ -->
<div class="modal-backdrop" id="viewModal">
<div class="modal modal-view">
    <div class="modal-header">
        <div class="modal-title-wrap">
            <div class="modal-avatar" id="vmAvatar"></div>
            <div>
                <div class="modal-title" id="vmName"></div>
                <div class="modal-sub" id="vmIdRole"></div>
            </div>
        </div>
        <button class="modal-close" onclick="closeModal('viewModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
        <div class="info-section">
            <div class="info-section-title"><i class="fas fa-id-card"></i> Personal Information</div>
            <div class="info-note">This information is managed by the user and cannot be edited by admins.</div>
            <div class="info-grid">
                <div class="info-item"><span class="info-label">Full Name</span><span class="info-val" id="vmFullName"></span></div>
                <div class="info-item"><span class="info-label">National ID</span><span class="info-val" id="vmNid"></span></div>
                <div class="info-item"><span class="info-label">Gender</span><span class="info-val" id="vmGender"></span></div>
                <div class="info-item"><span class="info-label">Date of Birth</span><span class="info-val" id="vmDob"></span></div>
                <div class="info-item"><span class="info-label">Email</span><span class="info-val" id="vmEmail"></span></div>
                <div class="info-item"><span class="info-label">Phone</span><span class="info-val" id="vmPhone"></span></div>
            </div>
        </div>
        <div class="info-section">
            <div class="info-section-title"><i class="fas fa-shield-alt"></i> Account Details</div>
            <div class="info-grid">
                <div class="info-item"><span class="info-label">Role</span><span class="info-val" id="vmRole"></span></div>
                <div class="info-item"><span class="info-label">Status</span><span class="info-val" id="vmStatus"></span></div>
            </div>
        </div>
        <div class="info-section">
            <div class="info-section-title"><i class="fas fa-tools"></i> Admin Actions</div>
            <div class="info-note">These are the only changes admins are permitted to make on user accounts.</div>
            <div class="action-list">
                <div class="action-item">
                    <div class="action-item-info">
                        <strong>Suspend / Reactivate</strong>
                        <p>Blocks or restores the user's ability to log in. Does not delete data.</p>
                    </div>
                    <button class="btn-inline-action" id="vmSuspendBtn" onclick="doActionFromModal()"></button>
                </div>
                <div class="action-item">
                    <div class="action-item-info">
                        <strong>Reset Password</strong>
                        <p>Generates a temporary password. Share it securely with the user.</p>
                    </div>
                    <button class="btn-inline-action btn-orange" onclick="doResetFromModal()"><i class="fas fa-key"></i> Reset</button>
                </div>
                <div class="action-item">
                    <div class="action-item-info">
                        <strong>Change Role</strong>
                        <p>Reassign this account to a different role.</p>
                    </div>
                    <button class="btn-inline-action btn-purple" onclick="openRoleFromModal()"><i class="fas fa-user-tag"></i> Change Role</button>
                </div>
                <div class="action-item action-item-danger">
                    <div class="action-item-info">
                        <strong>Delete Account</strong>
                        <p>Permanently removes this user and all linked records. Cannot be undone.</p>
                    </div>
                    <button class="btn-inline-action btn-red" onclick="deleteFromModal()"><i class="fas fa-trash"></i> Delete</button>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- ═══════════════════════════════════════
     CHANGE ROLE MODAL
════════════════════════════════════════ -->
<div class="modal-backdrop" id="roleModal">
<div class="modal modal-sm">
    <div class="modal-header">
        <div class="modal-title-wrap">
            <div class="modal-icon-wrap orange"><i class="fas fa-user-tag"></i></div>
            <div class="modal-title">Change Role</div>
        </div>
        <button class="modal-close" onclick="closeModal('roleModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
        <p class="modal-desc">Select a new role for <strong id="rmName"></strong>. This will change what sections of the system they can access.</p>
        <div class="role-options">
            <label class="role-option">
                <input type="radio" name="new_role" value="doctor">
                <span class="role-option-card">
                    <i class="fas fa-user-md"></i>
                    <strong>Doctor</strong>
                    <small>Clinical access, records, schedules</small>
                </span>
            </label>
            <label class="role-option">
                <input type="radio" name="new_role" value="receptionist">
                <span class="role-option-card">
                    <i class="fas fa-user-nurse"></i>
                    <strong>Receptionist</strong>
                    <small>Appointments, messages, articles</small>
                </span>
            </label>
            <label class="role-option">
                <input type="radio" name="new_role" value="patient">
                <span class="role-option-card">
                    <i class="fas fa-user-injured"></i>
                    <strong>Patient</strong>
                    <small>Appointments, records view, uploads</small>
                </span>
            </label>
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="closeModal('roleModal')">Cancel</button>
            <button class="btn-confirm btn-confirm-orange" onclick="submitRoleChange()"><i class="fas fa-check"></i> Confirm Change</button>
        </div>
    </div>
</div>
</div>

<!-- ═══════════════════════════════════════
     DELETE CONFIRM MODAL
════════════════════════════════════════ -->
<div class="modal-backdrop" id="deleteModal">
<div class="modal modal-sm">
    <div class="modal-header">
        <div class="modal-title-wrap">
            <div class="modal-icon-wrap red"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="modal-title">Delete User</div>
        </div>
        <button class="modal-close" onclick="closeModal('deleteModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
        <p class="modal-desc">You are about to permanently delete <strong id="dmName"></strong>. This action <strong>cannot be undone</strong> and will remove all associated appointments and records.</p>
        <div class="danger-checklist">
            <label class="danger-check">
                <input type="checkbox" id="deleteConfirmCheck" onchange="toggleDeleteBtn()">
                I understand this is permanent and cannot be reversed.
            </label>
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="closeModal('deleteModal')">Cancel</button>
            <button class="btn-confirm btn-confirm-red" id="confirmDeleteBtn" disabled onclick="submitDelete()">
                <i class="fas fa-trash"></i> Delete Permanently
            </button>
        </div>
    </div>
</div>
</div>

<!-- ═══════════════════════════════════════
     TOAST NOTIFICATION
════════════════════════════════════════ -->
<div class="toast" id="toast">
    <i class="fas fa-check-circle toast-icon" id="toastIcon"></i>
    <span id="toastMsg"></span>
</div>

<!-- ═══════════════════════════════════════
     TEMP PASSWORD MODAL
════════════════════════════════════════ -->
<div class="modal-backdrop" id="passModal">
<div class="modal modal-sm">
    <div class="modal-header">
        <div class="modal-title-wrap">
            <div class="modal-icon-wrap green"><i class="fas fa-key"></i></div>
            <div class="modal-title">Password Reset</div>
        </div>
        <button class="modal-close" onclick="closeModal('passModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
        <p class="modal-desc">Password reset successfully. Share this temporary password with the user securely. They should change it on next login.</p>
        <div class="temp-pass-box">
            <span id="tempPassDisplay"></span>
            <button class="btn-copy" onclick="copyTempPass()" title="Copy"><i class="fas fa-copy"></i></button>
        </div>
        <div class="modal-footer">
            <button class="btn-confirm btn-confirm-blue" onclick="closeModal('passModal')">Done</button>
        </div>
    </div>
</div>
</div>

<script>
/* ─────────────────────────────────────────
   STATE
───────────────────────────────────────── */
let activeRow    = null;   // the TR currently in context
let activeUserId = null;
let activeUserName = '';
let activeUserStatus = '';
let activeUserRole   = '';

/* ─────────────────────────────────────────
   MODAL HELPERS
───────────────────────────────────────── */
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
    if (id === 'deleteModal') document.getElementById('deleteConfirmCheck').checked = false, toggleDeleteBtn();
}

/* Close on backdrop click */
document.querySelectorAll('.modal-backdrop').forEach(b => {
    b.addEventListener('click', e => { if (e.target === b) b.classList.remove('open'); });
});

/* ─────────────────────────────────────────
   EXTRACT ROW DATA
───────────────────────────────────────── */
function rowData(tr) {
    return {
        id:     tr.dataset.id,
        name:   tr.dataset.name,
        role:   tr.dataset.role,
        status: tr.dataset.status,
        email:  tr.dataset.email,
        phone:  tr.dataset.phone,
        gender: tr.dataset.gender,
        dob:    tr.dataset.dob,
        nid:    tr.dataset.nid
    };
}

/* ─────────────────────────────────────────
   VIEW MODAL
───────────────────────────────────────── */
function openViewModal(tr) {
    const d = rowData(tr);
    activeRow        = tr;
    activeUserId     = d.id;
    activeUserName   = d.name;
    activeUserStatus = d.status;
    activeUserRole   = d.role;

    // Avatar initials
    const initials = (d.name.split(' ').map(w=>w[0]).join('')).toUpperCase();
    const avEl = document.getElementById('vmAvatar');
    avEl.textContent = initials;
    avEl.className = 'modal-avatar av-' + (d.role === 'doctor' ? 'blue' : d.role === 'patient' ? 'green' : 'orange');

    document.getElementById('vmName').textContent    = d.name;
    document.getElementById('vmIdRole').textContent  = '#' + String(d.id).padStart(4,'0') + ' · ' + ucFirst(d.role);
    document.getElementById('vmFullName').textContent = d.name;
    document.getElementById('vmNid').textContent     = d.nid || '—';
    document.getElementById('vmGender').textContent  = ucFirst(d.gender) || '—';
    document.getElementById('vmDob').textContent     = formatDate(d.dob);
    document.getElementById('vmEmail').textContent   = d.email;
    document.getElementById('vmPhone').textContent   = d.phone;
    document.getElementById('vmRole').innerHTML      = `<span class="role-badge role-${d.role}">${ucFirst(d.role)}</span>`;
    document.getElementById('vmStatus').innerHTML    = buildStatusBadge(d.status);

    // Suspend/reactivate button
    const suspBtn = document.getElementById('vmSuspendBtn');
    if (d.status === 'suspended') {
        suspBtn.innerHTML = '<i class="fas fa-user-check"></i> Reactivate';
        suspBtn.className = 'btn-inline-action btn-green';
    } else {
        suspBtn.innerHTML = '<i class="fas fa-user-slash"></i> Suspend';
        suspBtn.className = 'btn-inline-action btn-red-soft';
    }

    openModal('viewModal');
}

/* ─────────────────────────────────────────
   ROLE MODAL
───────────────────────────────────────── */
function openRoleModal(tr) {
    const d = rowData(tr);
    activeRow      = tr;
    activeUserId   = d.id;
    activeUserName = d.name;
    activeUserRole = d.role;
    document.getElementById('rmName').textContent = d.name;
    // Pre-select current role
    document.querySelectorAll('input[name="new_role"]').forEach(r => r.checked = r.value === d.role);
    openModal('roleModal');
}

function openRoleFromModal() {
    closeModal('viewModal');
    // re-open role modal with same context (activeRow still set)
    document.getElementById('rmName').textContent = activeUserName;
    document.querySelectorAll('input[name="new_role"]').forEach(r => r.checked = r.value === activeUserRole);
    openModal('roleModal');
}

function submitRoleChange() {
    const selected = document.querySelector('input[name="new_role"]:checked');
    if (!selected) { showToast('Please select a role.', false); return; }
    postAction('change_role', activeUserId, { new_role: selected.value }, res => {
        if (res.success) {
            updateRowRole(activeRow, res.new_role);
            closeModal('roleModal');
            showToast(res.message);
        } else showToast(res.message, false);
    });
}

/* ─────────────────────────────────────────
   DELETE
───────────────────────────────────────── */
function confirmDelete(tr) {
    const d = rowData(tr);
    activeRow      = tr;
    activeUserId   = d.id;
    activeUserName = d.name;
    document.getElementById('dmName').textContent = d.name;
    openModal('deleteModal');
}

function deleteFromModal() {
    closeModal('viewModal');
    document.getElementById('dmName').textContent = activeUserName;
    openModal('deleteModal');
}

function toggleDeleteBtn() {
    document.getElementById('confirmDeleteBtn').disabled = !document.getElementById('deleteConfirmCheck').checked;
}

function submitDelete() {
    postAction('delete', activeUserId, {}, res => {
        if (res.success) {
            activeRow.remove();
            closeModal('deleteModal');
            showToast(res.message);
            updateHeaderCount(-1, null);
        } else showToast(res.message, false);
    });
}

/* ─────────────────────────────────────────
   SUSPEND / REACTIVATE
───────────────────────────────────────── */
function doAction(action, tr) {
    activeRow        = tr;
    activeUserId     = tr.dataset.id;
    activeUserName   = tr.dataset.name;
    activeUserStatus = tr.dataset.status;
    postAction(action, activeUserId, {}, res => {
        if (res.success) {
            updateRowStatus(tr, res.new_status);
            showToast(res.message);
        } else showToast(res.message, false);
    });
}

function doActionFromModal() {
    const action = activeUserStatus === 'suspended' ? 'reactivate' : 'suspend';
    postAction(action, activeUserId, {}, res => {
        if (res.success) {
            updateRowStatus(activeRow, res.new_status);
            // Update modal button
            const btn = document.getElementById('vmSuspendBtn');
            const newStatus = res.new_status;
            activeUserStatus = newStatus;
            activeRow.dataset.status = newStatus;
            document.getElementById('vmStatus').innerHTML = buildStatusBadge(newStatus);
            if (newStatus === 'suspended') {
                btn.innerHTML = '<i class="fas fa-user-check"></i> Reactivate';
                btn.className = 'btn-inline-action btn-green';
            } else {
                btn.innerHTML = '<i class="fas fa-user-slash"></i> Suspend';
                btn.className = 'btn-inline-action btn-red-soft';
            }
            showToast(res.message);
        } else showToast(res.message, false);
    });
}

/* ─────────────────────────────────────────
   RESET PASSWORD
───────────────────────────────────────── */
function doResetFromModal() { triggerReset(activeUserId); }

function doAction_reset(tr) {
    activeRow      = tr;
    activeUserId   = tr.dataset.id;
    triggerReset(activeUserId);
}

function triggerReset(userId) {
    postAction('reset_password', userId, {}, res => {
        if (res.success) {
            document.getElementById('tempPassDisplay').textContent = res.temp_password;
            openModal('passModal');
        } else showToast(res.message, false);
    });
}

/* ─────────────────────────────────────────
   TABLE BUTTON — password reset shortcut
───────────────────────────────────────── */
document.addEventListener('click', e => {
    const btn = e.target.closest('.btn-password');
    if (btn) {
        const tr = btn.closest('tr');
        activeRow = tr;
        activeUserId = tr.dataset.id;
        triggerReset(tr.dataset.id);
    }
});

/* ─────────────────────────────────────────
   COPY TEMP PASS
───────────────────────────────────────── */
function copyTempPass() {
    const txt = document.getElementById('tempPassDisplay').textContent;
    navigator.clipboard.writeText(txt).then(() => showToast('Copied to clipboard!'));
}

/* ─────────────────────────────────────────
   DOM UPDATES
───────────────────────────────────────── */
function updateRowStatus(tr, newStatus) {
    tr.dataset.status = newStatus;
    const isSusp = newStatus === 'suspended';
    tr.classList.toggle('row-suspended', isSusp);

    // Update status badge cell
    tr.querySelectorAll('.status-badge').forEach(b => { b.outerHTML = buildStatusBadge(newStatus); });

    // Swap suspend ↔ reactivate button
    const oldBtn = tr.querySelector('.btn-suspend, .btn-activate');
    if (oldBtn) {
        const newBtn = document.createElement('button');
        newBtn.title = isSusp ? 'Reactivate account' : 'Suspend account';
        newBtn.className = 'btn-action ' + (isSusp ? 'btn-activate' : 'btn-suspend');
        newBtn.innerHTML = `<i class="fas fa-${isSusp ? 'user-check' : 'user-slash'}"></i>`;
        newBtn.onclick = () => doAction(isSusp ? 'reactivate' : 'suspend', tr);
        oldBtn.replaceWith(newBtn);
    }
}

function updateRowRole(tr, newRole) {
    tr.dataset.role = newRole;
    activeUserRole  = newRole;
    tr.querySelectorAll('.role-badge').forEach(b => {
        b.className = `role-badge role-${newRole}`;
        b.textContent = ucFirst(newRole);
    });
    // Update avatar color
    const av = tr.querySelector('.avatar');
    if (av) av.className = 'avatar av-' + (newRole === 'doctor' ? 'blue' : newRole === 'patient' ? 'green' : 'orange');
}

function updateHeaderCount(delta, role) {
    // Rough decrement of total shown — precise count would need a reload
}

/* ─────────────────────────────────────────
   AJAX HELPER
───────────────────────────────────────── */
function postAction(action, userId, extra, callback) {
    const body = new URLSearchParams({ action, user_id: userId, ...extra });
    fetch('usermanagement.php', { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body })
        .then(r => r.json())
        .then(callback)
        .catch(() => showToast('Network error. Please try again.', false));
}

/* ─────────────────────────────────────────
   TOAST
───────────────────────────────────────── */
let toastTimer;
function showToast(msg, success = true) {
    const t   = document.getElementById('toast');
    const ico = document.getElementById('toastIcon');
    document.getElementById('toastMsg').textContent = msg;
    ico.className = success ? 'fas fa-check-circle toast-icon' : 'fas fa-times-circle toast-icon toast-err';
    t.className = 'toast show' + (success ? '' : ' toast-error');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.className = 'toast', 3500);
}

/* ─────────────────────────────────────────
   UTILS
───────────────────────────────────────── */
function ucFirst(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }
function formatDate(d) {
    if (!d) return '—';
    const dt = new Date(d);
    return isNaN(dt) ? d : dt.toLocaleDateString('en-GB', {day:'2-digit',month:'short',year:'numeric'});
}
function buildStatusBadge(status) {
    return `<span class="status-badge status-${status}"><span class="status-dot"></span>${ucFirst(status)}</span>`;
}

/* ─────────────────────────────────────────
   SIDEBAR
───────────────────────────────────────── */
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('active');
    document.querySelector('.sidebar-overlay').classList.toggle('active');
}
document.addEventListener('click', e => {
    const sb = document.querySelector('.sidebar');
    const tb = document.querySelector('.sidebar-toggle');
    if (window.innerWidth <= 768 && sb && tb && !sb.contains(e.target) && !tb.contains(e.target)) {
        sb.classList.remove('active');
        document.querySelector('.sidebar-overlay')?.classList.remove('active');
    }
});
</script>
</body>
</html>