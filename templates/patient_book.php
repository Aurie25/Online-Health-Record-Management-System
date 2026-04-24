<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit();
}

$patientId = $_SESSION['user_id'];
$success   = "";
$isError   = false;

/* -------------------------
   HANDLE BOOKING
--------------------------*/
if (isset($_POST["schedule_id"])) {

    $scheduleId = (int) $_POST["schedule_id"];

    $conn->beginTransaction();

    $slot = $conn->prepare("
        SELECT * FROM doctor_schedule
        WHERE id = :id AND is_booked = FALSE
        FOR UPDATE
    ");
    $slot->execute(["id" => $scheduleId]);
    $data = $slot->fetch(PDO::FETCH_ASSOC);

    if ($data) {
        $insert = $conn->prepare("
            INSERT INTO appointments (patient_id, doctor_id, schedule_id)
            VALUES (:p, :d, :s)
        ");
        $insert->execute([
            "p" => $patientId,
            "d" => $data["doctor_id"],
            "s" => $scheduleId
        ]);

        $update = $conn->prepare("
            UPDATE doctor_schedule SET is_booked = TRUE WHERE id = :id
        ");
        $update->execute(["id" => $scheduleId]);

        $conn->commit();
        $success = "Appointment booked successfully! You'll find it in your medical history.";
    } else {
        $conn->rollBack();
        $success = "This slot was just taken by another patient. Please choose another.";
        $isError = true;
    }
}

/* -------------------------
   FETCH AVAILABLE SLOTS
--------------------------*/
$slots = $conn->query("
    SELECT ds.*, u.first_name, u.last_name
    FROM doctor_schedule ds
    JOIN users u ON ds.doctor_id = u.id
    WHERE ds.is_booked = FALSE
    AND ds.available_date >= CURDATE()
    ORDER BY ds.available_date ASC, ds.slot_time ASC
")->fetchAll(PDO::FETCH_ASSOC);

$slotCount = count($slots);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment — ApexCare</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../static/patient_sidebar.css">
    <link rel="stylesheet" href="../static/patient_book.css">
</head>
<body>

<div class="layout">
    <?php include "../static/includes/patient_sidebar.php"; ?>

    <main class="content">

        <!-- ── Page Header ─────────────────────────────── -->
        <div class="page-header">
            <div class="page-header-left">
                <h1>
                    <span class="header-icon"><i class="fas fa-calendar-plus"></i></span>
                    Book an Appointment
                </h1>
                <p class="page-header-sub">Choose an available time slot with one of our doctors</p>
            </div>
            <div class="header-date">
                <i class="fas fa-calendar-day"></i>
                <?php echo date('d M Y'); ?>
            </div>
        </div>

        <!-- ── Booking Alert ───────────────────────────── -->
        <?php if (!empty($success)): ?>
            <div class="booking-alert <?php echo $isError ? 'taken' : 'success'; ?>">
                <i class="fas fa-<?php echo $isError ? 'circle-exclamation' : 'circle-check'; ?>"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- ── Filter Bar ──────────────────────────────── -->
        <div class="filter-bar">
            <span class="filter-label"><i class="fas fa-sliders"></i> Filter</span>

            <input
                type="text"
                class="filter-input"
                id="searchDoctor"
                placeholder="Search doctor name…"
                oninput="filterSlots()"
            >

            <input
                type="date"
                class="filter-input"
                id="filterDate"
                min="<?php echo date('Y-m-d'); ?>"
                oninput="filterSlots()"
            >

            <select class="filter-select" id="filterTime" onchange="filterSlots()">
                <option value="">All times</option>
                <option value="morning">Morning (before 12:00)</option>
                <option value="afternoon">Afternoon (12:00–17:00)</option>
                <option value="evening">Evening (after 17:00)</option>
            </select>

            <span class="filter-count" id="filterCount">
                Showing <span id="visibleCount"><?php echo $slotCount; ?></span> of <?php echo $slotCount; ?> slots
            </span>
        </div>

        <!-- ── Slots Grid ──────────────────────────────── -->
        <div class="slots-section">
            <?php if ($slotCount > 0): ?>

                <div class="slots-grid" id="slotsGrid">
                    <?php foreach ($slots as $slot):
                        $doctorInitial = strtoupper(substr($slot['first_name'], 0, 1));
                        $doctorName    = htmlspecialchars('Dr. ' . $slot['first_name'] . ' ' . $slot['last_name']);
                        $dateFormatted = date('D, d M Y', strtotime($slot['available_date']));
                        $timeFormatted = date('h:i A', strtotime($slot['slot_time']));
                        $rawDate       = $slot['available_date'];
                        $rawTime       = $slot['slot_time'];

                        /* Is this today? */
                        $isToday = ($slot['available_date'] === date('Y-m-d'));
                    ?>
                    <div class="slot-card"
                         data-doctor="<?php echo strtolower($slot['first_name'] . ' ' . $slot['last_name']); ?>"
                         data-date="<?php echo $rawDate; ?>"
                         data-time="<?php echo $rawTime; ?>">

                        <!-- Card header -->
                        <div class="slot-card-head">
                            <div class="doctor-avatar"><?php echo $doctorInitial; ?></div>
                            <div>
                                <div class="doctor-name"><?php echo $doctorName; ?></div>
                                <div class="doctor-label">General Practitioner</div>
                            </div>
                            <?php if ($isToday): ?>
                                <span class="slot-badge" style="margin-left:auto;">
                                    <i class="fas fa-circle" style="color:var(--teal-light);"></i>
                                    Today
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Card body -->
                        <div class="slot-card-body">
                            <div class="slot-detail">
                                <div class="slot-detail-icon"><i class="fas fa-calendar"></i></div>
                                <div>
                                    <div class="slot-detail-label">Date</div>
                                    <div class="slot-detail-value"><?php echo $dateFormatted; ?></div>
                                </div>
                            </div>
                            <div class="slot-detail">
                                <div class="slot-detail-icon"><i class="fas fa-clock"></i></div>
                                <div>
                                    <div class="slot-detail-label">Time</div>
                                    <div class="slot-detail-value"><?php echo $timeFormatted; ?></div>
                                </div>
                                <span class="slot-badge">
                                    <i class="fas fa-circle"></i>
                                    Available
                                </span>
                            </div>
                        </div>

                        <!-- Book button -->
                        <div class="slot-card-foot">
                            <button
                                class="btn-book-slot"
                                onclick="openModal(
                                    '<?php echo addslashes($doctorName); ?>',
                                    '<?php echo addslashes($dateFormatted); ?>',
                                    '<?php echo addslashes($timeFormatted); ?>',
                                    '<?php echo $slot['id']; ?>'
                                )"
                            >
                                <i class="fas fa-calendar-check"></i>
                                Book This Slot
                            </button>
                        </div>

                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- No results after filter -->
                <div class="empty-state" id="noResults" style="display:none;">
                    <div class="empty-icon"><i class="fas fa-magnifying-glass"></i></div>
                    <h3>No matching slots</h3>
                    <p>Try adjusting your filters to see more available appointments.</p>
                </div>

            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-calendar-xmark"></i></div>
                    <h3>No slots available</h3>
                    <p>There are no available appointment slots right now. Please check back soon.</p>
                </div>
            <?php endif; ?>
        </div>

    </main>
</div>

<!-- ── Confirmation Modal ──────────────────────────── -->
<div class="modal-backdrop" id="modalBackdrop">
    <div class="modal">
        <div class="modal-icon"><i class="fas fa-calendar-check"></i></div>
        <h3>Confirm Appointment</h3>
        <p class="modal-desc">You're about to book the following slot:</p>

        <div class="modal-detail">
            <div class="modal-detail-row">
                <span>Doctor</span>
                <span id="modalDoctor">—</span>
            </div>
            <div class="modal-detail-row">
                <span>Date</span>
                <span id="modalDate">—</span>
            </div>
            <div class="modal-detail-row">
                <span>Time</span>
                <span id="modalTime">—</span>
            </div>
        </div>

        <form method="POST" id="bookingForm">
            <input type="hidden" name="schedule_id" id="modalScheduleId">
            <div class="modal-actions">
                <button type="button" class="btn-modal-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn-modal-confirm">
                    <i class="fas fa-check"></i>
                    Confirm Booking
                </button>
            </div>
        </form>
    </div>
</div>

<script>
/* ── Confirmation modal ── */
function openModal(doctor, date, time, scheduleId) {
    document.getElementById('modalDoctor').textContent    = doctor;
    document.getElementById('modalDate').textContent      = date;
    document.getElementById('modalTime').textContent      = time;
    document.getElementById('modalScheduleId').value      = scheduleId;
    document.getElementById('modalBackdrop').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('modalBackdrop').classList.remove('open');
    document.body.style.overflow = '';
}

/* Close on backdrop click */
document.getElementById('modalBackdrop').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

/* ── Client-side filtering ── */
function filterSlots() {
    const searchVal  = document.getElementById('searchDoctor').value.toLowerCase().trim();
    const dateVal    = document.getElementById('filterDate').value;
    const timeFilter = document.getElementById('filterTime').value;
    const cards      = document.querySelectorAll('.slot-card');
    let visible      = 0;

    cards.forEach(card => {
        const doctor    = card.dataset.doctor;
        const cardDate  = card.dataset.date;
        const cardTime  = card.dataset.time;
        const [h]       = cardTime.split(':').map(Number);

        const matchDoctor = !searchVal || doctor.includes(searchVal);
        const matchDate   = !dateVal   || cardDate === dateVal;

        let matchTime = true;
        if (timeFilter === 'morning')   matchTime = h < 12;
        if (timeFilter === 'afternoon') matchTime = h >= 12 && h < 17;
        if (timeFilter === 'evening')   matchTime = h >= 17;

        const show = matchDoctor && matchDate && matchTime;
        card.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    document.getElementById('visibleCount').textContent = visible;
    const noResults = document.getElementById('noResults');
    if (noResults) noResults.style.display = visible === 0 ? 'flex' : 'none';
}

/* ── ESC to close modal ── */
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeModal();
});
</script>

</body>
</html>