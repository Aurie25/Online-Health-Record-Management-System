<?php
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'receptionist') {
    header("Location: login.php");
    exit();
}

$currentPage      = basename($_SERVER['PHP_SELF']);
$receptionistName = htmlspecialchars($_SESSION['first_name'] ?? 'Receptionist');
$initial          = strtoupper(substr($_SESSION['first_name'] ?? 'R', 0, 1));
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
            <div class="user-name"><?php echo $receptionistName; ?></div>
            <div class="user-role">receptionist</div>
        </div>
    </div>

    <!-- Navigation -->
    <ul class="nav-links">

        <li class="nav-section-label">Patients</li>

        <li>
            <a href="receptionist_adduser.php"
               class="<?php echo $currentPage === 'receptionist_adduser.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-plus"></i>
                <span>Register Patient</span>
            </a>
        </li>

        <li>
            <a href="receptionistviewuser.php"
               class="<?php echo $currentPage === 'receptionistviewuser.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>View Patients</span>
            </a>
        </li>

        <li class="nav-section-label">Content</li>

        <li>
            <a href="receptionist_articles.php"
               class="<?php echo $currentPage === 'receptionist_articles.php' ? 'active' : ''; ?>">
                <i class="fas fa-newspaper"></i>
                <span>Manage Articles</span>
            </a>
        </li>

        <li class="nav-section-label">Communication</li>

        <li>
            <a href="receptionistmessages.php"
               class="<?php echo $currentPage === 'receptionistmessages.php' ? 'active' : ''; ?>">
                <i class="fas fa-envelope-open-text"></i>
                <span>Messages</span>
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
            <span class="sidebar-version">ApexCare v1.0 · Reception</span>
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