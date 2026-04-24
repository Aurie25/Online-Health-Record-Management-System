<?php
// admin_sidebar.php - Reusable sidebar component with proper active state handling
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Sidebar CSS -->
    <link rel="stylesheet" href="../static/admin_sidebar.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<div class="sidebar">
    <div class="sidebar-header">
        <h2>ApexCare Admin</h2>
        <p>Welcome, <?php echo isset($_SESSION['admin_name']) ? htmlspecialchars($_SESSION['admin_name']) : 'Admin'; ?></p>
    </div>
    <ul class="sidebar-menu">
        <li>
            <a href="admindashboard.php" class="<?php echo $current_page == 'admindashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li>
            <a href="usermanagement.php" class="<?php echo $current_page == 'usermanagement.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
        </li>
        <li>
            <a href="managedoctors.php" class="<?php echo $current_page == 'managedoctors.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-md"></i>
                <span>Doctors</span>
            </a>
        </li>
        <li>
            <a href="adminregisterdoc.php" class="<?php echo $current_page == 'adminregisterdoc.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-plus"></i>
                <span>Register Staff</span>
            </a>
        </li>
        <li>
            <a href="reports.php" class="<?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
        </li>
        <li>
            <a href="admin_logout.php" onclick="return confirm('Are you sure you want to logout?')">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</div>