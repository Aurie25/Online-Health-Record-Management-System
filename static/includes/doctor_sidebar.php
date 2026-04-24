<?php
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') {
    header("Location: login.php");
    exit();
}

$currentPage = basename($_SERVER['PHP_SELF']);
$firstName   = htmlspecialchars($_SESSION['first_name'] ?? 'Doctor');
$lastName    = htmlspecialchars($_SESSION['last_name']  ?? '');
$initial     = strtoupper(substr($_SESSION['first_name'] ?? 'D', 0, 1));
?>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<button class="mobile-toggle" id="mobileToggle" onclick="openSidebar()" aria-label="Open menu">
    <i class="fas fa-bars"></i>
</button>

<aside class="sidebar" id="sidebar">

    <!-- Header -->
    <div class="sidebar-header">
        <img src="../logo1.jpg" alt="ApexCare Logo">
        <span class="brand">ApexCare</span>
        <button id="toggleBtn" onclick="toggleSidebar()" aria-label="Collapse sidebar">
            <i class="fas fa-chevron-left" id="toggleIcon"></i>
        </button>
    </div>

    <!-- User mini card -->
    <div class="sidebar-user">
        <div class="user-avatar"><?php echo $initial; ?></div>
        <div class="user-info">
            <div class="user-name">Dr. <?php echo $firstName . ' ' . $lastName; ?></div>
            <div class="user-role">physician</div>
        </div>
    </div>

    <!-- Navigation -->
    <ul class="nav-links">

        <li class="nav-section-label">Overview</li>

        <li>
            <a href="doctor_dashboard.php"
               class="<?php echo $currentPage === 'doctor_dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <li class="nav-section-label">Clinical</li>

        <li>
            <a href="doctor_appointments.php"
               class="<?php echo $currentPage === 'doctor_appointments.php' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-days"></i>
                <span>Appointments</span>
            </a>
        </li>

        <li>
            <a href="doctor_schedule.php"
               class="<?php echo $currentPage === 'doctor_schedule.php' ? 'active' : ''; ?>">
                <i class="fas fa-clock"></i>
                <span>My Schedule</span>
            </a>
        </li>

        <li>
            <a href="dpatientrecords.php"
               class="<?php echo $currentPage === 'dpatientrecords.php' ? 'active' : ''; ?>">
                <i class="fas fa-notes-medical"></i>
                <span>Patient Records</span>
            </a>
        </li>
         <li>
            <a href="dpatientconditionform.php"
               class="<?php echo $currentPage === 'dpatientconditionform.php' ? 'active' : ''; ?>">
                <i class="fas fa-notes-medical"></i>
                <span>Patient condition form</span>
            </a>
        </li>

        <li class="nav-section-label">Account</li>

        <li>
            <a href="dsettings.php"
               class="<?php echo $currentPage === 'dsettings.php' ? 'active' : ''; ?>">
                <i class="fas fa-gear"></i>
                <span>Settings</span>
            </a>
        </li>

        <li>
            <a href="logout.php" class="logout">
                <i class="fas fa-arrow-right-from-bracket"></i>
                <span>Logout</span>
            </a>
        </li>

    </ul>

    <!-- Footer -->
    <div class="sidebar-footer">
        <div class="sidebar-footer-inner">
            <div class="footer-dot"></div>
            <span class="sidebar-version">ApexCare v1.0 · Doctor</span>
        </div>
    </div>

</aside>

<script>
    const sidebar    = document.getElementById('sidebar');
    const overlay    = document.getElementById('sidebarOverlay');
    const toggleIcon = document.getElementById('toggleIcon');
    let isCollapsed  = false;

    function toggleSidebar() {
        isCollapsed = !isCollapsed;
        sidebar.classList.toggle('collapsed', isCollapsed);
        const content = document.querySelector('.content');
        if (content) {
            content.style.marginLeft = isCollapsed
                ? 'var(--sidebar-collapsed-w)'
                : 'var(--sidebar-w)';
        }
        toggleIcon.style.transform  = isCollapsed ? 'rotate(180deg)' : 'rotate(0deg)';
        toggleIcon.style.transition = 'transform 0.25s ease';
    }

    function openSidebar() {
        sidebar.classList.add('mobile-open');
        overlay.classList.add('active');
    }

    function closeSidebar() {
        sidebar.classList.remove('mobile-open');
        overlay.classList.remove('active');
    }
</script>