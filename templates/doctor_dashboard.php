<?php
session_start();
require '../db.php';

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "doctor") {
    header("Location: login.php");
    exit();
}

$doctorId   = $_SESSION["user_id"];
$firstName  = htmlspecialchars($_SESSION["first_name"] ?? 'Doctor');
$lastName   = htmlspecialchars($_SESSION["last_name"]  ?? '');

/* ── STATS ───────────────────────────────────────── */

// Total unique patients
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT patient_id)
    FROM appointments
    WHERE doctor_id = ?
");
$stmt->execute([$doctorId]);
$totalPatients = (int) $stmt->fetchColumn();

// Today's appointments
$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM appointments a
    JOIN doctor_schedule ds ON a.schedule_id = ds.id
    WHERE a.doctor_id = ?
    AND ds.available_date = CURDATE()
    AND a.status = 'assigned'
");
$stmt->execute([$doctorId]);
$todayCount = (int) $stmt->fetchColumn();

// Total completed appointments
$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM appointments
    WHERE doctor_id = ? AND status = 'completed'
");
$stmt->execute([$doctorId]);
$completedCount = (int) $stmt->fetchColumn();

// Upcoming appointments (total assigned, future dates)
$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM appointments a
    JOIN doctor_schedule ds ON a.schedule_id = ds.id
    WHERE a.doctor_id = ?
    AND ds.available_date >= CURDATE()
    AND a.status = 'assigned'
");
$stmt->execute([$doctorId]);
$upcomingCount = (int) $stmt->fetchColumn();

/* ── NEXT APPOINTMENT ────────────────────────────── */
$stmt = $conn->prepare("
    SELECT u.first_name, u.last_name, ds.available_date, ds.slot_time
    FROM appointments a
    JOIN doctor_schedule ds ON a.schedule_id = ds.id
    JOIN users u ON a.patient_id = u.id
    WHERE a.doctor_id = ?
    AND ds.available_date >= CURDATE()
    AND a.status = 'assigned'
    ORDER BY ds.available_date ASC, ds.slot_time ASC
    LIMIT 1
");
$stmt->execute([$doctorId]);
$nextAppt = $stmt->fetch(PDO::FETCH_ASSOC);

/* ── TODAY'S SCHEDULE LIST ───────────────────────── */
$stmt = $conn->prepare("
    SELECT u.first_name, u.last_name, ds.slot_time, a.id AS appt_id
    FROM appointments a
    JOIN doctor_schedule ds ON a.schedule_id = ds.id
    JOIN users u ON a.patient_id = u.id
    WHERE a.doctor_id = ?
    AND ds.available_date = CURDATE()
    AND a.status = 'assigned'
    ORDER BY ds.slot_time ASC
");
$stmt->execute([$doctorId]);
$todayList = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ── TIME GREETING ───────────────────────────────── */
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$wave     = $hour < 12 ? '🌅' : ($hour < 17 ? '☀️' : '🌙');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — ApexCare Doctor</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../static/doctor_sidebar.css">
    <link rel="stylesheet" href="../static/doctor.css">
    <link rel="stylesheet" href="../static/doctor_dashboard.css">
</head>
<body>

<div class="layout">
    <?php include "../static/includes/doctor_sidebar.php"; ?>

    <main class="content">

        <!-- ── Welcome Hero ────────────────────────────── -->
        <div class="welcome-hero">
            <div class="hero-left">
                <div class="hero-greeting">
                    <span><?php echo $greeting ?>, Dr. <?php echo $firstName; ?></span>
                    <span class="wave"><?php echo $wave; ?></span>
                </div>
                <div class="hero-sub">
                    <span class="hero-chip today">
                        <i class="fas fa-calendar-day"></i>
                        <?php echo date('l, d M Y'); ?>
                    </span>
                    <span class="hero-chip">
                        <i class="fas fa-calendar-check"></i>
                        <?php echo $todayCount; ?> appointment<?php echo $todayCount !== 1 ? 's' : ''; ?> today
                    </span>
                </div>
            </div>
            <div class="hero-right">
                <div class="hero-date">
                    <i class="fas fa-clock"></i>
                    <span id="liveClock"><?php echo date('h:i A'); ?></span>
                </div>
            </div>
        </div>

        <!-- ── Stats Grid ───────────────────────────────── -->
        <div class="stats-grid">

            <div class="stat-card sapphire">
                <div class="stat-icon sapphire"><i class="fas fa-users"></i></div>
                <div>
                    <div class="stat-value"><?php echo $totalPatients; ?></div>
                    <div class="stat-label">Total Patients</div>
                </div>
            </div>

            <div class="stat-card cyan">
                <div class="stat-icon cyan"><i class="fas fa-calendar-check"></i></div>
                <div>
                    <div class="stat-value"><?php echo $todayCount; ?></div>
                    <div class="stat-label">Today's Appts</div>
                </div>
            </div>

            <div class="stat-card emerald">
                <div class="stat-icon emerald"><i class="fas fa-circle-check"></i></div>
                <div>
                    <div class="stat-value"><?php echo $completedCount; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>

            <div class="stat-card amber">
                <div class="stat-icon amber"><i class="fas fa-hourglass-half"></i></div>
                <div>
                    <div class="stat-value"><?php echo $upcomingCount; ?></div>
                    <div class="stat-label">Upcoming</div>
                </div>
            </div>

        </div>

        <!-- ── Main Dashboard Grid ─────────────────────── -->
        <div class="dashboard-grid">

            <!-- ── Left: Today's Schedule ─────────────── -->
            <div class="d-card">
                <div class="d-card-head">
                    <div class="d-card-head-left">
                        <span class="d-card-icon cyan"><i class="fas fa-list-check"></i></span>
                        <div>
                            <div class="d-card-title">Today's Schedule</div>
                            <div class="d-card-sub"><?php echo date('d M Y'); ?> · <?php echo $todayCount; ?> appointment<?php echo $todayCount !== 1 ? 's' : ''; ?></div>
                        </div>
                    </div>
                    <a href="doctor_appointments.php" class="btn-d-secondary" style="font-size:12.5px;padding:7px 14px;">
                        <i class="fas fa-arrow-right"></i>
                        View all
                    </a>
                </div>

                <?php if (count($todayList) > 0): ?>
                <table class="schedule-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Patient</th>
                            <th>Time Slot</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($todayList as $i => $appt):
                            $initials = strtoupper(substr($appt['first_name'], 0, 1) . substr($appt['last_name'], 0, 1));
                        ?>
                        <tr>
                            <td><span class="slot-num"><?php echo str_pad($i + 1, 2, '0', STR_PAD_LEFT); ?></span></td>
                            <td>
                                <div class="patient-cell">
                                    <div class="patient-avatar"><?php echo $initials; ?></div>
                                    <span class="patient-name">
                                        <?php echo htmlspecialchars($appt['first_name'] . ' ' . $appt['last_name']); ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <span class="time-chip">
                                    <i class="far fa-clock"></i>
                                    <?php echo date('h:i A', strtotime($appt['slot_time'])); ?>
                                </span>
                            </td>
                            <td><span class="status-pill assigned"><i class="fas fa-circle" style="font-size:7px;"></i> Assigned</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="table-footer">
                    <span class="table-footer-info">
                        <i class="fas fa-calendar-day"></i>
                        <?php echo $todayCount; ?> patient<?php echo $todayCount !== 1 ? 's' : ''; ?> scheduled today
                    </span>
                    <span class="table-footer-info">
                        <i class="fas fa-circle-check"></i>
                        healthrecord_db
                    </span>
                </div>

                <?php else: ?>
                <div class="d-empty">
                    <div class="d-empty-icon"><i class="fas fa-calendar-xmark"></i></div>
                    <h3>No appointments today</h3>
                    <p>Your schedule is clear. Enjoy the free time, Doctor.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- ── Right Panel ────────────────────────── -->
            <div class="next-appt-panel">

                <!-- Next appointment -->
                <div class="next-appt-card">
                    <div class="next-appt-head">
                        <div class="next-appt-head-label">
                            <i class="fas fa-forward-step"></i>
                            Next Appointment
                        </div>
                        <?php if ($nextAppt): ?>
                            <div class="next-appt-name">
                                <?php echo htmlspecialchars($nextAppt['first_name'] . ' ' . $nextAppt['last_name']); ?>
                            </div>
                            <div class="next-appt-time">
                                <i class="fas fa-calendar-day"></i>
                                <?php
                                    $date = new DateTime($nextAppt['available_date']);
                                    $now  = new DateTime('today');
                                    $diff = (int) $now->diff($date)->days;
                                    $dateLabel = $diff === 0
                                        ? 'Today'
                                        : ($diff === 1 ? 'Tomorrow' : $date->format('d M Y'));
                                    echo $dateLabel . ' · ' . date('h:i A', strtotime($nextAppt['slot_time']));
                                ?>
                            </div>
                        <?php else: ?>
                            <div class="next-appt-name" style="font-size:15px;opacity:0.7;">No upcoming appointments</div>
                        <?php endif; ?>
                    </div>

                    <?php if ($nextAppt): ?>
                    <div class="next-appt-body">
                        <div style="display:flex;flex-direction:column;gap:8px;">
                            <div style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text-secondary);">
                                <i class="fas fa-user" style="color:var(--cyan-dark);width:14px;text-align:center;"></i>
                                Patient: <strong style="color:var(--text-primary);"><?php echo htmlspecialchars($nextAppt['first_name'] . ' ' . $nextAppt['last_name']); ?></strong>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text-secondary);">
                                <i class="fas fa-calendar" style="color:var(--cyan-dark);width:14px;text-align:center;"></i>
                                Date: <strong style="color:var(--text-primary);"><?php echo date('d M Y', strtotime($nextAppt['available_date'])); ?></strong>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text-secondary);">
                                <i class="fas fa-clock" style="color:var(--cyan-dark);width:14px;text-align:center;"></i>
                                Time: <strong style="color:var(--text-primary);"><?php echo date('h:i A', strtotime($nextAppt['slot_time'])); ?></strong>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="next-appt-empty">
                        <i class="fas fa-calendar-check"></i>
                        <span>Schedule is clear</span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Quick links -->
                <div class="quick-links-card">
                    <div class="quick-links-head">
                        <i class="fas fa-bolt"></i>
                        Quick Access
                    </div>
                    <div class="quick-links-body">
                        <a href="doctor_appointments.php" class="quick-link">
                            <div class="quick-link-icon"><i class="fas fa-calendar-days"></i></div>
                            All Appointments
                        </a>
                        <a href="doctor_schedule.php" class="quick-link">
                            <div class="quick-link-icon"><i class="fas fa-clock"></i></div>
                            Manage Schedule
                        </a>
                        <a href="dpatientrecords.php" class="quick-link">
                            <div class="quick-link-icon"><i class="fas fa-notes-medical"></i></div>
                            Patient Records
                        </a>
                        <a href="dsettings.php" class="quick-link">
                            <div class="quick-link-icon"><i class="fas fa-gear"></i></div>
                            Settings
                        </a>
                    </div>
                </div>

            </div>
        </div>

    </main>
</div>

<script>
    /* Live clock */
    function updateClock() {
        const now  = new Date();
        let h      = now.getHours();
        const m    = String(now.getMinutes()).padStart(2, '0');
        const ampm = h >= 12 ? 'PM' : 'AM';
        h          = h % 12 || 12;
        document.getElementById('liveClock').textContent = h + ':' + m + ' ' + ampm;
    }
    setInterval(updateClock, 1000);
    updateClock();
</script>

</body>
</html>