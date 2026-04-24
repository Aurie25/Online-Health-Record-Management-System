<?php
session_start();
require_once "../db.php";

/* --------------------
   AUTHENTICATION
--------------------- */
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "patient") {
    header("Location: login.php");
    exit();
}

$patientId = intval($_SESSION["user_id"]);

/* --------------------
   FETCH PATIENT
--------------------- */
$stmt = $conn->prepare("
    SELECT id, first_name, last_name, date_of_birth, gender
    FROM users
    WHERE id = :pid AND role = 'patient'
");
$stmt->execute(["pid" => $patientId]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$_SESSION['first_name'] = $patient['first_name'];

$dob      = new DateTime($patient["date_of_birth"]);
$today    = new DateTime();
$age      = $today->diff($dob)->y;
$fullName = htmlspecialchars($patient["first_name"] . " " . $patient["last_name"]);
$initials = strtoupper(substr($patient["first_name"], 0, 1) . substr($patient["last_name"], 0, 1));

/* --------------------
   FETCH MEDICAL HISTORY
--------------------- */
$stmt = $conn->prepare("
    SELECT
        m.id,
        m.created_at  AS visit_date,
        m.symptoms,
        m.diagnosis,
        m.treatment,
        m.notes,
        u.first_name  AS doctor_first,
        u.last_name   AS doctor_last
    FROM medical_records m
    JOIN users u ON m.doctor_id = u.id
    WHERE m.patient_id = :pid
    ORDER BY m.created_at DESC
");
$stmt->execute(["pid" => $patientId]);
$history     = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalRecord = count($history);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical History — ApexCare</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../static/patient_sidebar.css">
    <link rel="stylesheet" href="../static/pmedicalhistory.css">
</head>
<body>

<div class="layout">
    <?php include "../static/includes/patient_sidebar.php"; ?>

    <main class="content">

        <!-- ── Page Header ─────────────────────────────── -->
        <div class="page-header">
            <div class="page-header-left">
                <h1>
                    <span class="header-icon"><i class="fas fa-notes-medical"></i></span>
                    Medical History
                </h1>
                <p class="page-header-sub">A full record of your past visits and treatments at ApexCare</p>
            </div>
            <div class="header-date">
                <i class="fas fa-calendar-day"></i>
                <?php echo date('d M Y'); ?>
            </div>
        </div>

        <!-- ── Patient Summary ─────────────────────────── -->
        <div class="patient-summary">
            <div class="summary-banner"></div>
            <div class="summary-body">
                <div class="summary-avatar-row">
                    <div class="summary-avatar"><?php echo $initials; ?></div>
                    <div class="record-count-badge">
                        <i class="fas fa-folder-open"></i>
                        <?php echo $totalRecord; ?> record<?php echo $totalRecord !== 1 ? 's' : ''; ?>
                    </div>
                </div>
                <div class="summary-name"><?php echo $fullName; ?></div>
                <div class="summary-chips">
                    <span class="s-chip">
                        <i class="fas fa-id-badge"></i>
                        P<?php echo str_pad($patientId, 4, '0', STR_PAD_LEFT); ?>
                    </span>
                    <span class="s-chip">
                        <i class="fas fa-cake-candles"></i>
                        <?php echo $age; ?> years old
                    </span>
                    <span class="s-chip">
                        <i class="fas fa-venus-mars"></i>
                        <?php echo ucfirst(htmlspecialchars($patient["gender"])); ?>
                    </span>
                    <span class="s-chip">
                        <i class="fas fa-calendar"></i>
                        DOB: <?php echo $dob->format('d M Y'); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- ── Filter Bar ──────────────────────────────── -->
        <div class="filter-bar">
            <span class="filter-label"><i class="fas fa-sliders"></i> Filter</span>
            <input
                type="text"
                class="filter-input"
                id="searchDoctor"
                placeholder="Search by doctor name…"
            >
            <input
                type="date"
                class="filter-input"
                id="filterDate"
            >
            <button class="btn-filter" id="filterBtn">
                <i class="fas fa-magnifying-glass"></i>
                Apply
            </button>
            <button class="btn-clear" id="clearBtn">
                <i class="fas fa-xmark"></i>
                Clear
            </button>
            <span class="filter-result-count" id="resultCount">
                Showing <span id="visibleCount"><?php echo $totalRecord; ?></span> of <?php echo $totalRecord; ?> records
            </span>
        </div>

        <!-- ── Timeline ────────────────────────────────── -->
        <?php if ($totalRecord > 0): ?>

            <div class="section-heading">
                <div class="section-heading-icon"><i class="fas fa-timeline"></i></div>
                <h2>Visit Timeline</h2>
            </div>

            <div class="timeline" id="historyContainer">

                <?php foreach ($history as $i => $record):
                    $doctorName   = htmlspecialchars("Dr. " . $record["doctor_first"] . " " . $record["doctor_last"]);
                    $visitDate    = strtotime($record["visit_date"]);
                    $dayNum       = date("d", $visitDate);
                    $monthAbbr    = date("M", $visitDate);
                    $yearNum      = date("Y", $visitDate);
                    $diagnosisStr = $record["diagnosis"] ? htmlspecialchars($record["diagnosis"]) : null;
                    $cardId       = "card-" . $record["id"];
                ?>
                <div class="history-card"
                     id="<?php echo $cardId; ?>"
                     data-doctor="<?php echo strtolower($record["doctor_first"] . " " . $record["doctor_last"]); ?>"
                     data-date="<?php echo date('Y-m-d', $visitDate); ?>">

                    <!-- Record header -->
                    <div class="record-header">
                        <div class="record-header-left">
                            <!-- Date block -->
                            <div class="record-date-badge">
                                <div class="record-date-day"><?php echo $dayNum; ?></div>
                                <div class="record-date-month"><?php echo $monthAbbr; ?></div>
                                <div class="record-date-year"><?php echo $yearNum; ?></div>
                            </div>
                            <div class="record-header-info">
                                <div class="record-title">
                                    <?php echo $diagnosisStr ? $diagnosisStr : 'Medical Visit'; ?>
                                </div>
                                <div class="record-doctor">
                                    <i class="fas fa-user-doctor"></i>
                                    <?php echo $doctorName; ?>
                                </div>
                            </div>
                        </div>
                        <button class="expand-btn" onclick="toggleCard('<?php echo $cardId; ?>')" aria-label="Expand record">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>

                    <!-- Collapsible body -->
                    <div class="record-body" id="body-<?php echo $cardId; ?>">

                        <?php if (!empty($record["symptoms"])): ?>
                        <div class="record-field">
                            <div class="field-icon notes"><i class="fas fa-stethoscope"></i></div>
                            <div>
                                <div class="field-label">Symptoms</div>
                                <div class="field-value"><?php echo htmlspecialchars($record["symptoms"]); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="record-field">
                            <div class="field-icon diag"><i class="fas fa-microscope"></i></div>
                            <div>
                                <div class="field-label">Diagnosis</div>
                                <div class="field-value <?php echo empty($record["diagnosis"]) ? 'empty' : ''; ?>">
                                    <?php echo !empty($record["diagnosis"]) ? htmlspecialchars($record["diagnosis"]) : 'Not recorded'; ?>
                                </div>
                            </div>
                        </div>

                        <div class="record-field">
                            <div class="field-icon treat"><i class="fas fa-pills"></i></div>
                            <div>
                                <div class="field-label">Treatment</div>
                                <div class="field-value <?php echo empty($record["treatment"]) ? 'empty' : ''; ?>">
                                    <?php echo !empty($record["treatment"]) ? htmlspecialchars($record["treatment"]) : 'Not recorded'; ?>
                                </div>
                            </div>
                        </div>

                        <div class="record-field">
                            <div class="field-icon notes"><i class="fas fa-clipboard"></i></div>
                            <div>
                                <div class="field-label">Doctor's Notes</div>
                                <div class="field-value <?php echo empty($record["notes"]) ? 'empty' : ''; ?>">
                                    <?php echo !empty($record["notes"]) ? htmlspecialchars($record["notes"]) : 'No notes recorded'; ?>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
                <?php endforeach; ?>

                <!-- No filter results message -->
                <div class="no-filter-result" id="noFilterResult">
                    <div class="empty-icon"><i class="fas fa-magnifying-glass"></i></div>
                    <h3 style="font-size:16px;font-weight:700;color:var(--text-primary);">No matching records</h3>
                    <p style="font-size:13px;color:var(--text-muted);">Try a different doctor name or date.</p>
                </div>

            </div>

        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="fas fa-notes-medical"></i></div>
                <h3>No medical records yet</h3>
                <p>Your visit records will appear here after your first appointment at ApexCare.</p>
            </div>
        <?php endif; ?>

    </main>
</div>

<footer>
    &copy; <?php echo date('Y'); ?> ApexCare &nbsp;·&nbsp; Patient Portal
</footer>

<script>
/* ── Toggle card expand/collapse ── */
function toggleCard(cardId) {
    const body = document.getElementById('body-' + cardId);
    const btn  = document.querySelector('#' + cardId + ' .expand-btn');
    body.classList.toggle('open');
    btn.classList.toggle('open');
}

/* ── Auto-open first card ── */
const firstCard = document.querySelector('.history-card');
if (firstCard) {
    const firstId = firstCard.id;
    document.getElementById('body-' + firstId)?.classList.add('open');
    firstCard.querySelector('.expand-btn')?.classList.add('open');
}

/* ── Filter logic ── */
const cards       = document.querySelectorAll('.history-card');
const totalCount  = cards.length;

function applyFilter() {
    const searchVal = document.getElementById('searchDoctor').value.toLowerCase().trim();
    const dateVal   = document.getElementById('filterDate').value;
    let visible     = 0;

    cards.forEach(card => {
        const matchDoctor = !searchVal || card.dataset.doctor.includes(searchVal);
        const matchDate   = !dateVal   || card.dataset.date === dateVal;
        const show        = matchDoctor && matchDate;
        card.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    document.getElementById('visibleCount').textContent = visible;

    const noResult = document.getElementById('noFilterResult');
    if (noResult) noResult.classList.toggle('show', visible === 0);
}

function clearFilter() {
    document.getElementById('searchDoctor').value = '';
    document.getElementById('filterDate').value   = '';
    cards.forEach(c => c.style.display = '');
    document.getElementById('visibleCount').textContent = totalCount;
    const noResult = document.getElementById('noFilterResult');
    if (noResult) noResult.classList.remove('show');
}

document.getElementById('filterBtn')?.addEventListener('click', applyFilter);
document.getElementById('clearBtn')?.addEventListener('click', clearFilter);

/* Live search on type */
document.getElementById('searchDoctor')?.addEventListener('input', applyFilter);
document.getElementById('filterDate')?.addEventListener('input', applyFilter);
</script>

</body>
</html>