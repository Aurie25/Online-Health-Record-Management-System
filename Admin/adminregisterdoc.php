<?php
session_start();

require_once dirname(__DIR__) . '../db.php';
require_once 'adminauth.php';

$auth = new Auth($conn);
$auth->redirectIfNotLoggedIn();

/* ===============================
   HANDLE DELETE
================================ */

if (isset($_GET['action'], $_GET['id'])) {

    $user_id = (int) $_GET['id'];
    $action  = $_GET['action'];

    if ($action === 'delete' && $user_id > 0) {

        // Delete doctor profile first (if no FK cascade)
        $conn->prepare("DELETE FROM doctor_profiles WHERE doctor_id = :id")
             ->execute([':id' => $user_id]);

        // Delete doctor user safely
        $stmt = $conn->prepare("DELETE FROM users WHERE id = :id AND role = 'doctor'");
        $stmt->execute([':id' => $user_id]);

        // Log activity
        $log = $conn->prepare("
            INSERT INTO activity_logs 
            (admin_id, action, details, ip_address, timestamp)
            VALUES (:admin_id, :action, :details, :ip, NOW())
        ");

        $log->execute([
            ':admin_id' => $_SESSION['admin_id'],
            ':action'   => 'doctor_delete',
            ':details'  => "Deleted doctor ID {$user_id}",
            ':ip'       => $_SERVER['REMOTE_ADDR']
        ]);

        header("Location: adminregisterdoc.php?success=Doctor deleted successfully");
        exit();
    }
}

/* ===============================
   SEARCH & FILTER
================================ */

$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$specialization_filter = $_GET['specialization'] ?? '';

$query = "
    SELECT 
        u.*, 
        dp.specialization, 
        dp.status
    FROM users u
    LEFT JOIN doctor_profiles dp ON u.id = dp.doctor_id
    WHERE u.role = 'doctor'
";

$params = [];

if (!empty($search)) {
    $query .= " AND (
        u.first_name LIKE :search OR
        u.last_name LIKE :search OR
        u.email LIKE :search OR
        u.national_id LIKE :search
    )";
    $params[':search'] = "%{$search}%";
}

if (!empty($status_filter)) {
    $query .= " AND dp.status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($specialization_filter)) {
    $query .= " AND dp.specialization = :specialization";
    $params[':specialization'] = $specialization_filter;
}

$query .= " ORDER BY u.id DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ===============================
   SPECIALIZATION DROPDOWN
================================ */

$spec_stmt = $conn->query("
    SELECT DISTINCT specialization 
    FROM doctor_profiles 
    ORDER BY specialization
");

$specializations = $spec_stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Management - ApexCare Admin</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../static/admin_sidebar.css">
    <link rel="stylesheet" href="../static/adminregisterdoctors.css">
</head>
<body>

<?php include '../static/includes/admin_sidebar.php'; ?>

<button class="sidebar-toggle" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<div class="main-content">

    <div class="header">
    <h1>
        <i class="fas fa-users-cog"></i>
        Staff Management
    </h1>

    <div class="header-buttons">
        <a href="register-doctor.php" class="btn btn-primary">
            <i class="fas fa-user-md"></i> Register Doctor
        </a>

        <a href="register-receptionist.php" class="btn btn-primary">
            <i class="fas fa-user-tie"></i> Register Receptionist
        </a>
    </div>
</div>

    <?php if(isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($_GET['success']); ?>
        </div>
    <?php endif; ?>

    <div class="filters">
        <form method="GET" class="filter-grid">

            <div class="filter-group">
                <label><i class="fas fa-search"></i> Search</label>
                <input type="text" name="search"
                       placeholder="Name, Email, National ID"
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>

            <div class="filter-group">
                <label><i class="fas fa-flag"></i> Status</label>
                <select name="status">
                    <option value="">All Status</option>
                    <option value="working" <?php echo $status_filter=='working'?'selected':''; ?>>Working</option>
                    <option value="onleave" <?php echo $status_filter=='onleave'?'selected':''; ?>>On Leave</option>
                    <option value="absent" <?php echo $status_filter=='absent'?'selected':''; ?>>Absent</option>
                </select>
            </div>

            <div class="filter-group">
                <label><i class="fas fa-stethoscope"></i> Specialization</label>
                <select name="specialization">
                    <option value="">All Specializations</option>
                    <?php foreach($specializations as $spec): ?>
                        <option value="<?php echo htmlspecialchars($spec); ?>"
                            <?php echo $specialization_filter==$spec?'selected':''; ?>>
                            <?php echo htmlspecialchars($spec); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-buttons">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Apply
                </button>
                <a href="adminregisterdoc.php" class="btn btn-secondary">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>

        </form>
    </div>

    <div class="card">
        <div class="card-body">

            <?php if(count($doctors) > 0): ?>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Doctor Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Specialization</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>

                    <?php foreach($doctors as $doctor): ?>
                        <tr>
                            <td><?php echo $doctor['id']; ?></td>

                            <td>
                                <strong>
                                    <?php echo htmlspecialchars($doctor['first_name'].' '.$doctor['last_name']); ?>
                                </strong>
                            </td>

                            <td><?php echo htmlspecialchars($doctor['email']); ?></td>
                            <td><?php echo htmlspecialchars($doctor['phone']); ?></td>

                            <td>
                                <?php echo htmlspecialchars($doctor['specialization'] ?? 'Not Set'); ?>
                            </td>

                            <td>
                                <span class="status-badge status-<?php echo $doctor['status'] ?? 'working'; ?>">
                                    <?php echo ucfirst($doctor['status'] ?? 'working'); ?>
                                </span>
                            </td>

                            <td>
                                <div class="action-buttons">

                                    <a href="edit-doctor.php?id=<?php echo $doctor['id']; ?>"
                                       class="btn-action btn-edit"
                                       title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>

                                    <a href="?action=delete&id=<?php echo $doctor['id']; ?>"
                                       class="btn-action btn-delete"
                                       onclick="return confirm('Delete this doctor permanently?')"
                                       title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>

                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    </tbody>
                </table>
            </div>

            <?php else: ?>

                <div class="empty-state">
                    <i class="fas fa-user-md"></i>
                    <h3>No Doctors Found</h3>
                    <p>No doctors match your filters.</p>
                    <a href="register-doctor.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Register Doctor
                    </a>
                </div>

            <?php endif; ?>

        </div>
    </div>

</div>

<script>
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('active');
    document.querySelector('.sidebar-overlay').classList.toggle('active');
}
</script>

</body>
</html>