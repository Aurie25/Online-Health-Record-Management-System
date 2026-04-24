<?php
session_start();
require_once dirname(__DIR__) . '../db.php';
require_once 'adminauth.php';

$auth = new Auth($conn);
$auth->redirectIfNotLoggedIn();

/* ==========================================
   DATE RANGE FILTER
========================================== */
$start = $_GET['start'] ?? date('Y-m-01');
$end   = $_GET['end']   ?? date('Y-m-d');

/* ==========================================
   1. CORE COUNTS
========================================== */
$totalUsers         = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalDoctors       = $conn->query("SELECT COUNT(*) FROM users WHERE role='doctor'")->fetchColumn();
$totalPatients      = $conn->query("SELECT COUNT(*) FROM users WHERE role='patient'")->fetchColumn();
$totalAdmins        = $conn->query("SELECT COUNT(*) FROM admins")->fetchColumn();
$totalReceptionists = $conn->query("SELECT COUNT(*) FROM users WHERE role='receptionist'")->fetchColumn();

/* ==========================================
   APPOINTMENT ANALYTICS (filtered by date)
========================================== */
$appointmentStats = $conn->prepare("
    SELECT status, COUNT(*) as total
    FROM appointments
    WHERE DATE(created_at) BETWEEN :start AND :end
    GROUP BY status ORDER BY total DESC
");
$appointmentStats->execute([':start' => $start, ':end' => $end]);
$appointments  = $appointmentStats->fetchAll(PDO::FETCH_ASSOC);
$totalAppts    = array_sum(array_column($appointments, 'total')) ?: 1;

/* ==========================================
   MONTHLY APPOINTMENTS CHART
========================================== */
$monthly = $conn->query("
    SELECT DATE_FORMAT(created_at,'%Y-%m') as month, COUNT(*) as total
    FROM appointments
    GROUP BY month ORDER BY month ASC LIMIT 12
")->fetchAll(PDO::FETCH_ASSOC);

$months     = array_map(fn($m) => date('M Y', strtotime($m['month'].'-01')), $monthly);
$userCounts = array_column($monthly, 'total');

/* ==========================================
   RECENT ACTIVITY
========================================== */
$recentActivity = $conn->query("
    SELECT al.action, al.details, al.timestamp, a.full_name
    FROM admin_activity_logs al
    LEFT JOIN admins a ON al.admin_id = a.id
    ORDER BY al.timestamp DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

/* ==========================================
   2. DOCTOR UTILISATION
========================================== */
$doctorLoad = $conn->query("
    SELECT u.first_name, u.last_name,
           COUNT(a.id)                          as total,
           SUM(a.status='completed')            as completed,
           SUM(a.status='cancelled')            as cancelled,
           SUM(a.status='assigned')             as assigned
    FROM users u
    LEFT JOIN appointments a ON u.id = a.doctor_id
    WHERE u.role = 'doctor'
    GROUP BY u.id
    ORDER BY total DESC
")->fetchAll(PDO::FETCH_ASSOC);

/* ==========================================
   3. SCHEDULE FILL RATE
========================================== */
$fillRateRow = $conn->query("
    SELECT
        COUNT(*)                                                AS total_slots,
        SUM(is_booked)                                         AS booked_slots,
        ROUND(SUM(is_booked)/NULLIF(COUNT(*),0)*100, 1)       AS fill_pct
    FROM doctor_schedule
    WHERE available_date >= CURDATE() - INTERVAL 30 DAY
")->fetch(PDO::FETCH_ASSOC);
$fillRate = $fillRateRow ?: ['total_slots'=>0,'booked_slots'=>0,'fill_pct'=>0];

/* ==========================================
   4. UNREAD MESSAGES BACKLOG
========================================== */
$unreadMessages = $conn->query("SELECT COUNT(*) FROM messages WHERE is_read=0")->fetchColumn();
$msgTrend = $conn->query("
    SELECT DATE_FORMAT(created_at,'%Y-%m') as month, COUNT(*) as total
    FROM messages GROUP BY month ORDER BY month DESC LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

/* ==========================================
   5. PATIENT FILE UPLOADS OVER TIME
========================================== */
$uploadStats = $conn->query("
    SELECT DATE_FORMAT(uploaded_at,'%Y-%m') as month, COUNT(*) as total
    FROM uploads GROUP BY month ORDER BY month DESC LIMIT 12
")->fetchAll(PDO::FETCH_ASSOC);
$totalUploads = $conn->query("SELECT COUNT(*) FROM uploads")->fetchColumn();

/* ==========================================
   6. GENDER & AGE DEMOGRAPHICS
========================================== */
$genderSplit = $conn->query("
    SELECT gender, COUNT(*) as total FROM users
    WHERE role='patient' GROUP BY gender
")->fetchAll(PDO::FETCH_ASSOC);

$ageBrackets = $conn->query("
    SELECT
        CASE
            WHEN TIMESTAMPDIFF(YEAR,date_of_birth,CURDATE()) < 18 THEN 'Under 18'
            WHEN TIMESTAMPDIFF(YEAR,date_of_birth,CURDATE()) < 35 THEN '18-34'
            WHEN TIMESTAMPDIFF(YEAR,date_of_birth,CURDATE()) < 55 THEN '35-54'
            ELSE '55+'
        END as bracket,
        COUNT(*) as total
    FROM users WHERE role='patient'
    GROUP BY bracket ORDER BY bracket
")->fetchAll(PDO::FETCH_ASSOC);

/* ==========================================
   6b. DOCTOR AVAILABILITY STATUS
========================================== */
$docStatus = $conn->query("
    SELECT status, COUNT(*) as total FROM doctor_profiles GROUP BY status
")->fetchAll(PDO::FETCH_ASSOC);

/* ==========================================
   7. TOP CONDITIONS BY PATIENT COUNT
========================================== */
$conditionStats = $conn->query("
    SELECT mc.condition_name, mc.category,
           COUNT(pc.id)                   as patient_count,
           SUM(pc.status='active')        as active,
           SUM(pc.status='managed')       as managed,
           SUM(pc.status='recovered')     as recovered
    FROM medical_conditions mc
    LEFT JOIN patient_conditions pc ON mc.id = pc.condition_id
    GROUP BY mc.id
    ORDER BY patient_count DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

/* ==========================================
   8. CONDITIONS BY CATEGORY BREAKDOWN
========================================== */
$categoryBreakdown = $conn->query("
    SELECT mc.category, COUNT(pc.patient_id) as patients
    FROM patient_conditions pc
    JOIN medical_conditions mc ON pc.condition_id = mc.id
    WHERE pc.status = 'active'
    GROUP BY mc.category
")->fetchAll(PDO::FETCH_ASSOC);

/* ==========================================
   9. TREATMENT SCHEDULE ADHERENCE
========================================== */
$adherenceRaw = $conn->query("
    SELECT status, COUNT(*) as total FROM treatment_schedules
    WHERE appointment_date <= NOW()
    GROUP BY status
")->fetchAll(PDO::FETCH_ASSOC);
$adherence = array_column($adherenceRaw, 'total', 'status');
$totalTreatments = array_sum($adherence) ?: 1;

/* ==========================================
   10. DISABILITY ASSESSMENTS
========================================== */
$assessmentRow = $conn->query("
    SELECT
        COUNT(*)                              as total,
        ROUND(AVG(severity_percentage),1)     as avg_severity,
        SUM(permanence='permanent')           as permanent,
        SUM(permanence='temporary')           as temporary,
        SUM(prognosis='improving')            as improving,
        SUM(prognosis='stable')               as stable,
        SUM(prognosis='deteriorating')        as deteriorating
    FROM disability_assessments
")->fetch(PDO::FETCH_ASSOC);
$assessmentData = $assessmentRow ?: ['total'=>0,'avg_severity'=>0,'permanent'=>0,'temporary'=>0,'improving'=>0,'stable'=>0,'deteriorating'=>0];

/* ==========================================
   CSV EXPORT
========================================== */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename=apexcare_report_'.date('Y-m-d').'.csv');
    $out = fopen("php://output","w");

    fputcsv($out, ['ApexCare Analytics Report']);
    fputcsv($out, ['Period', "$start to $end"]);
    fputcsv($out, ['Generated', date('Y-m-d H:i:s')]);
    fputcsv($out, []);

    // Core counts
    fputcsv($out, ['--- USER OVERVIEW ---']);
    fputcsv($out, ['Metric', 'Value']);
    fputcsv($out, ['Total Users',        $totalUsers]);
    fputcsv($out, ['Total Doctors',      $totalDoctors]);
    fputcsv($out, ['Total Patients',     $totalPatients]);
    fputcsv($out, ['Total Admins',       $totalAdmins]);
    fputcsv($out, ['Total Receptionists',$totalReceptionists]);
    fputcsv($out, []);

    // Appointments
    fputcsv($out, ['--- APPOINTMENTS (PERIOD) ---']);
    fputcsv($out, ['Status', 'Count']);
    foreach ($appointments as $a) fputcsv($out, [$a['status'], $a['total']]);
    fputcsv($out, []);

    // Doctor utilisation
    fputcsv($out, ['--- DOCTOR UTILISATION ---']);
    fputcsv($out, ['Doctor', 'Total Appointments', 'Completed', 'Cancelled', 'Assigned']);
    foreach ($doctorLoad as $d) {
        fputcsv($out, [
            $d['first_name'].' '.$d['last_name'],
            $d['total'], $d['completed'], $d['cancelled'], $d['assigned']
        ]);
    }
    fputcsv($out, []);

    // Schedule fill
    fputcsv($out, ['--- SCHEDULE FILL RATE (LAST 30 DAYS) ---']);
    fputcsv($out, ['Total Slots',  $fillRate['total_slots']]);
    fputcsv($out, ['Booked Slots', $fillRate['booked_slots']]);
    fputcsv($out, ['Fill Rate %',  $fillRate['fill_pct'].'%']);
    fputcsv($out, []);

    // Messages
    fputcsv($out, ['--- MESSAGES ---']);
    fputcsv($out, ['Unread Messages', $unreadMessages]);
    fputcsv($out, []);

    // Uploads
    fputcsv($out, ['--- PATIENT FILE UPLOADS ---']);
    fputcsv($out, ['Total Uploads', $totalUploads]);
    fputcsv($out, ['Month', 'Count']);
    foreach ($uploadStats as $u) fputcsv($out, [$u['month'], $u['total']]);
    fputcsv($out, []);

    // Demographics
    fputcsv($out, ['--- PATIENT DEMOGRAPHICS ---']);
    fputcsv($out, ['Gender', 'Count']);
    foreach ($genderSplit as $g) fputcsv($out, [$g['gender'], $g['total']]);
    fputcsv($out, ['Age Bracket', 'Count']);
    foreach ($ageBrackets as $ab) fputcsv($out, [$ab['bracket'], $ab['total']]);
    fputcsv($out, []);

    // Doctor availability
    fputcsv($out, ['--- DOCTOR AVAILABILITY ---']);
    fputcsv($out, ['Status', 'Count']);
    foreach ($docStatus as $ds) fputcsv($out, [$ds['status'], $ds['total']]);
    fputcsv($out, []);

    // Conditions
    fputcsv($out, ['--- TOP CONDITIONS BY PATIENT COUNT ---']);
    fputcsv($out, ['Condition', 'Category', 'Total Patients', 'Active', 'Managed', 'Recovered']);
    foreach ($conditionStats as $c) {
        fputcsv($out, [$c['condition_name'], $c['category'], $c['patient_count'], $c['active'], $c['managed'], $c['recovered']]);
    }
    fputcsv($out, []);

    // Category breakdown
    fputcsv($out, ['--- CONDITIONS BY CATEGORY ---']);
    fputcsv($out, ['Category', 'Active Patients']);
    foreach ($categoryBreakdown as $cb) fputcsv($out, [$cb['category'], $cb['patients']]);
    fputcsv($out, []);

    // Treatment adherence
    fputcsv($out, ['--- TREATMENT SCHEDULE ADHERENCE ---']);
    fputcsv($out, ['Status', 'Count']);
    foreach ($adherenceRaw as $ar) fputcsv($out, [$ar['status'], $ar['total']]);
    fputcsv($out, []);

    // Disability assessments
    fputcsv($out, ['--- DISABILITY ASSESSMENTS ---']);
    fputcsv($out, ['Total Assessments',         $assessmentData['total']]);
    fputcsv($out, ['Avg Severity %',            $assessmentData['avg_severity'].'%']);
    fputcsv($out, ['Permanent',                 $assessmentData['permanent']]);
    fputcsv($out, ['Temporary',                 $assessmentData['temporary']]);
    fputcsv($out, ['Prognosis: Improving',      $assessmentData['improving']]);
    fputcsv($out, ['Prognosis: Stable',         $assessmentData['stable']]);
    fputcsv($out, ['Prognosis: Deteriorating',  $assessmentData['deteriorating']]);

    fclose($out);
    exit;
}

/* ==========================================
   PDF EXPORT
========================================== */
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    echo '<!DOCTYPE html><html><head><title>ApexCare Report</title>
    <style>
        body{font-family:sans-serif;padding:40px;color:#0f172a}
        h1{color:#3b82f6;margin-bottom:4px}
        h2{font-size:14px;color:#475569;text-transform:uppercase;letter-spacing:0.5px;margin:28px 0 8px;border-bottom:2px solid #e2e8f0;padding-bottom:6px}
        p{color:#475569;font-size:13px;margin-bottom:16px}
        table{width:100%;border-collapse:collapse;margin-bottom:20px;font-size:13px}
        th{background:#f8fafc;font-size:11px;text-transform:uppercase;letter-spacing:0.5px;color:#94a3b8;padding:9px 12px;text-align:left;border:1px solid #e2e8f0}
        td{border:1px solid #e2e8f0;padding:9px 12px}
        tr:nth-child(even) td{background:#f8fafc}
        .badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600}
        .badge-blue{background:#eff6ff;color:#1d4ed8}
        .badge-green{background:#ecfdf5;color:#047857}
        .badge-red{background:#fef2f2;color:#b91c1c}
        .badge-yellow{background:#fffbeb;color:#92400e}
        .badge-purple{background:#f5f3ff;color:#5b21b6}
        .two-col{display:grid;grid-template-columns:1fr 1fr;gap:20px}
        .stat-box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px 18px;margin-bottom:12px}
        .stat-box .val{font-size:26px;font-weight:700;color:#0f172a}
        .stat-box .lbl{font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;margin-top:2px}
        @media print{body{padding:20px}}
    </style></head><body>';

    echo '<h1>ApexCare Analytics Report</h1>';
    echo '<p>Period: <strong>'.$start.'</strong> to <strong>'.$end.'</strong> &nbsp;|&nbsp; Generated: '.date('d M Y, H:i').'</p>';

    // Section 1
    echo '<h2>User Overview</h2>';
    echo '<table><tr><th>Role</th><th>Count</th></tr>';
    foreach ([
        ['Total Users','users',$totalUsers,'badge-blue'],
        ['Doctors','user-md',$totalDoctors,'badge-blue'],
        ['Patients','user-injured',$totalPatients,'badge-green'],
        ['Admins','shield',$totalAdmins,'badge-purple'],
        ['Receptionists','headset',$totalReceptionists,'badge-yellow'],
    ] as $row) {
        echo '<tr><td>'.$row[0].'</td><td><span class="badge '.$row[3].'">'.$row[2].'</span></td></tr>';
    }
    echo '</table>';

    // Section 2: Appointments
    echo '<h2>Appointment Status — Selected Period</h2>';
    echo '<table><tr><th>Status</th><th>Count</th><th>Percentage</th></tr>';
    foreach ($appointments as $a) {
        $pct = round($a['total']/$totalAppts*100);
        echo '<tr><td>'.ucfirst($a['status']).'</td><td>'.$a['total'].'</td><td>'.$pct.'%</td></tr>';
    }
    echo '</table>';

    // Section 3: Doctor utilisation
    echo '<h2>Doctor Utilisation</h2>';
    echo '<table><tr><th>Doctor</th><th>Total</th><th>Completed</th><th>Cancelled</th><th>Assigned</th></tr>';
    foreach ($doctorLoad as $d) {
        echo '<tr><td>Dr. '.htmlspecialchars($d['first_name'].' '.$d['last_name']).'</td><td>'.$d['total'].'</td><td>'.$d['completed'].'</td><td>'.$d['cancelled'].'</td><td>'.$d['assigned'].'</td></tr>';
    }
    echo '</table>';

    // Section 4: Schedule fill
    echo '<h2>Schedule Fill Rate (Last 30 Days)</h2>';
    echo '<table><tr><th>Total Slots</th><th>Booked</th><th>Fill Rate</th></tr>';
    echo '<tr><td>'.$fillRate['total_slots'].'</td><td>'.$fillRate['booked_slots'].'</td><td><strong>'.$fillRate['fill_pct'].'%</strong></td></tr>';
    echo '</table>';

    // Section 5: Demographics
    echo '<h2>Patient Demographics</h2>';
    echo '<div class="two-col">';
    echo '<div><table><tr><th>Gender</th><th>Count</th></tr>';
    foreach ($genderSplit as $g) echo '<tr><td>'.ucfirst($g['gender']).'</td><td>'.$g['total'].'</td></tr>';
    echo '</table></div>';
    echo '<div><table><tr><th>Age Bracket</th><th>Count</th></tr>';
    foreach ($ageBrackets as $ab) echo '<tr><td>'.$ab['bracket'].'</td><td>'.$ab['total'].'</td></tr>';
    echo '</table></div>';
    echo '</div>';

    // Section 6: Doctor availability
    echo '<h2>Doctor Availability Status</h2>';
    echo '<table><tr><th>Status</th><th>Count</th></tr>';
    foreach ($docStatus as $ds) echo '<tr><td>'.ucfirst($ds['status']).'</td><td>'.$ds['total'].'</td></tr>';
    echo '</table>';

    // Section 7: Top conditions
    echo '<h2>Top Conditions by Patient Count</h2>';
    echo '<table><tr><th>Condition</th><th>Category</th><th>Patients</th><th>Active</th><th>Managed</th><th>Recovered</th></tr>';
    foreach ($conditionStats as $c) {
        echo '<tr><td>'.htmlspecialchars($c['condition_name']).'</td><td>'.ucfirst($c['category']).'</td><td>'.$c['patient_count'].'</td><td>'.$c['active'].'</td><td>'.$c['managed'].'</td><td>'.$c['recovered'].'</td></tr>';
    }
    echo '</table>';

    // Section 8: Category breakdown
    echo '<h2>Conditions by Category (Active Patients)</h2>';
    echo '<table><tr><th>Category</th><th>Active Patients</th></tr>';
    foreach ($categoryBreakdown as $cb) echo '<tr><td>'.ucfirst($cb['category']).'</td><td>'.$cb['patients'].'</td></tr>';
    echo '</table>';

    // Section 9: Treatment adherence
    echo '<h2>Treatment Schedule Adherence</h2>';
    echo '<table><tr><th>Status</th><th>Count</th><th>Percentage</th></tr>';
    foreach ($adherenceRaw as $ar) {
        $pct = round($ar['total']/$totalTreatments*100);
        echo '<tr><td>'.ucfirst($ar['status']).'</td><td>'.$ar['total'].'</td><td>'.$pct.'%</td></tr>';
    }
    echo '</table>';

    // Section 10: Disability assessments
    echo '<h2>Disability Assessments</h2>';
    echo '<table><tr><th>Metric</th><th>Value</th></tr>';
    echo '<tr><td>Total Assessments Issued</td><td>'.$assessmentData['total'].'</td></tr>';
    echo '<tr><td>Average Severity</td><td>'.$assessmentData['avg_severity'].'%</td></tr>';
    echo '<tr><td>Permanent</td><td>'.$assessmentData['permanent'].'</td></tr>';
    echo '<tr><td>Temporary</td><td>'.$assessmentData['temporary'].'</td></tr>';
    echo '<tr><td>Prognosis: Improving</td><td>'.$assessmentData['improving'].'</td></tr>';
    echo '<tr><td>Prognosis: Stable</td><td>'.$assessmentData['stable'].'</td></tr>';
    echo '<tr><td>Prognosis: Deteriorating</td><td>'.$assessmentData['deteriorating'].'</td></tr>';
    echo '</table>';

    // Uploads
    echo '<h2>Patient File Uploads</h2>';
    echo '<table><tr><th>Month</th><th>Files Uploaded</th></tr>';
    foreach ($uploadStats as $u) echo '<tr><td>'.$u['month'].'</td><td>'.$u['total'].'</td></tr>';
    echo '</table>';

    echo '<script>window.print();</script></body></html>';
    exit;
}

/* ==========================================
   HELPERS
========================================== */
function apptClass(string $s): string {
    return match(strtolower($s)) {
        'confirmed'           => 'confirmed',
        'pending'             => 'pending',
        'cancelled','canceled'=> 'cancelled',
        'completed'           => 'completed',
        default               => 'other'
    };
}
function activityDotClass(string $a): string {
    return str_contains(strtolower($a),'login') ? 'login' : 'default';
}
function categoryColor(string $cat): string {
    return match($cat) {
        'chronic'    => 'blue',
        'disability' => 'orange',
        'temporary'  => 'green',
        'mental'     => 'purple',
        default      => 'gray'
    };
}
function adherenceColor(string $s): string {
    return match(strtolower($s)) {
        'completed' => 'green',
        'missed'    => 'red',
        'cancelled' => 'orange',
        'scheduled' => 'blue',
        default     => 'gray'
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enterprise Analytics — ApexCare Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../static/admin_sidebar.css">
    <link rel="stylesheet" href="../static/reports.css">
</head>
<body>

<?php include '../static/includes/admin_sidebar.php'; ?>
<button class="sidebar-toggle" onclick="toggleSidebar()" aria-label="Toggle sidebar"><i class="fas fa-bars"></i></button>
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<main class="main-content">

    <!-- ── Page Header ── -->
    <div class="page-header">
        <div class="page-header-left">
            <h1>
                <span class="header-icon"><i class="fas fa-chart-pie"></i></span>
                Enterprise Analytics
            </h1>
            <p class="page-header-sub">Real-time system insights &amp; reporting for ApexCare</p>
        </div>
        <div class="header-date"><i class="fas fa-calendar-day"></i> <?php echo date('d M Y'); ?></div>
    </div>

    <!-- ── Date Filter ── -->
    <form method="GET" class="filter-bar">
        <span class="filter-label"><i class="fas fa-sliders"></i> Filter Period</span>
        <div class="filter-group">
            <span>From</span>
            <input type="date" name="start" class="filter-input" value="<?php echo htmlspecialchars($start); ?>">
        </div>
        <span class="filter-divider">→</span>
        <div class="filter-group">
            <span>To</span>
            <input type="date" name="end" class="filter-input" value="<?php echo htmlspecialchars($end); ?>">
        </div>
        <button type="submit" class="btn-filter"><i class="fas fa-magnifying-glass"></i> Apply</button>
        <div class="filter-period-pills">
            <a href="?start=<?php echo date('Y-m-d'); ?>&end=<?php echo date('Y-m-d'); ?>" class="period-pill">Today</a>
            <a href="?start=<?php echo date('Y-m-d',strtotime('-7 days')); ?>&end=<?php echo date('Y-m-d'); ?>" class="period-pill">7 days</a>
            <a href="?start=<?php echo date('Y-m-01'); ?>&end=<?php echo date('Y-m-d'); ?>" class="period-pill">This month</a>
            <a href="?start=<?php echo date('Y-01-01'); ?>&end=<?php echo date('Y-m-d'); ?>" class="period-pill">This year</a>
        </div>
    </form>

    <!-- ── Stats Grid ── -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-value"><?php echo number_format($totalUsers); ?></div>
            <div class="stat-label">Total Users</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-user-md"></i></div>
            <div class="stat-value"><?php echo number_format($totalDoctors); ?></div>
            <div class="stat-label">Doctors</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-user-injured"></i></div>
            <div class="stat-value"><?php echo number_format($totalPatients); ?></div>
            <div class="stat-label">Patients</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-user-shield"></i></div>
            <div class="stat-value"><?php echo number_format($totalAdmins); ?></div>
            <div class="stat-label">Admins</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-user-nurse"></i></div>
            <div class="stat-value"><?php echo number_format($totalReceptionists); ?></div>
            <div class="stat-label">Receptionists</div>
        </div>
    </div>

    <!-- ── ROW 1: Chart + Appointments ── -->
    <div class="content-row col-2">

        <div class="report-card d1">
            <div class="card-head">
                <div class="card-head-left">
                    <span class="card-icon blue"><i class="fas fa-chart-line"></i></span>
                    <div>
                        <div class="card-title">Appointment Trends</div>
                        <div class="card-sub">Monthly appointment volume</div>
                    </div>
                </div>
                <span class="card-pill"><?php echo count($monthly); ?> months</span>
            </div>
            <div class="card-body">
                <div class="chart-wrap"><canvas id="userChart"></canvas></div>
            </div>
        </div>

        <div class="report-card d2">
            <div class="card-head">
                <div class="card-head-left">
                    <span class="card-icon orange"><i class="fas fa-calendar-check"></i></span>
                    <div>
                        <div class="card-title">Appointments</div>
                        <div class="card-sub">By status in selected period</div>
                    </div>
                </div>
                <span class="card-pill"><?php echo array_sum(array_column($appointments,'total')); ?> total</span>
            </div>
            <div class="card-body">
                <?php if (empty($appointments)): ?>
                    <div class="appt-empty"><i class="fas fa-calendar-xmark"></i> No appointments in this period</div>
                <?php else: ?>
                    <div class="appt-list">
                        <?php foreach ($appointments as $a):
                            $cls = apptClass($a['status']);
                            $pct = round($a['total']/$totalAppts*100);
                        ?>
                        <div class="appt-row <?php echo $cls; ?>">
                            <div class="appt-dot"></div>
                            <div class="appt-name"><?php echo htmlspecialchars($a['status']); ?></div>
                            <div class="appt-bar-track">
                                <div class="appt-bar-fill" data-pct="<?php echo $pct; ?>" style="width:0"></div>
                            </div>
                            <div class="appt-count"><?php echo $a['total']; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── ROW 2: Doctor Utilisation (full width) ── -->
    <div class="content-row col-full">
        <div class="report-card d1">
            <div class="card-head">
                <div class="card-head-left">
                    <span class="card-icon blue"><i class="fas fa-user-md"></i></span>
                    <div>
                        <div class="card-title">Doctor Utilisation</div>
                        <div class="card-sub">Appointment load per doctor — all time</div>
                    </div>
                </div>
                <span class="card-pill"><?php echo count($doctorLoad); ?> doctors</span>
            </div>
            <div class="card-body no-pad">
                <?php if (empty($doctorLoad)): ?>
                    <div class="appt-empty"><i class="fas fa-user-md"></i> No doctor data available</div>
                <?php else: ?>
                    <div class="data-table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Doctor</th>
                                    <th>Total</th>
                                    <th>Completed</th>
                                    <th>Cancelled</th>
                                    <th>Assigned</th>
                                    <th>Load</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $maxLoad = max(array_column($doctorLoad,'total')) ?: 1;
                                foreach ($doctorLoad as $d):
                                    $loadPct = $maxLoad > 0 ? round($d['total']/$maxLoad*100) : 0;
                                ?>
                                <tr>
                                    <td><span class="doc-name">Dr. <?php echo htmlspecialchars($d['first_name'].' '.$d['last_name']); ?></span></td>
                                    <td><strong><?php echo $d['total']; ?></strong></td>
                                    <td><span class="badge badge-green"><?php echo $d['completed']; ?></span></td>
                                    <td><span class="badge badge-red"><?php echo $d['cancelled']; ?></span></td>
                                    <td><span class="badge badge-blue"><?php echo $d['assigned']; ?></span></td>
                                    <td style="min-width:100px">
                                        <div class="load-bar-track">
                                            <div class="load-bar-fill" data-pct="<?php echo $loadPct; ?>" style="width:0"></div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── ROW 3: Schedule Fill + Doctor Availability + Messages ── -->
    <div class="content-row col-3">

        <div class="report-card d2">
            <div class="card-head">
                <div class="card-head-left">
                    <span class="card-icon green"><i class="fas fa-calendar-alt"></i></span>
                    <div>
                        <div class="card-title">Schedule Fill Rate</div>
                        <div class="card-sub">Last 30 days</div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="fill-rate-display">
                    <div class="fill-rate-circle" data-pct="<?php echo $fillRate['fill_pct']; ?>">
                        <svg viewBox="0 0 80 80" class="donut-svg">
                            <circle class="donut-bg" cx="40" cy="40" r="32"/>
                            <circle class="donut-fill" cx="40" cy="40" r="32"
                                stroke-dasharray="<?php echo round($fillRate['fill_pct'] * 2.01); ?> 201"
                                stroke-dashoffset="50"/>
                        </svg>
                        <div class="fill-rate-text">
                            <span class="fill-pct"><?php echo $fillRate['fill_pct']; ?>%</span>
                            <span class="fill-label">booked</span>
                        </div>
                    </div>
                    <div class="fill-rate-stats">
                        <div class="fill-stat"><span class="fs-val"><?php echo $fillRate['booked_slots']; ?></span><span class="fs-lbl">Booked</span></div>
                        <div class="fill-stat"><span class="fs-val"><?php echo $fillRate['total_slots'] - $fillRate['booked_slots']; ?></span><span class="fs-lbl">Open</span></div>
                        <div class="fill-stat"><span class="fs-val"><?php echo $fillRate['total_slots']; ?></span><span class="fs-lbl">Total</span></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="report-card d3">
            <div class="card-head">
                <div class="card-head-left">
                    <span class="card-icon orange"><i class="fas fa-stethoscope"></i></span>
                    <div>
                        <div class="card-title">Doctor Availability</div>
                        <div class="card-sub">Current status</div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($docStatus)): ?>
                    <div class="appt-empty">No data</div>
                <?php else: ?>
                    <div class="appt-list">
                    <?php foreach ($docStatus as $ds):
                        $dsClass = match($ds['status']) {
                            'working'  => 'completed',
                            'onleave'  => 'pending',
                            'absent'   => 'cancelled',
                            default    => 'other'
                        };
                        $total = array_sum(array_column($docStatus,'total')) ?: 1;
                        $pct   = round($ds['total']/$total*100);
                    ?>
                    <div class="appt-row <?php echo $dsClass; ?>">
                        <div class="appt-dot"></div>
                        <div class="appt-name"><?php echo ucfirst($ds['status']); ?></div>
                        <div class="appt-bar-track">
                            <div class="appt-bar-fill" data-pct="<?php echo $pct; ?>" style="width:0"></div>
                        </div>
                        <div class="appt-count"><?php echo $ds['total']; ?></div>
                    </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="report-card d4">
            <div class="card-head">
                <div class="card-head-left">
                    <span class="card-icon purple"><i class="fas fa-envelope"></i></span>
                    <div>
                        <div class="card-title">Messages</div>
                        <div class="card-sub">Inbox &amp; unread backlog</div>
                    </div>
                </div>
                <?php if ($unreadMessages > 0): ?>
                    <span class="card-pill" style="background:var(--red-light);color:var(--red)"><?php echo $unreadMessages; ?> unread</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="msg-unread-badge <?php echo $unreadMessages > 0 ? 'has-unread' : ''; ?>">
                    <i class="fas fa-<?php echo $unreadMessages > 0 ? 'envelope' : 'envelope-open'; ?>"></i>
                    <span><?php echo $unreadMessages; ?></span>
                    <small>unread messages</small>
                </div>
                <div style="margin-top:16px">
                    <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.6px;margin-bottom:8px">Recent months</div>
                    <?php foreach (array_slice($msgTrend,0,4) as $m): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0;border-bottom:1px solid var(--border);font-size:12.5px">
                        <span style="color:var(--text-secondary);font-family:'DM Mono',monospace"><?php echo $m['month']; ?></span>
                        <span style="font-weight:700;color:var(--text-primary)"><?php echo $m['total']; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ── ROW 4: Demographics ── -->
    <div class="content-row col-2">

        <div class="report-card d1">
            <div class="card-head">
                <div class="card-head-left">
                    <span class="card-icon cyan"><i class="fas fa-venus-mars"></i></span>
                    <div>
                        <div class="card-title">Patient Demographics</div>
                        <div class="card-sub">Gender &amp; age distribution</div>
                    </div>
                </div>
                <span class="card-pill"><?php echo number_format($totalPatients); ?> patients</span>
            </div>
            <div class="card-body">
                <div class="demo-grid">
                    <div>
                        <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.6px;margin-bottom:12px">By Gender</div>
                        <div class="chart-wrap" style="height:180px"><canvas id="genderChart"></canvas></div>
                    </div>
                    <div>
                        <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.6px;margin-bottom:12px">By Age Bracket</div>
                        <div class="chart-wrap" style="height:180px"><canvas id="ageChart"></canvas></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="report-card d2">
            <div class="card-head">
                <div class="card-head-left">
                    <span class="card-icon green"><i class="fas fa-file-upload"></i></span>
                    <div>
                        <div class="card-title">Patient File Uploads</div>
                        <div class="card-sub">Upload volume over time</div>
                    </div>
                </div>
                <span class="card-pill"><?php echo number_format($totalUploads); ?> total</span>
            </div>
            <div class="card-body">
                <div class="chart-wrap" style="height:220px"><canvas id="uploadsChart"></canvas></div>
            </div>
        </div>
    </div>

    <!-- ── ROW 5: Top Conditions + Category Breakdown ── -->
    <div class="content-row col-2">

        <div class="report-card d1">
            <div class="card-head">
                <div class="card-head-left">
                    <span class="card-icon red"><i class="fas fa-heartbeat"></i></span>
                    <div>
                        <div class="card-title">Top Conditions</div>
                        <div class="card-sub">By patient count — all statuses</div>
                    </div>
                </div>
                <span class="card-pill"><?php echo count($conditionStats); ?> conditions</span>
            </div>
            <div class="card-body no-pad">
                <?php if (empty($conditionStats)): ?>
                    <div class="appt-empty" style="padding:32px"><i class="fas fa-notes-medical" style="display:block;font-size:24px;margin-bottom:8px"></i>No condition data yet</div>
                <?php else: ?>
                    <div class="data-table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr><th>#</th><th>Condition</th><th>Category</th><th>Patients</th><th>Active</th><th>Managed</th><th>Recovered</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($conditionStats as $i => $c): ?>
                                <tr>
                                    <td style="color:var(--text-muted);font-family:'DM Mono',monospace;font-size:11px"><?php echo $i+1; ?></td>
                                    <td><strong><?php echo htmlspecialchars($c['condition_name']); ?></strong></td>
                                    <td><span class="badge badge-<?php echo categoryColor($c['category']); ?>"><?php echo ucfirst($c['category']); ?></span></td>
                                    <td><strong><?php echo $c['patient_count']; ?></strong></td>
                                    <td><span class="badge badge-red"><?php echo $c['active']; ?></span></td>
                                    <td><span class="badge badge-yellow"><?php echo $c['managed']; ?></span></td>
                                    <td><span class="badge badge-green"><?php echo $c['recovered']; ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="report-card d2">
            <div class="card-head">
                <div class="card-head-left">
                    <span class="card-icon purple"><i class="fas fa-layer-group"></i></span>
                    <div>
                        <div class="card-title">Conditions by Category</div>
                        <div class="card-sub">Active patients per category</div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($categoryBreakdown)): ?>
                    <div class="appt-empty"><i class="fas fa-layer-group" style="display:block;font-size:24px;margin-bottom:8px"></i>No data yet</div>
                <?php else: ?>
                    <div class="chart-wrap" style="height:200px"><canvas id="categoryChart"></canvas></div>
                    <div class="appt-list" style="margin-top:16px">
                        <?php
                        $catTotal = array_sum(array_column($categoryBreakdown,'patients')) ?: 1;
                        $catColors = ['chronic'=>'blue','disability'=>'orange','temporary'=>'green','mental'=>'purple','other'=>'gray'];
                        foreach ($categoryBreakdown as $cb):
                            $cls = $catColors[$cb['category']] ?? 'gray';
                            $pct = round($cb['patients']/$catTotal*100);
                        ?>
                        <div class="appt-row <?php echo $cls === 'blue' ? 'completed' : ($cls === 'orange' ? 'pending' : ($cls === 'green' ? 'confirmed' : ($cls === 'purple' ? 'other' : 'other'))); ?>">
                            <div class="appt-dot"></div>
                            <div class="appt-name"><?php echo ucfirst($cb['category']); ?></div>
                            <div class="appt-bar-track">
                                <div class="appt-bar-fill" data-pct="<?php echo $pct; ?>" style="width:0"></div>
                            </div>
                            <div class="appt-count"><?php echo $cb['patients']; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── ROW 6: Treatment Adherence + Disability Assessments ── -->
    <div class="content-row col-2">

        <div class="report-card d1">
            <div class="card-head">
                <div class="card-head-left">
                    <span class="card-icon orange"><i class="fas fa-clipboard-list"></i></span>
                    <div>
                        <div class="card-title">Treatment Adherence</div>
                        <div class="card-sub">Scheduled sessions — all time</div>
                    </div>
                </div>
                <span class="card-pill"><?php echo array_sum($adherence); ?> sessions</span>
            </div>
            <div class="card-body">
                <?php if (empty($adherenceRaw)): ?>
                    <div class="appt-empty"><i class="fas fa-clipboard-list" style="display:block;font-size:24px;margin-bottom:8px"></i>No treatment data yet</div>
                <?php else: ?>
                    <div class="chart-wrap" style="height:200px"><canvas id="adherenceChart"></canvas></div>
                    <div class="appt-list" style="margin-top:16px">
                    <?php foreach ($adherenceRaw as $ar):
                        $cls = adherenceColor($ar['status']);
                        $pct = round($ar['total']/$totalTreatments*100);
                    ?>
                    <div class="appt-row <?php echo match($cls){ 'green'=>'confirmed','red'=>'cancelled','orange'=>'pending',default=>'completed' }; ?>">
                        <div class="appt-dot"></div>
                        <div class="appt-name"><?php echo ucfirst($ar['status']); ?></div>
                        <div class="appt-bar-track">
                            <div class="appt-bar-fill" data-pct="<?php echo $pct; ?>" style="width:0"></div>
                        </div>
                        <div class="appt-count"><?php echo $ar['total']; ?></div>
                    </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="report-card d2">
            <div class="card-head">
                <div class="card-head-left">
                    <span class="card-icon red"><i class="fas fa-file-medical-alt"></i></span>
                    <div>
                        <div class="card-title">Disability Assessments</div>
                        <div class="card-sub">Issued certificates &amp; prognosis</div>
                    </div>
                </div>
                <span class="card-pill"><?php echo $assessmentData['total']; ?> total</span>
            </div>
            <div class="card-body">
                <div class="assess-grid">
                    <div class="assess-stat-box">
                        <div class="assess-val"><?php echo $assessmentData['total']; ?></div>
                        <div class="assess-lbl">Assessments issued</div>
                    </div>
                    <div class="assess-stat-box">
                        <div class="assess-val"><?php echo $assessmentData['avg_severity']; ?>%</div>
                        <div class="assess-lbl">Avg severity</div>
                    </div>
                    <div class="assess-stat-box">
                        <div class="assess-val"><?php echo $assessmentData['permanent']; ?></div>
                        <div class="assess-lbl">Permanent</div>
                    </div>
                    <div class="assess-stat-box">
                        <div class="assess-val"><?php echo $assessmentData['temporary']; ?></div>
                        <div class="assess-lbl">Temporary</div>
                    </div>
                </div>
                <div style="margin-top:18px">
                    <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.6px;margin-bottom:10px">Prognosis Breakdown</div>
                    <?php
                    $prognosisData = [
                        ['label'=>'Improving',    'val'=>$assessmentData['improving'],    'cls'=>'confirmed'],
                        ['label'=>'Stable',       'val'=>$assessmentData['stable'],       'cls'=>'completed'],
                        ['label'=>'Deteriorating','val'=>$assessmentData['deteriorating'],'cls'=>'cancelled'],
                    ];
                    $progTotal = ($assessmentData['improving'] + $assessmentData['stable'] + $assessmentData['deteriorating']) ?: 1;
                    foreach ($prognosisData as $pd):
                        $pct = round($pd['val']/$progTotal*100);
                    ?>
                    <div class="appt-row <?php echo $pd['cls']; ?>" style="margin-bottom:8px">
                        <div class="appt-dot"></div>
                        <div class="appt-name"><?php echo $pd['label']; ?></div>
                        <div class="appt-bar-track">
                            <div class="appt-bar-fill" data-pct="<?php echo $pct; ?>" style="width:0"></div>
                        </div>
                        <div class="appt-count"><?php echo $pd['val']; ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ── ROW 7: Recent Activity + Export ── -->
    <div class="content-row col-2">

        <div class="report-card d3">
            <div class="card-head">
                <div class="card-head-left">
                    <span class="card-icon purple"><i class="fas fa-bolt"></i></span>
                    <div>
                        <div class="card-title">Recent Activity</div>
                        <div class="card-sub">Latest system events</div>
                    </div>
                </div>
                <span class="card-pill">Live</span>
            </div>
            <div class="card-body no-pad">
                <?php if (empty($recentActivity)): ?>
                    <div style="padding:40px;text-align:center;color:var(--text-muted);font-size:13.5px;font-weight:500;">
                        <i class="fas fa-inbox" style="font-size:28px;display:block;margin-bottom:10px;color:var(--border-strong);"></i>No recent activity
                    </div>
                <?php else: ?>
                    <?php foreach ($recentActivity as $log): ?>
                    <div class="activity-item">
                        <div class="activity-dot-col">
                            <div class="a-dot <?php echo activityDotClass($log['action']); ?>"></div>
                            <div class="a-line"></div>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div class="activity-action">
                                <?php echo htmlspecialchars($log['action']); ?>
                                <?php if(!empty($log['full_name'])): ?>
                                    <span style="font-weight:400;color:var(--text-muted);font-size:12px;">by <?php echo htmlspecialchars($log['full_name']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="activity-detail"><?php echo htmlspecialchars($log['details']); ?></div>
                        </div>
                        <div class="activity-time"><?php echo date('d M · H:i', strtotime($log['timestamp'])); ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Export Card -->
        <div class="report-card d4">
            <div class="card-head">
                <div class="card-head-left">
                    <span class="card-icon green"><i class="fas fa-file-arrow-down"></i></span>
                    <div>
                        <div class="card-title">Export Reports</div>
                        <div class="card-sub">Download full analytics package</div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <p style="font-size:13.5px;color:var(--text-secondary);margin-bottom:20px;line-height:1.6;">
                    Export includes all 10 analytics sections for the period
                    <strong style="color:var(--text-primary);font-family:'DM Mono',monospace;font-size:12px;"><?php echo $start; ?> → <?php echo $end; ?></strong>.
                </p>
                <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:20px">
                    <a href="?export=csv&start=<?php echo $start; ?>&end=<?php echo $end; ?>" class="btn-export csv">
                        <i class="fas fa-file-csv"></i> Export as CSV
                    </a>
                    <a href="?export=pdf&start=<?php echo $start; ?>&end=<?php echo $end; ?>" class="btn-export pdf" target="_blank">
                        <i class="fas fa-file-pdf"></i> Export as PDF
                    </a>
                </div>
                <div style="background:var(--surface-alt);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px">
                    <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.6px;margin-bottom:10px">Included in export</div>
                    <?php
                    $exportItems = [
                        ['users',       'Users & roles',          number_format($totalUsers).' total'],
                        ['calendar-check','Appointments (period)', array_sum(array_column($appointments,'total')).' records'],
                        ['user-md',     'Doctor utilisation',     count($doctorLoad).' doctors'],
                        ['calendar-alt','Schedule fill rate',     $fillRate['fill_pct'].'%'],
                        ['venus-mars',  'Demographics',           'Gender & age'],
                        ['heartbeat',   'Top conditions',         count($conditionStats).' conditions'],
                        ['layer-group', 'Category breakdown',     count($categoryBreakdown).' categories'],
                        ['clipboard-list','Treatment adherence',  array_sum($adherence).' sessions'],
                        ['file-medical-alt','Disability assessments',$assessmentData['total'].' issued'],
                        ['file-upload', 'Patient uploads',        number_format($totalUploads).' files'],
                    ];
                    foreach ($exportItems as $item): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0;border-bottom:1px solid var(--border);font-size:12.5px">
                        <span style="color:var(--text-secondary);display:flex;align-items:center;gap:7px">
                            <i class="fas fa-<?php echo $item[0]; ?>" style="color:var(--blue);font-size:11px;width:14px"></i>
                            <?php echo $item[1]; ?>
                        </span>
                        <span style="font-family:'DM Mono',monospace;font-size:11.5px;color:var(--text-muted)"><?php echo $item[2]; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
/* ── Shared chart defaults ── */
Chart.defaults.font.family = "'DM Mono', monospace";
Chart.defaults.color = '#94a3b8';

const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
const gridColor = isDark ? 'rgba(255,255,255,0.06)' : '#f1f5f9';

/* ── 1. Appointment Trends ── */
const ctx1 = document.getElementById('userChart').getContext('2d');
const g1 = ctx1.createLinearGradient(0,0,0,260);
g1.addColorStop(0,'rgba(59,130,246,0.18)');
g1.addColorStop(1,'rgba(59,130,246,0)');
new Chart(ctx1,{
    type:'line',
    data:{
        labels:<?php echo json_encode($months); ?>,
        datasets:[{label:'Appointments',data:<?php echo json_encode($userCounts); ?>,borderColor:'#3b82f6',backgroundColor:g1,borderWidth:2.5,pointBackgroundColor:'#3b82f6',pointBorderColor:'#fff',pointBorderWidth:2,pointRadius:5,pointHoverRadius:7,fill:true,tension:0.4}]
    },
    options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},
        plugins:{legend:{display:false},tooltip:{backgroundColor:'#0f172a',titleColor:'#94a3b8',bodyColor:'#f8fafc',padding:12,cornerRadius:10,displayColors:false,callbacks:{title:i=>i[0].label,label:i=>`  ${i.raw} appointments`}}},
        scales:{x:{grid:{display:false},border:{display:false},ticks:{color:'#94a3b8',font:{size:11}}},y:{beginAtZero:true,grid:{color:gridColor},border:{display:false},ticks:{color:'#94a3b8',font:{size:11},stepSize:1,precision:0}}}}
});

/* ── 2. Gender Doughnut ── */
const genderLabels = <?php echo json_encode(array_column($genderSplit,'gender')); ?>;
const genderData   = <?php echo json_encode(array_column($genderSplit,'total')); ?>;
if (genderData.length > 0) {
    new Chart(document.getElementById('genderChart'),{
        type:'doughnut',
        data:{labels:genderLabels,datasets:[{data:genderData,backgroundColor:['#3b82f6','#f97316','#10b981','#8b5cf6','#06b6d4'],borderWidth:0,hoverOffset:6}]},
        options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{padding:12,font:{size:11},usePointStyle:true,pointStyleWidth:8}},tooltip:{backgroundColor:'#0f172a',padding:10,cornerRadius:8}},cutout:'65%'}
    });
}

/* ── 3. Age Bracket Bar ── */
const ageLabels = <?php echo json_encode(array_column($ageBrackets,'bracket')); ?>;
const ageData   = <?php echo json_encode(array_column($ageBrackets,'total')); ?>;
if (ageData.length > 0) {
    new Chart(document.getElementById('ageChart'),{
        type:'bar',
        data:{labels:ageLabels,datasets:[{label:'Patients',data:ageData,backgroundColor:['#3b82f680','#f9731680','#10b98180','#8b5cf680'],borderColor:['#3b82f6','#f97316','#10b981','#8b5cf6'],borderWidth:2,borderRadius:6,borderSkipped:false}]},
        options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{backgroundColor:'#0f172a',padding:10,cornerRadius:8,callbacks:{label:i=>`  ${i.raw} patients`}}},scales:{x:{grid:{display:false},border:{display:false},ticks:{color:'#94a3b8',font:{size:10}}},y:{beginAtZero:true,grid:{color:gridColor},border:{display:false},ticks:{color:'#94a3b8',font:{size:10},precision:0}}}}
    });
}

/* ── 4. Uploads Bar ── */
const uploadLabels = <?php echo json_encode(array_column(array_reverse($uploadStats),'month')); ?>;
const uploadData   = <?php echo json_encode(array_column(array_reverse($uploadStats),'total')); ?>;
if (uploadData.length > 0) {
    new Chart(document.getElementById('uploadsChart'),{
        type:'bar',
        data:{labels:uploadLabels,datasets:[{label:'Uploads',data:uploadData,backgroundColor:'rgba(16,185,129,0.15)',borderColor:'#10b981',borderWidth:2,borderRadius:5,borderSkipped:false}]},
        options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{backgroundColor:'#0f172a',padding:10,cornerRadius:8,callbacks:{label:i=>`  ${i.raw} files`}}},scales:{x:{grid:{display:false},border:{display:false},ticks:{color:'#94a3b8',font:{size:10},maxRotation:45}},y:{beginAtZero:true,grid:{color:gridColor},border:{display:false},ticks:{color:'#94a3b8',font:{size:10},precision:0}}}}
    });
}

/* ── 5. Conditions Pie ── */
const catLabels = <?php echo json_encode(array_column($categoryBreakdown,'category')); ?>;
const catData   = <?php echo json_encode(array_column($categoryBreakdown,'patients')); ?>;
if (catData.length > 0) {
    new Chart(document.getElementById('categoryChart'),{
        type:'doughnut',
        data:{labels:catLabels.map(l=>l.charAt(0).toUpperCase()+l.slice(1)),datasets:[{data:catData,backgroundColor:['#3b82f680','#f9731680','#10b98180','#8b5cf680','#94a3b880'],borderColor:['#3b82f6','#f97316','#10b981','#8b5cf6','#94a3b8'],borderWidth:2,hoverOffset:8}]},
        options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{backgroundColor:'#0f172a',padding:10,cornerRadius:8,callbacks:{label:i=>`  ${i.raw} patients`}}},cutout:'60%'}
    });
}

/* ── 6. Adherence Doughnut ── */
const adhLabels = <?php echo json_encode(array_column($adherenceRaw,'status')); ?>;
const adhData   = <?php echo json_encode(array_column($adherenceRaw,'total')); ?>;
if (adhData.length > 0) {
    new Chart(document.getElementById('adherenceChart'),{
        type:'doughnut',
        data:{labels:adhLabels.map(l=>l.charAt(0).toUpperCase()+l.slice(1)),datasets:[{data:adhData,backgroundColor:['rgba(16,185,129,0.7)','rgba(239,68,68,0.7)','rgba(249,115,22,0.7)','rgba(59,130,246,0.7)'],borderWidth:0,hoverOffset:6}]},
        options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{padding:12,font:{size:11},usePointStyle:true,pointStyleWidth:8}},tooltip:{backgroundColor:'#0f172a',padding:10,cornerRadius:8}},cutout:'65%'}
    });
}

/* ── Animate all bar fills ── */
document.addEventListener('DOMContentLoaded', () => {
    requestAnimationFrame(() => {
        document.querySelectorAll('[data-pct]').forEach(el => {
            el.style.width = el.dataset.pct + '%';
        });
        document.querySelectorAll('.load-bar-fill[data-pct]').forEach(el => {
            el.style.width = el.dataset.pct + '%';
        });
    });
});

/* ── Sidebar ── */
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('active');
    document.querySelector('.sidebar-overlay').classList.toggle('active');
}
document.addEventListener('click', function(e) {
    const sidebar = document.querySelector('.sidebar');
    const toggle  = document.querySelector('.sidebar-toggle');
    if (window.innerWidth <= 768 && sidebar && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
        sidebar.classList.remove('active');
        document.querySelector('.sidebar-overlay').classList.remove('active');
    }
});
</script>
</body>
</html>