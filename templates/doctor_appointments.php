<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: login.php");
    exit();
}

$doctorId = $_SESSION['user_id'];
$success  = '';

/* ── MARK AS COMPLETED ───────────────────────────── */
if (isset($_POST['complete_id'])) {
    $appointmentId = intval($_POST['complete_id']);
    $conn->prepare("
        UPDATE appointments
        SET status = 'completed'
        WHERE id = :id AND doctor_id = :doc
    ")->execute([':id' => $appointmentId, ':doc' => $doctorId]);
    header("Location: doctor_appointment.php?done=1");
    exit();
}

$success = isset($_GET['done']) ? 'Appointment marked as completed.' : '';

/* ── FETCH APPOINTMENTS ──────────────────────────── */
$stmt = $conn->prepare("
    SELECT a.id,
           a.status,
           a.notes,
           ds.available_date,
           ds.slot_time,
           u.first_name AS patient_first,
           u.last_name  AS patient_last
    FROM appointments a
    JOIN doctor_schedule ds ON a.schedule_id = ds.id
    JOIN users u            ON a.patient_id  = u.id
    WHERE a.doctor_id = :doc
    ORDER BY
        FIELD(a.status, 'assigned', 'cancelled', 'completed'),
        ds.available_date DESC,
        ds.slot_time DESC
");
$stmt->execute([':doc' => $doctorId]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ── STATS ───────────────────────────────────────── */
$total     = count($appointments);
$assigned  = 0; $completed = 0; $cancelled = 0;
foreach ($appointments as $a) {
    if ($a['status'] === 'assigned')  $assigned++;
    if ($a['status'] === 'completed') $completed++;
    if ($a['status'] === 'cancelled') $cancelled++;
}

$todayStr = date('Y-m-d');
$todayCount = 0;
foreach ($appointments as $a) {
    if ($a['available_date'] === $todayStr && $a['status'] === 'assigned') $todayCount++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments — ApexCare</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../static/doctor_appointment.css">
       <link rel="stylesheet" href="../static/doctor_sidebar.css">
    <link rel="stylesheet" href="../static/doctor.css">
</head>
<body>

<div class="layout">
    <?php include "../static/includes/doctor_sidebar.php"; ?>

    <main class="content">

        <!-- ── Page Header ─────────────────────────────── -->
        <div class="page-header">
            <div>
                <h1>
                    <span class="header-icon"><i class="fas fa-calendar-check"></i></span>
                    My Appointments
                </h1>
                <p class="page-header-sub">Manage and track your scheduled patient appointments</p>
            </div>
            <div class="header-badge">
                <i class="fas fa-calendar-day"></i>
                <?php echo date('d M Y'); ?>
            </div>
        </div>

        <!-- ── Success Toast ───────────────────────────── -->
        <?php if ($success): ?>
            <div class="success-toast" id="successToast">
                <i class="fas fa-circle-check"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- ── Stats Row ───────────────────────────────── -->
        <div class="stats-row">
            <div class="stat-card teal">
                <div class="stat-icon teal"><i class="fas fa-calendar-days"></i></div>
                <div>
                    <div class="stat-value"><?php echo $total; ?></div>
                    <div class="stat-label">Total</div>
                </div>
            </div>
            <div class="stat-card amber">
                <div class="stat-icon amber"><i class="fas fa-clock"></i></div>
                <div>
                    <div class="stat-value"><?php echo $assigned; ?></div>
                    <div class="stat-label">Upcoming</div>
                </div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon green"><i class="fas fa-circle-check"></i></div>
                <div>
                    <div class="stat-value"><?php echo $completed; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>
            <div class="stat-card red">
                <div class="stat-icon red"><i class="fas fa-calendar-xmark"></i></div>
                <div>
                    <div class="stat-value"><?php echo $cancelled; ?></div>
                    <div class="stat-label">Cancelled</div>
                </div>
            </div>
        </div>

        <!-- ── Toolbar ─────────────────────────────────── -->
        <div class="toolbar">
            <div class="filter-tabs">
                <span class="filter-tab active" data-filter="all">
                    <i class="fas fa-list"></i>
                    All <span class="tab-count"><?php echo $total; ?></span>
                </span>
                <span class="filter-tab" data-filter="assigned">
                    <i class="fas fa-clock"></i>
                    Upcoming <span class="tab-count"><?php echo $assigned; ?></span>
                </span>
                <span class="filter-tab" data-filter="completed">
                    <i class="fas fa-circle-check"></i>
                    Completed <span class="tab-count"><?php echo $completed; ?></span>
                </span>
                <?php if ($cancelled > 0): ?>
                <span class="filter-tab" data-filter="cancelled">
                    <i class="fas fa-xmark"></i>
                    Cancelled <span class="tab-count"><?php echo $cancelled; ?></span>
                </span>
                <?php endif; ?>
            </div>

            <div class="search-wrap">
                <i class="fas fa-magnifying-glass si"></i>
                <input
                    type="text"
                    class="search-input"
                    id="searchInput"
                    placeholder="Search by patient name…"
                    oninput="filterTable()"
                >
            </div>

            <select class="sort-select" id="sortSelect" onchange="sortTable()">
                <option value="date-desc">Date: Newest first</option>
                <option value="date-asc">Date: Oldest first</option>
                <option value="name-asc">Name: A → Z</option>
                <option value="name-desc">Name: Z → A</option>
            </select>

            <span class="result-count">
                <span id="visibleCount"><?php echo $total; ?></span> of <?php echo $total; ?>
            </span>
        </div>

        <!-- ── Table Card ──────────────────────────────── -->
        <?php if ($total > 0): ?>

        <div class="table-card">
            <div class="table-card-head">
                <div class="table-card-head-left">
                    <span class="tbl-icon"><i class="fas fa-table-list"></i></span>
                    <div>
                        <div class="table-card-title">Appointment Schedule</div>
                        <div class="table-card-sub">appointments · healthrecord_db</div>
                    </div>
                </div>
            </div>

            <table class="appt-table" id="apptTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Patient</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php foreach ($appointments as $i => $a):
                        $fullName = $a['patient_first'] . ' ' . $a['patient_last'];
                        $initials = strtoupper(substr($a['patient_first'], 0, 1) . substr($a['patient_last'], 0, 1));
                        $dateDisp = date('d M Y', strtotime($a['available_date']));
                        $timeDisp = date('g:i A', strtotime($a['slot_time']));
                        $isToday  = $a['available_date'] === $todayStr;
                        $status   = $a['status'];
                    ?>
                    <tr data-status="<?php echo $status; ?>"
                        data-name="<?php echo strtolower(htmlspecialchars($fullName)); ?>"
                        data-date="<?php echo $a['available_date']; ?>"
                        data-time="<?php echo $a['slot_time']; ?>">

                        <td><span class="row-num"><?php echo $i + 1; ?></span></td>

                        <td>
                            <div class="patient-cell">
                                <div class="patient-avatar"><?php echo $initials; ?></div>
                                <span class="patient-name"><?php echo htmlspecialchars($fullName); ?></span>
                            </div>
                        </td>

                        <td>
                            <span class="date-chip">
                                <i class="fas fa-calendar"></i>
                                <?php echo $dateDisp; ?>
                                <?php if ($isToday): ?>
                                    &nbsp;<span style="color:var(--teal);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:0.5px;">Today</span>
                                <?php endif; ?>
                            </span>
                        </td>

                        <td>
                            <span class="time-chip">
                                <i class="fas fa-clock"></i>
                                <?php echo $timeDisp; ?>
                            </span>
                        </td>

                        <td>
                            <?php
                                $icon = match($status) {
                                    'completed' => 'circle-check',
                                    'cancelled' => 'circle-xmark',
                                    default     => 'circle-dot',
                                };
                            ?>
                            <span class="status-badge <?php echo $status; ?>">
                                <i class="fas fa-<?php echo $icon; ?>"></i>
                                <?php echo ucfirst($status); ?>
                            </span>
                        </td>

                        <td>
                            <?php if ($status === 'assigned'): ?>
                                <button
                                    class="btn-complete"
                                    onclick="confirmComplete(<?php echo $a['id']; ?>, '<?php echo addslashes(htmlspecialchars($fullName)); ?>')"
                                >
                                    <i class="fas fa-circle-check"></i>
                                    Mark Completed
                                </button>
                            <?php else: ?>
                                <span class="done-dash">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div id="noResults">
                <div class="empty-icon" style="width:48px;height:48px;font-size:20px;margin:0 auto;">
                    <i class="fas fa-magnifying-glass"></i>
                </div>
                <p style="font-size:13.5px;font-weight:600;color:var(--text-muted);text-align:center;margin-top:10px;">
                    No appointments match your filters.
                </p>
            </div>

            <div class="table-footer">
                <span class="table-footer-info">
                    <i class="fas fa-calendar-check"></i>
                    <span id="footerCount"><?php echo $total; ?></span> appointment<?php echo $total !== 1 ? 's' : ''; ?>
                </span>
                <span class="table-footer-info">
                    <i class="fas fa-circle-check"></i>
                    healthrecord_db · appointments
                </span>
            </div>
        </div>

        <?php else: ?>

        <div class="empty-state">
            <div class="empty-icon"><i class="fas fa-calendar-days"></i></div>
            <h3>No appointments yet</h3>
            <p>Your appointment schedule is empty. Appointments will appear here once patients book with you.</p>
        </div>

        <?php endif; ?>

    </main>
</div>

<!-- ── Confirm Complete Modal ────────────────────── -->
<div class="modal-backdrop" id="completeModal">
    <div class="modal-box">
        <div class="modal-icon"><i class="fas fa-circle-check"></i></div>
        <h3>Mark as Completed?</h3>
        <p id="completeModalText">This will mark the appointment as completed.</p>
        <div class="modal-actions">
            <button class="btn-modal-cancel" onclick="closeModal()">Cancel</button>
            <button class="btn-modal-confirm" id="confirmCompleteBtn">
                <i class="fas fa-circle-check"></i>
                Confirm
            </button>
        </div>
    </div>
</div>

<!-- Hidden form for POST submission -->
<form method="POST" id="completeForm" style="display:none;">
    <input type="hidden" name="complete_id" id="completeIdInput">
</form>

<script>
    /* ── Confirm complete modal ── */
    function confirmComplete(id, name) {
        document.getElementById('completeModalText').textContent =
            'Mark the appointment with ' + name + ' as completed? This cannot be undone.';
        document.getElementById('completeIdInput').value = id;
        document.getElementById('completeModal').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    document.getElementById('confirmCompleteBtn').addEventListener('click', function () {
        document.getElementById('completeForm').submit();
    });

    function closeModal() {
        document.getElementById('completeModal').classList.remove('open');
        document.body.style.overflow = '';
    }

    document.getElementById('completeModal').addEventListener('click', function (e) {
        if (e.target === this) closeModal();
    });

    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

    /* ── Filter + search ── */
    const rows   = document.querySelectorAll('#tableBody tr');
    const total  = rows.length;
    let   activeFilter = 'all';

    document.querySelectorAll('.filter-tab').forEach(tab => {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            activeFilter = this.dataset.filter;
            filterTable();
        });
    });

    function filterTable() {
        const search  = document.getElementById('searchInput').value.toLowerCase().trim();
        let   visible = 0;

        rows.forEach(row => {
            const matchFilter = activeFilter === 'all' || row.dataset.status === activeFilter;
            const matchSearch = !search || row.dataset.name.includes(search);
            const show = matchFilter && matchSearch;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        document.getElementById('visibleCount').textContent = visible;
        document.getElementById('footerCount').textContent  = visible;
        document.getElementById('noResults').classList.toggle('show', visible === 0 && total > 0);
    }

    /* ── Sort ── */
    function sortTable() {
        const tbody  = document.getElementById('tableBody');
        const sortBy = document.getElementById('sortSelect').value;
        const allRows = Array.from(tbody.querySelectorAll('tr'));

        allRows.sort((a, b) => {
            if (sortBy === 'date-desc') return b.dataset.date.localeCompare(a.dataset.date) || b.dataset.time.localeCompare(a.dataset.time);
            if (sortBy === 'date-asc')  return a.dataset.date.localeCompare(b.dataset.date) || a.dataset.time.localeCompare(b.dataset.time);
            if (sortBy === 'name-asc')  return a.dataset.name.localeCompare(b.dataset.name);
            if (sortBy === 'name-desc') return b.dataset.name.localeCompare(a.dataset.name);
        });

        allRows.forEach(row => tbody.appendChild(row));
        /* Re-number rows */
        tbody.querySelectorAll('tr').forEach((row, i) => {
            const num = row.querySelector('.row-num');
            if (num) num.textContent = i + 1;
        });
    }

    /* ── Auto-dismiss toast ── */
    const toast = document.getElementById('successToast');
    if (toast) {
        setTimeout(() => {
            toast.style.transition = 'opacity 0.5s ease';
            toast.style.opacity    = '0';
            setTimeout(() => toast.remove(), 500);
        }, 5000);
    }
</script>

</body>
</html>