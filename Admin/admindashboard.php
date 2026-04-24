<?php
session_start();
require_once '../db.php';
require_once 'adminauth.php';

$auth = new Auth($conn);
$auth->redirectIfNotLoggedIn();

$stats = [
    'total_users' => 0,
    'total_doctors' => 0,
    'total_patients' => 0,
    'total_records' => 0
];

try {
    $stmt = $conn->query("SELECT COUNT(*) FROM users");
    $stats['total_users'] = $stmt->fetchColumn();

    $stmt = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'doctor'");
    $stats['total_doctors'] = $stmt->fetchColumn();

    $stmt = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'patient'");
    $stats['total_patients'] = $stmt->fetchColumn();

    try {
        $stmt = $conn->query("SELECT COUNT(*) FROM patient_records");
        $stats['total_records'] = $stmt->fetchColumn();
    } catch (Exception $e) {
        $stats['total_records'] = 0;
    }

    try {
        $stmt = $conn->query("SELECT * FROM activity_logs ORDER BY timestamp DESC LIMIT 5");
        $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $recent_activities = [];
    }

} catch(PDOException $e) {
    $error = "Database error.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — ApexCare Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../static/admin_dashboard.css">
</head>
<body>
<div class="admin-container">

    <?php include '../static/includes/admin_sidebar.php'; ?>

    <button class="sidebar-toggle" onclick="toggleSidebar()" aria-label="Toggle sidebar">
        <i class="fas fa-bars"></i>
    </button>

    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <main class="main-content">

        <!-- ── Header ─────────────────────────────────── -->
        <div class="header">
            <div class="header-left">
                <div class="date-strip">
                    <i class="far fa-calendar"></i>
                    <?php echo date('l, F j, Y'); ?>
                </div>
                <h2>
                    <span class="icon-wrap"><i class="fas fa-chart-pie"></i></span>
                    Dashboard Overview
                </h2>
                <p class="welcome-text">
                    Good <?php echo (date('H') < 12) ? 'morning' : ((date('H') < 17) ? 'afternoon' : 'evening') ?>,
                    <strong><?php echo htmlspecialchars($_SESSION['admin_name']); ?></strong> — here's what's happening today.
                </p>
            </div>
            <div class="welcome-badge">
                <i class="fas fa-user-shield"></i>
                <span><?php echo htmlspecialchars($_SESSION['admin_role'] ?? 'Administrator'); ?></span>
            </div>
        </div>

        <!-- ── Stats Grid ──────────────────────────────── -->
        <div class="stats-grid">

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-trend positive"><i class="fas fa-arrow-up"></i> 12%</div>
                </div>
                <div class="stat-details">
                    <h3><?php echo number_format($stats['total_users']); ?></h3>
                    <p>Total Users</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon"><i class="fas fa-user-md"></i></div>
                    <div class="stat-trend positive"><i class="fas fa-arrow-up"></i> 8%</div>
                </div>
                <div class="stat-details">
                    <h3><?php echo number_format($stats['total_doctors']); ?></h3>
                    <p>Total Doctors</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon"><i class="fas fa-user-injured"></i></div>
                    <div class="stat-trend neutral"><i class="fas fa-minus"></i> 0%</div>
                </div>
                <div class="stat-details">
                    <h3><?php echo number_format($stats['total_patients']); ?></h3>
                    <p>Total Patients</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon"><i class="fas fa-notes-medical"></i></div>
                    <div class="stat-trend positive"><i class="fas fa-arrow-up"></i> 5%</div>
                </div>
                <div class="stat-details">
                    <h3><?php echo number_format($stats['total_records']); ?></h3>
                    <p>Medical Records</p>
                </div>
            </div>

        </div>

        <!-- ── Content Grid ───────────────────────────── -->
        <div class="content-grid">

            <!-- Recent Activity -->
            <div class="card">
                <div class="card-header">
                    <h3>
                        <span class="card-header-icon blue"><i class="fas fa-history"></i></span>
                        Recent Activity
                    </h3>
                    <a href="#" class="view-all">View all <i class="fas fa-arrow-right"></i></a>
                </div>

                <?php if (!empty($recent_activities)): ?>
                    <div class="activity-list">
                        <?php foreach($recent_activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-dot-wrap">
                                <div class="activity-dot <?php echo $activity['type'] === 'login' ? 'login' : 'default'; ?>"></div>
                                <div class="activity-line"></div>
                            </div>
                            <div class="activity-details">
                                <p><?php echo htmlspecialchars($activity['details']); ?></p>
                                <small>
                                    <i class="far fa-clock"></i>
                                    <?php echo date('M d · H:i', strtotime($activity['timestamp'])); ?>
                                </small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="fas fa-inbox"></i></div>
                        <p>No recent activity yet</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h3>
                        <span class="card-header-icon orange"><i class="fas fa-bolt"></i></span>
                        Quick Actions
                    </h3>
                </div>

                <div class="quick-actions-grid">
                    <a href="usermanagement.php" class="quick-action-btn">
                        <i class="fas fa-users"></i>
                        <span>Manage Users</span>
                    </a>
                    <a href="managedoctors.php" class="quick-action-btn">
                        <i class="fas fa-user-md"></i>
                        <span>Manage Doctors</span>
                    </a>
                    <a href="adminregisterdoc.php" class="quick-action-btn">
                        <i class="fas fa-user-plus"></i>
                        <span>Add Doctor</span>
                    </a>
                    <a href="reports.php" class="quick-action-btn">
                        <i class="fas fa-chart-bar"></i>
                        <span>View Reports</span>
                    </a>
                </div>
            </div>

        </div>
    </main>
</div>

<script>
    function toggleSidebar() {
        document.querySelector('.sidebar').classList.toggle('active');
        document.querySelector('.sidebar-overlay').classList.toggle('active');
    }

    document.addEventListener('click', function(e) {
        const sidebar = document.querySelector('.sidebar');
        const toggle = document.querySelector('.sidebar-toggle');
        if (window.innerWidth <= 768 && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
            sidebar.classList.remove('active');
            document.querySelector('.sidebar-overlay').classList.remove('active');
        }
    });

    document.querySelector('.sidebar')?.addEventListener('dblclick', function() {
        if (window.innerWidth > 768) {
            this.classList.toggle('collapsed');
            document.querySelector('.main-content').style.marginLeft =
                this.classList.contains('collapsed') ? '80px' : '270px';
        }
    });
</script>
</body>
</html>