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

        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'doctor'");
        $stmt->execute([$user_id]);

        // Log activity
        $log_stmt = $conn->prepare("
            INSERT INTO activity_logs (admin_id, action, details, ip_address, timestamp)
            VALUES (?, 'user_delete', ?, ?, NOW())
        ");

        $details = "Deleted doctor ID {$user_id}";

        $log_stmt->execute([
            $_SESSION['admin_id'],
            $details,
            $_SERVER['REMOTE_ADDR']
        ]);

        header("Location: usermanagement.php?success=Doctor deleted successfully");
        exit();
    }
}

/* ===============================
   SEARCH & FILTER
================================ */

$search = $_GET['search'] ?? '';
$gender_filter = $_GET['gender'] ?? '';

$query = "SELECT * FROM users WHERE role = 'doctor'";
$params = [];

if (!empty($search)) {
    $query .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR national_id LIKE ?)";
    $search_term = "%{$search}%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

if (!empty($gender_filter)) {
    $query .= " AND gender = ?";
    $params[] = $gender_filter;
}

$query .= " ORDER BY id DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Doctor Management - ApexCare</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../static/managedoctors.css">
</head>
<body>

<?php include '../static/includes/admin_sidebar.php'; ?>

<div class="main-content">

<h1>Doctor Management</h1>

<?php if(isset($_GET['success'])): ?>
    <p style="color:green;"><?php echo htmlspecialchars($_GET['success']); ?></p>
<?php endif; ?>

<form method="GET">
    <input type="text" name="search" placeholder="Search name, email, national ID"
        value="<?php echo htmlspecialchars($search); ?>">

    <select name="gender">
        <option value="">All Genders</option>
        <option value="male" <?php echo $gender_filter=='male'?'selected':''; ?>>Male</option>
        <option value="female" <?php echo $gender_filter=='female'?'selected':''; ?>>Female</option>
    </select>

    <button type="submit">Filter</button>
    <a href="usermanagement.php">Reset</a>
</form>

<hr>

<?php if(count($doctors) > 0): ?>

<table border="1" cellpadding="8">
<tr>
    <th>ID</th>
    <th>Name</th>
    <th>Email</th>
    <th>Phone</th>
    <th>Gender</th>
    <th>Date of Birth</th>
    <th>National ID</th>
    <th>Actions</th>
</tr>

<?php foreach($doctors as $doctor): ?>
<tr>
    <td><?php echo $doctor['id']; ?></td>
    <td><?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></td>
    <td><?php echo htmlspecialchars($doctor['email']); ?></td>
    <td><?php echo htmlspecialchars($doctor['phone']); ?></td>
    <td><?php echo htmlspecialchars($doctor['gender']); ?></td>
    <td><?php echo htmlspecialchars($doctor['date_of_birth']); ?></td>
    <td><?php echo htmlspecialchars($doctor['national_id']); ?></td>
    <td>
        <a href="edit-user.php?id=<?php echo $doctor['id']; ?>">Edit</a> |
        <a href="?action=delete&id=<?php echo $doctor['id']; ?>"
           onclick="return confirm('Delete this doctor permanently?')">
           Delete
        </a>
    </td>
</tr>
<?php endforeach; ?>

</table>

<?php else: ?>

<p>No doctors found.</p>

<?php endif; ?>

</div>

</body>
</html>