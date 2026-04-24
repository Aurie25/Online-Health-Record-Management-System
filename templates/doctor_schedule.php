<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: login.php");
    exit();
}

$doctorId = $_SESSION['user_id'];
$success  = '';
$slotCount = 0;
$errors   = [];

/* ── HANDLE FORM ─────────────────────────────────── */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $date     = $_POST['date']     ?? '';
    $start    = $_POST['start_time'] ?? '';
    $end      = $_POST['end_time']   ?? '';
    $interval = intval($_POST['interval'] ?? 30);

    /* Validate */
    if (empty($date))  $errors[] = 'Please select a date.';
    if (empty($start)) $errors[] = 'Please enter a start time.';
    if (empty($end))   $errors[] = 'Please enter an end time.';

    if (empty($errors)) {
        $startTs = strtotime($date . ' ' . $start);
        $endTs   = strtotime($date . ' ' . $end);

        if ($startTs >= $endTs) {
            $errors[] = 'End time must be after start time.';
        }

        /* Check for duplicates on this date for this doctor */
        if (empty($errors)) {
            $existCheck = $conn->prepare("
                SELECT COUNT(*) FROM doctor_schedule
                WHERE doctor_id = :doc AND available_date = :date
            ");
            $existCheck->execute([':doc' => $doctorId, ':date' => $date]);
            if ($existCheck->fetchColumn() > 0) {
                $errors[] = 'You already have a schedule for this date. Please choose a different date.';
            }
        }
    }

    if (empty($errors)) {
        $ts = strtotime($date . ' ' . $start);
        $endTs = strtotime($date . ' ' . $end);

        $stmt = $conn->prepare("
            INSERT INTO doctor_schedule (doctor_id, available_date, slot_time)
            VALUES (:doc, :date, :time)
        ");

        while ($ts < $endTs) {
            $stmt->execute([
                ':doc'  => $doctorId,
                ':date' => $date,
                ':time' => date('H:i:s', $ts),
            ]);
            $slotCount++;
            $ts = strtotime("+{$interval} minutes", $ts);
        }

        $dateFormatted = date('l, d M Y', strtotime($date));
        $success = "Schedule created! <strong>{$slotCount} slot" . ($slotCount !== 1 ? 's' : '') . "</strong> added for <strong>{$dateFormatted}</strong>.";
        header("Location: doctor_schedule.php?done=" . urlencode($success));
        exit();
    }
}

$doneMsg = isset($_GET['done']) ? htmlspecialchars(urldecode($_GET['done'])) : '';

/* ── TODAY MIN FOR DATE INPUT ────────────────────── */
$minDate = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Schedule — ApexCare</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../static/doctor_schedule.css">
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
                    <span class="header-icon"><i class="fas fa-calendar-plus"></i></span>
                    Create Daily Schedule
                </h1>
                <p class="page-header-sub">Set your available time slots for patient appointments</p>
            </div>
            <div class="header-badge">
                <i class="fas fa-calendar-day"></i>
                <?php echo date('d M Y'); ?>
            </div>
        </div>

        <!-- ── Success Toast ───────────────────────────── -->
        <?php if ($doneMsg): ?>
            <div class="success-toast" id="successToast">
                <i class="fas fa-circle-check"></i>
                <span><?php echo $doneMsg; /* already escaped via urldecode + htmlspecialchars */ ?></span>
            </div>
        <?php endif; ?>

        <!-- ── Error Alert ─────────────────────────────── -->
        <?php if (!empty($errors)): ?>
            <div class="success-toast" style="background:#fef2f2;border-color:rgba(220,38,38,0.2);color:#991b1b;margin-bottom:24px;" id="errorToast">
                <i class="fas fa-circle-exclamation" style="color:#dc2626;"></i>
                <div>
                    <?php foreach ($errors as $err): ?>
                        <div><?php echo htmlspecialchars($err); ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ── Main Grid ────────────────────────────────── -->
        <div class="schedule-grid">

            <!-- ── LEFT: Form ─────────────────────────── -->
            <div class="form-card">
                <div class="form-card-head">
                    <span class="form-card-icon"><i class="fas fa-clock"></i></span>
                    <div>
                        <div class="form-card-title">Schedule Builder</div>
                        <div class="form-card-sub">Slots are auto-generated at your chosen interval</div>
                    </div>
                </div>

                <div class="form-card-body">
                    <form method="POST" id="scheduleForm">

                        <!-- Date -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-calendar-day"></i>
                                Date
                            </label>
                            <input
                                type="date"
                                name="date"
                                id="dateInput"
                                class="form-input"
                                min="<?php echo $minDate; ?>"
                                value="<?php echo isset($_POST['date']) ? htmlspecialchars($_POST['date']) : ''; ?>"
                                required
                                oninput="updatePreview()"
                            >
                        </div>

                        <!-- Time range -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-hourglass"></i>
                                Time Range
                            </label>
                            <div class="time-row">
                                <div class="form-group" style="margin-bottom:0;">
                                    <label class="form-label" style="font-size:10.5px;letter-spacing:0.4px;color:var(--text-muted);">
                                        <i class="fas fa-hourglass-start"></i> Start
                                    </label>
                                    <input
                                        type="time"
                                        name="start_time"
                                        id="startInput"
                                        class="form-input"
                                        value="<?php echo isset($_POST['start_time']) ? htmlspecialchars($_POST['start_time']) : '08:00'; ?>"
                                        required
                                        oninput="updatePreview()"
                                    >
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label class="form-label" style="font-size:10.5px;letter-spacing:0.4px;color:var(--text-muted);">
                                        <i class="fas fa-hourglass-end"></i> End
                                    </label>
                                    <input
                                        type="time"
                                        name="end_time"
                                        id="endInput"
                                        class="form-input"
                                        value="<?php echo isset($_POST['end_time']) ? htmlspecialchars($_POST['end_time']) : '17:00'; ?>"
                                        required
                                        oninput="updatePreview()"
                                    >
                                </div>
                            </div>
                        </div>

                        <!-- Slot Interval -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-stopwatch"></i>
                                Slot Interval
                            </label>
                            <div class="interval-picker" id="intervalPicker">
                                <?php
                                $intervals = [
                                    15 => '15 min',
                                    20 => '20 min',
                                    30 => '30 min',
                                    45 => '45 min',
                                    60 => '1 hour',
                                    90 => '1.5 hr',
                                ];
                                $selectedInterval = intval($_POST['interval'] ?? 30);
                                foreach ($intervals as $mins => $label):
                                    $isSelected = $mins === $selectedInterval;
                                    [$num, $unit] = explode(' ', $label);
                                ?>
                                <label class="interval-btn <?php echo $isSelected ? 'selected' : ''; ?>"
                                       onclick="selectInterval(<?php echo $mins; ?>, this)">
                                    <input type="radio" name="interval"
                                           value="<?php echo $mins; ?>"
                                           <?php echo $isSelected ? 'checked' : ''; ?>>
                                    <span class="interval-check"><i class="fas fa-check"></i></span>
                                    <span class="interval-mins"><?php echo $num; ?></span>
                                    <span class="interval-label"><?php echo $unit; ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <!-- Hidden actual interval value updated by JS -->
                            <input type="hidden" name="interval" id="intervalHidden" value="<?php echo $selectedInterval; ?>">
                        </div>

                        <!-- Live Slot Preview -->
                        <div class="slot-preview" id="slotPreview">
                            <div class="slot-preview-head">
                                <span class="slot-preview-label">
                                    <i class="fas fa-eye"></i>
                                    Slots Preview
                                </span>
                                <span class="slot-preview-count" id="slotCountBadge">0 slots</span>
                            </div>
                            <div class="slot-chips" id="slotChips">
                                <span class="slot-preview-empty">
                                    <i class="fas fa-circle-info"></i>
                                    Set a time range to preview slots
                                </span>
                            </div>
                        </div>

                        <!-- Submit -->
                        <button type="submit" class="btn-submit" id="submitBtn">
                            <i class="fas fa-calendar-plus"></i>
                            Generate Slots
                        </button>

                    </form>
                </div>
            </div>

            <!-- ── RIGHT: Info panels ──────────────────── -->
            <div>

                <!-- How it works -->
                <div class="info-card">
                    <div class="info-card-head">
                        <span class="info-card-icon teal"><i class="fas fa-circle-info"></i></span>
                        <span class="info-card-title">How It Works</span>
                    </div>
                    <div class="info-card-body">
                        <div class="how-steps">
                            <div class="how-step">
                                <div class="step-num">1</div>
                                <div class="step-text">
                                    <strong>Pick a date</strong> — choose any future date to open your availability.
                                </div>
                            </div>
                            <div class="how-step">
                                <div class="step-num">2</div>
                                <div class="step-text">
                                    <strong>Set a time range</strong> — e.g. 08:00 to 17:00 for a full working day.
                                </div>
                            </div>
                            <div class="how-step">
                                <div class="step-num">3</div>
                                <div class="step-text">
                                    <strong>Choose an interval</strong> — slots are auto-generated every 15, 30, 60 minutes etc.
                                </div>
                            </div>
                            <div class="how-step">
                                <div class="step-num">4</div>
                                <div class="step-text">
                                    <strong>Patients can book</strong> — your slots appear on their appointment page immediately.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tips -->
                <div class="info-card">
                    <div class="info-card-head">
                        <span class="info-card-icon amber"><i class="fas fa-lightbulb"></i></span>
                        <span class="info-card-title">Tips</span>
                    </div>
                    <div class="info-card-body">
                        <div class="tips-list">
                            <div class="tip-item">
                                <i class="fas fa-triangle-exclamation"></i>
                                You can only create <strong>one schedule per date</strong>. The system will warn you if a date already has slots.
                            </div>
                            <div class="tip-item">
                                <i class="fas fa-lightbulb"></i>
                                <strong>30-minute slots</strong> are recommended for general consultations; 15 min for follow-ups.
                            </div>
                            <div class="tip-item">
                                <i class="fas fa-calendar-check"></i>
                                Create schedules a few days in advance so patients have time to book.
                            </div>
                            <div class="tip-item">
                                <i class="fas fa-clock"></i>
                                A 08:00–17:00 range with 30-min slots generates <strong>18 bookable slots</strong>.
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </div>

    </main>
</div>

<script>
    let selectedInterval = <?php echo $selectedInterval; ?>;

    /* ── Interval selector ── */
    function selectInterval(mins, label) {
        document.querySelectorAll('.interval-btn').forEach(b => b.classList.remove('selected'));
        label.classList.add('selected');
        label.querySelector('input[type="radio"]').checked = true;
        document.getElementById('intervalHidden').value = mins;
        selectedInterval = mins;
        updatePreview();
    }

    /* ── Live slot preview ── */
    function updatePreview() {
        const startVal = document.getElementById('startInput').value;
        const endVal   = document.getElementById('endInput').value;
        const chips    = document.getElementById('slotChips');
        const badge    = document.getElementById('slotCountBadge');

        if (!startVal || !endVal) {
            chips.innerHTML = '<span class="slot-preview-empty"><i class="fas fa-circle-info"></i> Set a time range to preview slots</span>';
            badge.textContent = '0 slots';
            return;
        }

        const [sh, sm] = startVal.split(':').map(Number);
        const [eh, em] = endVal.split(':').map(Number);
        const startMins = sh * 60 + sm;
        const endMins   = eh * 60 + em;

        if (startMins >= endMins) {
            chips.innerHTML = '<span class="slot-preview-empty" style="color:#dc2626;"><i class="fas fa-circle-xmark"></i> End time must be after start time</span>';
            badge.textContent = '0 slots';
            return;
        }

        const slots = [];
        let cur = startMins;
        while (cur < endMins) {
            const h = Math.floor(cur / 60);
            const m = cur % 60;
            slots.push((h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m);
            cur += selectedInterval;
        }

        badge.textContent = slots.length + ' slot' + (slots.length !== 1 ? 's' : '');

        if (slots.length === 0) {
            chips.innerHTML = '<span class="slot-preview-empty"><i class="fas fa-circle-info"></i> No slots in this range</span>';
            return;
        }

        /* Show max 12 chips + overflow */
        const maxShow = 12;
        const shown   = slots.slice(0, maxShow);
        let html = shown.map(t => `
            <span class="slot-chip">
                <i class="fas fa-clock"></i>${t}
            </span>
        `).join('');

        if (slots.length > maxShow) {
            html += `<span class="slot-chip" style="background:var(--teal-pale);border-color:rgba(13,148,136,0.2);color:var(--teal-dark);">
                <i class="fas fa-plus"></i>${slots.length - maxShow} more
            </span>`;
        }

        chips.innerHTML = html;
    }

    /* ── Submit loading state ── */
    document.getElementById('scheduleForm').addEventListener('submit', function () {
        const btn = document.getElementById('submitBtn');
        btn.classList.add('loading');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Slots…';
    });

    /* ── Auto-dismiss toast ── */
    const toast = document.getElementById('successToast');
    if (toast) {
        setTimeout(() => {
            toast.style.transition = 'opacity 0.5s ease';
            toast.style.opacity    = '0';
            setTimeout(() => toast.remove(), 500);
        }, 6000);
    }

    /* ── Init preview on page load ── */
    updatePreview();
</script>

</body>
</html>