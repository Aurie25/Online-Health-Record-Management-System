<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "receptionist") {
    header("Location: login.php");
    exit();
}

/* ===============================
   FETCH PATIENTS WITH FULL DETAILS
================================ */
$stmt = $conn->prepare("
    SELECT id, first_name, last_name, email, gender, date_of_birth
    FROM users
    WHERE role = 'patient'
    ORDER BY id DESC
");
$stmt->execute();
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalPatients = count($patients);

/* Gender counts for stats */
$maleCount   = 0;
$femaleCount = 0;
foreach ($patients as $p) {
    if (strtolower($p['gender']) === 'male')   $maleCount++;
    if (strtolower($p['gender']) === 'female') $femaleCount++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Patients — ApexCare</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../static/receptionist_sidebar.css">
    <link rel="stylesheet" href="../static/receptionist.css">
    <link rel="stylesheet" href="../static/receptionistviewusers.css">
</head>
<body>

<div class="layout">
    <?php include "../static/includes/receptionist_sidebar.php"; ?>

    <main class="content">

        <!-- ── Page Header ─────────────────────────────── -->
        <div class="page-header">
            <div class="page-header-left">
                <h1>
                    <span class="r-header-icon"><i class="fas fa-users"></i></span>
                    All Patients
                </h1>
                <p class="page-header-sub">Registered patients in the ApexCare system</p>
            </div>
            <div class="header-badge">
                <i class="fas fa-calendar-day"></i>
                <?php echo date('d M Y'); ?>
            </div>
        </div>

        <!-- ── Stats Row ───────────────────────────────── -->
        <div class="stats-row">
            <div class="stat-card amber">
                <div class="stat-icon amber"><i class="fas fa-hospital-user"></i></div>
                <div>
                    <div class="stat-value"><?php echo $totalPatients; ?></div>
                    <div class="stat-label">Total Patients</div>
                </div>
            </div>
            <div class="stat-card slate">
                <div class="stat-icon slate"><i class="fas fa-mars"></i></div>
                <div>
                    <div class="stat-value"><?php echo $maleCount; ?></div>
                    <div class="stat-label">Male</div>
                </div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon green"><i class="fas fa-venus"></i></div>
                <div>
                    <div class="stat-value"><?php echo $femaleCount; ?></div>
                    <div class="stat-label">Female</div>
                </div>
            </div>
        </div>

        <!-- ── Toolbar ─────────────────────────────────── -->
        <div class="toolbar">
            <div class="search-wrap">
                <i class="fas fa-magnifying-glass"></i>
                <input
                    type="text"
                    class="search-input"
                    id="searchInput"
                    placeholder="Search by name or email…"
                    oninput="filterTable()"
                >
            </div>

            <select class="filter-select" id="genderFilter" onchange="filterTable()">
                <option value="">All genders</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
            </select>

            <div class="toolbar-right">
                <span class="result-count">
                    Showing <span id="visibleCount"><?php echo $totalPatients; ?></span> of <?php echo $totalPatients; ?>
                </span>
                <a href="receptionist_adduser.php" class="btn-add-patient">
                    <i class="fas fa-user-plus"></i>
                    Add Patient
                </a>
            </div>
        </div>

        <!-- ── Patients Table ──────────────────────────── -->
        <div class="table-card">
            <div class="table-card-head">
                <div class="table-card-head-left">
                    <span class="table-icon"><i class="fas fa-table-list"></i></span>
                    <div>
                        <div class="table-card-title">Patient Registry</div>
                        <div class="table-card-sub">Click a row to expand details</div>
                    </div>
                </div>
            </div>

            <?php if ($totalPatients > 0): ?>

            <table class="patients-table" id="patientsTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Patient ID</th>
                        <th>Name</th>
                        <th>Gender</th>
                        <th>Date of Birth</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php foreach ($patients as $i => $patient):
                        $initials    = strtoupper(substr($patient['first_name'], 0, 1) . substr($patient['last_name'], 0, 1));
                        $fullName    = htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']);
                        $paddedId    = str_pad($patient['id'], 4, '0', STR_PAD_LEFT);
                        $dobFormatted = $patient['date_of_birth']
                            ? date('d M Y', strtotime($patient['date_of_birth']))
                            : '—';
                        $genderLower = strtolower($patient['gender']);
                    ?>
                    <tr data-name="<?php echo strtolower($fullName); ?>"
                        data-gender="<?php echo $genderLower; ?>">

                        <td><span class="row-num"><?php echo $i + 1; ?></span></td>

                        <td>
                            <span class="patient-id">P<?php echo $paddedId; ?></span>
                        </td>

                        <td>
                            <div class="patient-name-cell">
                                <div class="patient-avatar"><?php echo $initials; ?></div>
                                <span class="patient-full-name"><?php echo $fullName; ?></span>
                            </div>
                        </td>

                        <td>
                            <span style="font-size:13px;font-weight:600;color:var(--text-secondary);">
                                <?php echo ucfirst(htmlspecialchars($patient['gender'])); ?>
                            </span>
                        </td>

                        <td>
                            <span style="font-family:'DM Mono',monospace;font-size:12px;color:var(--text-muted);">
                                <?php echo $dobFormatted; ?>
                            </span>
                        </td>

                        <td>
                            <div class="action-cell">
                                <a href="receptionist_view_patient.php?id=<?php echo $patient['id']; ?>"
                                   class="btn-view">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <a href="receptionist_edit_patient.php?id=<?php echo $patient['id']; ?>"
                                   class="btn-edit">
                                    <i class="fas fa-pen"></i> Edit
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- No filter results row -->
            <div id="noResults">
                <div class="empty-icon" style="width:48px;height:48px;font-size:20px;">
                    <i class="fas fa-magnifying-glass"></i>
                </div>
                <p style="font-size:13.5px;font-weight:600;color:var(--text-muted);">
                    No patients match your search.
                </p>
            </div>

            <div class="table-footer">
                <span class="table-footer-info">
                    <i class="fas fa-users"></i>
                    <span id="footerCount"><?php echo $totalPatients; ?></span> patient<?php echo $totalPatients !== 1 ? 's' : ''; ?> registered
                </span>
                <span class="table-footer-info">
                    <i class="fas fa-circle-check"></i>
                    healthrecord_db · users
                </span>
            </div>

            <?php else: ?>

            <div class="empty-state">
                <div class="empty-icon"><i class="fas fa-user-slash"></i></div>
                <h3>No patients yet</h3>
                <p>No patients have been registered in the system. Add the first one now.</p>
                <a href="receptionist_adduser.php" class="btn-add-patient" style="margin-top:4px;">
                    <i class="fas fa-user-plus"></i>
                    Register First Patient
                </a>
            </div>

            <?php endif; ?>
        </div>

    </main>
</div>

<script>
    const rows       = document.querySelectorAll('#tableBody tr');
    const total      = rows.length;

    function filterTable() {
        const search = document.getElementById('searchInput').value.toLowerCase().trim();
        const gender = document.getElementById('genderFilter').value.toLowerCase();
        let visible  = 0;

        rows.forEach(row => {
            const matchName   = !search || row.dataset.name.includes(search);
            const matchGender = !gender || row.dataset.gender === gender;
            const show        = matchName && matchGender;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        document.getElementById('visibleCount').textContent  = visible;
        document.getElementById('footerCount').textContent   = visible;

        const noResults = document.getElementById('noResults');
        noResults.classList.toggle('show', visible === 0 && total > 0);
    }
</script>

</body>
</html>