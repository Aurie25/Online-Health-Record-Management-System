<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: login.php");
    exit();
}

$doctorId = $_SESSION['user_id'];
$patient  = null;
$history  = [];
$error    = '';

/* ── SEARCH PATIENT ──────────────────────────────── */
if (isset($_GET['patient_id']) && $_GET['patient_id'] !== '') {
    $patientId = intval($_GET['patient_id']);

    $stmt = $conn->prepare("
        SELECT id, first_name, last_name, date_of_birth, gender
        FROM users
        WHERE id = :pid AND role = 'patient'
    ");
    $stmt->execute([':pid' => $patientId]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($patient) {
        $stmt = $conn->prepare("
            SELECT symptoms, diagnosis, treatment, notes, created_at
            FROM medical_records
            WHERE patient_id = :pid
            ORDER BY created_at DESC
        ");
        $stmt->execute([':pid' => $patientId]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $error = "No patient found with ID #{$patientId}. Please check the ID and try again.";
    }
}

/* ── SAVE RECORD ─────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = intval($_POST['patient_id']);
    $symptoms  = trim($_POST['symptoms']  ?? '');
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $treatment = trim($_POST['treatment'] ?? '');
    $notes     = trim($_POST['notes']     ?? '');

    if (empty($symptoms) || empty($diagnosis)) {
        $error = "Symptoms and diagnosis are required fields.";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE id = :pid AND role = 'patient'");
        $check->execute([':pid' => $patientId]);

        if (!$check->fetch()) {
            $error = "Invalid patient ID.";
        } else {
            /* medical_records.created_at has DEFAULT current_timestamp() — no need to pass it */
            $stmt = $conn->prepare("
                INSERT INTO medical_records
                    (patient_id, doctor_id, symptoms, diagnosis, treatment, notes)
                VALUES
                    (:pid, :did, :sym, :diag, :treat, :notes)
            ");
            $stmt->execute([
                ':pid'   => $patientId,
                ':did'   => $doctorId,
                ':sym'   => $symptoms,
                ':diag'  => $diagnosis,
                ':treat' => $treatment,
                ':notes' => $notes,
            ]);

            header("Location: dpatientrecords.php?patient_id={$patientId}&saved=1");
            exit();
        }
    }
}

/* ── HELPERS ─────────────────────────────────────── */
function calcAge(string $dob): int {
    return (int) (new DateTime($dob))->diff(new DateTime())->y;
}

function relDate(string $dt): string {
    $d   = new DateTime($dt);
    $now = new DateTime();
    $days = (int) $now->diff($d)->days;
    if ($days === 0) return 'Today · ' . $d->format('g:i A');
    if ($days === 1) return 'Yesterday · ' . $d->format('g:i A');
    return $d->format('d M Y') . ' · ' . $d->format('g:i A');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Records — ApexCare</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../static/doctor_sidebar.css">
    <link rel="stylesheet" href="../static/dpatientrecord.css">
</head>
<body>

<div class="layout">
    <?php include "../static/includes/doctor_sidebar.php"; ?>

    <main class="content">

        <!-- ── Page Header ─────────────────────────────── -->
        <div class="page-header">
            <div>
                <h1>
                    <span class="header-icon"><i class="fas fa-file-medical"></i></span>
                    Patient Records
                </h1>
                <p class="page-header-sub">Search a patient and record consultation details</p>
            </div>
            <div class="header-badge">
                <i class="fas fa-calendar-day"></i>
                <?php echo date('d M Y'); ?>
            </div>
        </div>

        <!-- ── Error Alert ─────────────────────────────── -->
        <?php if ($error): ?>
            <div class="alert error" id="errorAlert">
                <i class="fas fa-circle-exclamation"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- ── Saved Alert ─────────────────────────────── -->
        <?php if (isset($_GET['saved'])): ?>
            <div class="alert success" id="savedAlert">
                <i class="fas fa-circle-check"></i>
                Medical record saved successfully.
            </div>
        <?php endif; ?>

        <!-- ── Search Card ─────────────────────────────── -->
        <div class="search-card">
            <div class="search-card-head">
                <span class="card-icon teal"><i class="fas fa-magnifying-glass"></i></span>
                <div>
                    <div class="card-title">Patient Lookup</div>
                    <div class="card-sub">Enter the patient's ID number to pull their records</div>
                </div>
            </div>
            <div class="search-card-body">
                <form method="GET" id="searchForm">
                    <div class="search-row">
                        <div class="search-field">
                            <div class="field-label">
                                <i class="fas fa-id-card"></i>
                                Patient ID
                            </div>
                            <div class="search-input-wrap">
                                <i class="fas fa-hashtag si"></i>
                                <input
                                    type="number"
                                    name="patient_id"
                                    class="r-input"
                                    min="1"
                                    placeholder="e.g. 42"
                                    value="<?php echo isset($_GET['patient_id']) ? htmlspecialchars($_GET['patient_id']) : ''; ?>"
                                    required
                                >
                            </div>
                        </div>
                        <button type="submit" class="btn-search">
                            <i class="fas fa-magnifying-glass"></i>
                            Search Patient
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ── Patient Content ─────────────────────────── -->
        <?php if ($patient):
            $fullName    = $patient['first_name'] . ' ' . $patient['last_name'];
            $initials    = strtoupper(substr($patient['first_name'], 0, 1) . substr($patient['last_name'], 0, 1));
            $age         = calcAge($patient['date_of_birth']);
            $dob         = date('d M Y', strtotime($patient['date_of_birth']));
            $genderIcon  = strtolower($patient['gender']) === 'male' ? 'mars' : 'venus';
            $historyCount = count($history);
        ?>

        <div class="patient-grid">

            <!-- ── LEFT: Patient Summary ──────────────── -->
            <div class="patient-summary">
                <div class="patient-summary-banner">
                    <div class="patient-avatar-wrap">
                        <div class="patient-avatar-lg"><?php echo $initials; ?></div>
                    </div>
                </div>

                <div class="patient-summary-body">
                    <div class="patient-name-lg"><?php echo htmlspecialchars($fullName); ?></div>
                    <div class="patient-id-chip">
                        <i class="fas fa-hashtag"></i>
                        P<?php echo str_pad($patient['id'], 4, '0', STR_PAD_LEFT); ?>
                    </div>

                    <div class="patient-detail-row">
                        <div class="detail-icon teal"><i class="fas fa-cake-candles"></i></div>
                        <div>
                            <div class="detail-label">Date of Birth</div>
                            <div class="detail-value"><?php echo $dob; ?> <span style="color:var(--text-muted);font-size:12px;">(age <?php echo $age; ?>)</span></div>
                        </div>
                    </div>

                    <div class="patient-detail-row">
                        <div class="detail-icon purple"><i class="fas fa-<?php echo $genderIcon; ?>"></i></div>
                        <div>
                            <div class="detail-label">Gender</div>
                            <div class="detail-value"><?php echo ucfirst(htmlspecialchars($patient['gender'])); ?></div>
                        </div>
                    </div>

                    <div class="record-count-chip">
                        <div class="record-count-left">
                            <i class="fas fa-folder-open"></i>
                            Medical Records
                        </div>
                        <div class="record-count-num"><?php echo $historyCount; ?></div>
                    </div>
                </div>
            </div>

            <!-- ── RIGHT COLUMN ───────────────────────── -->
            <div class="right-col">

                <!-- Medical History -->
                <div class="history-card">
                    <div class="history-card-head">
                        <div class="history-card-head-left">
                            <span class="card-icon slate"><i class="fas fa-clock-rotate-left"></i></span>
                            <div>
                                <div class="card-title">Medical History</div>
                                <div class="card-sub"><?php echo $historyCount; ?> record<?php echo $historyCount !== 1 ? 's' : ''; ?> on file</div>
                            </div>
                        </div>
                    </div>

                    <div class="timeline">
                        <?php if ($historyCount === 0): ?>
                            <div class="timeline-empty">
                                <div class="timeline-empty-icon"><i class="fas fa-folder-open"></i></div>
                                <p>No previous records for this patient.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($history as $record): ?>
                            <div class="timeline-item">
                                <div class="timeline-dot-col">
                                    <div class="timeline-dot"></div>
                                    <div class="timeline-line"></div>
                                </div>
                                <div class="timeline-content">
                                    <div class="timeline-date">
                                        <i class="far fa-calendar"></i>
                                        <?php echo relDate($record['created_at']); ?>
                                    </div>
                                    <div class="record-fields">
                                        <div class="record-field">
                                            <div class="record-field-label symptoms">
                                                <i class="fas fa-triangle-exclamation"></i>
                                                Symptoms
                                            </div>
                                            <div class="record-field-value"><?php echo nl2br(htmlspecialchars($record['symptoms'])); ?></div>
                                        </div>
                                        <div class="record-field">
                                            <div class="record-field-label diagnosis">
                                                <i class="fas fa-stethoscope"></i>
                                                Diagnosis
                                            </div>
                                            <div class="record-field-value"><?php echo nl2br(htmlspecialchars($record['diagnosis'])); ?></div>
                                        </div>
                                        <?php if ($record['treatment']): ?>
                                        <div class="record-field">
                                            <div class="record-field-label treatment">
                                                <i class="fas fa-pills"></i>
                                                Treatment
                                            </div>
                                            <div class="record-field-value"><?php echo nl2br(htmlspecialchars($record['treatment'])); ?></div>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($record['notes']): ?>
                                        <div class="record-field <?php echo !$record['treatment'] ? 'full' : ''; ?>">
                                            <div class="record-field-label notes">
                                                <i class="fas fa-note-sticky"></i>
                                                Notes
                                            </div>
                                            <div class="record-field-value"><?php echo nl2br(htmlspecialchars($record['notes'])); ?></div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Current Visit Form -->
                <div class="visit-card">
                    <div class="visit-card-head">
                        <span class="card-icon teal"><i class="fas fa-pen-to-square"></i></span>
                        <div>
                            <div class="card-title">Current Visit</div>
                            <div class="card-sub">Record today's consultation for <?php echo htmlspecialchars($patient['first_name']); ?></div>
                        </div>
                    </div>

                    <div class="visit-card-body">
                        <form method="POST" id="visitForm">
                            <input type="hidden" name="patient_id" value="<?php echo $patient['id']; ?>">

                            <div class="form-grid">

                                <!-- Symptoms -->
                                <div>
                                    <label class="f-label required symptoms-l">
                                        <i class="fas fa-triangle-exclamation"></i>
                                        Symptoms <span class="req-star">*</span>
                                    </label>
                                    <textarea
                                        name="symptoms"
                                        class="f-textarea"
                                        id="sympField"
                                        placeholder="Describe the patient's presenting symptoms…"
                                        required
                                        oninput="countChars('sympField','sympCount')"
                                    ></textarea>
                                    <div class="char-hint"><span id="sympCount">0</span> chars</div>
                                </div>

                                <!-- Diagnosis -->
                                <div>
                                    <label class="f-label required diagnosis-l">
                                        <i class="fas fa-stethoscope"></i>
                                        Diagnosis <span class="req-star">*</span>
                                    </label>
                                    <textarea
                                        name="diagnosis"
                                        class="f-textarea"
                                        id="diagField"
                                        placeholder="Your clinical diagnosis…"
                                        required
                                        oninput="countChars('diagField','diagCount')"
                                    ></textarea>
                                    <div class="char-hint"><span id="diagCount">0</span> chars</div>
                                </div>

                                <!-- Treatment -->
                                <div>
                                    <label class="f-label optional treatment-l">
                                        <i class="fas fa-pills"></i>
                                        Treatment
                                        <span style="font-size:10px;color:var(--text-muted);font-weight:500;margin-left:4px;">(optional)</span>
                                    </label>
                                    <textarea
                                        name="treatment"
                                        class="f-textarea"
                                        id="treatField"
                                        placeholder="Prescribed medications, procedures…"
                                        oninput="countChars('treatField','treatCount')"
                                    ></textarea>
                                    <div class="char-hint"><span id="treatCount">0</span> chars</div>
                                </div>

                                <!-- Notes -->
                                <div>
                                    <label class="f-label optional notes-l">
                                        <i class="fas fa-note-sticky"></i>
                                        Follow-up Notes
                                        <span style="font-size:10px;color:var(--text-muted);font-weight:500;margin-left:4px;">(optional)</span>
                                    </label>
                                    <textarea
                                        name="notes"
                                        class="f-textarea"
                                        id="notesField"
                                        placeholder="Follow-up instructions, next appointment…"
                                        oninput="countChars('notesField','notesCount')"
                                    ></textarea>
                                    <div class="char-hint"><span id="notesCount">0</span> chars</div>
                                </div>

                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn-save" id="saveBtn">
                                    <i class="fas fa-floppy-disk"></i>
                                    Save Record
                                </button>
                                <span class="form-actions-note">
                                    <i class="fas fa-lock"></i>
                                    Saved securely to patient's file
                                </span>
                            </div>
                        </form>
                    </div>
                </div>

            </div><!-- /.right-col -->
        </div><!-- /.patient-grid -->

        <?php endif; ?>

    </main>
</div>

<script>
    /* ── Char counters ── */
    function countChars(fieldId, countId) {
        document.getElementById(countId).textContent =
            document.getElementById(fieldId).value.length;
    }

    /* ── Submit loading ── */
    const visitForm = document.getElementById('visitForm');
    if (visitForm) {
        visitForm.addEventListener('submit', function () {
            const btn = document.getElementById('saveBtn');
            btn.classList.add('loading');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
        });
    }

    /* ── Auto-dismiss alerts ── */
    ['savedAlert', 'errorAlert'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        setTimeout(() => {
            el.style.transition = 'opacity 0.5s ease';
            el.style.opacity    = '0';
            setTimeout(() => el.remove(), 500);
        }, 6000);
    });
</script>

</body>
</html>