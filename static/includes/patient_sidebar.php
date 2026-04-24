<?php
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit();
}

$sidebarInitial = strtoupper(substr($_SESSION['first_name'] ?? 'P', 0, 1));
$sidebarName    = htmlspecialchars($_SESSION['first_name'] ?? 'Patient');
$currentPage    = basename($_SERVER['PHP_SELF']);
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
        <div class="user-avatar"><?php echo $sidebarInitial; ?></div>
        <div class="user-info">
            <div class="user-name"><?php echo $sidebarName; ?></div>
            <div class="user-role">patient portal</div>
        </div>
    </div>

    <!-- Navigation -->
    <ul class="nav-links">

        <li class="nav-section-label">Main</li>

        <li>
            <a href="patienthome.php" class="<?php echo $currentPage === 'patienthome.php' ? 'active' : ''; ?>">
                <i class="fas fa-house-medical"></i>
                <span>Home</span>
            </a>
        </li>

        <li>
            <a href="patientprofile.php" class="<?php echo $currentPage === 'patientprofile.php' ? 'active' : ''; ?>">
                <i class="fas fa-circle-user"></i>
                <span>My Profile</span>
            </a>
        </li>

        <li class="nav-section-label">Health</li>

        <li>
            <a href="patient_book.php" class="<?php echo $currentPage === 'patient_book.php' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-plus"></i>
                <span>Book Appointment</span>
            </a>
        </li>

        <li>
                 <a href="patienthealthtracker.php" class="<?php echo $currentPage === 'patienthealthtracker.php' ? 'active' : ''; ?>">
         <i class="fas fa-heart-pulse"></i>
       <span>My Health</span>
          </a>

        </li>
   

        <li>
            <a href="patientmedicalhistory.php" class="<?php echo $currentPage === 'patientmedicalhistory.php' ? 'active' : ''; ?>">
                <i class="fas fa-notes-medical"></i>
                <span>Medical History</span>
            </a>
        </li>

        <li>
            <a href="patientuploads.php" class="<?php echo $currentPage === 'patientuploads.php' ? 'active' : ''; ?>">
                <i class="fas fa-cloud-arrow-up"></i>
                <span>My Uploads</span>
            </a>
        </li>

        <li class="nav-section-label">Account</li>

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
            <span class="sidebar-version">ApexCare v1.0 · Patient Portal</span>
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