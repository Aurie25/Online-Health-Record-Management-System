<?php

require_once '../db.php';

// ─── SESSION & AUTH CHECK ────────────────────────────────────────────────────
session_start();

$doctor_id = $_SESSION['user_id'] ?? 1; 

// ─── FETCH PATIENTS ───────────────────────────────────────────────────────────
$patients = $conn->query("
    SELECT id, first_name, last_name, date_of_birth, gender, national_id
    FROM users WHERE role = 'patient' ORDER BY first_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// ─── FETCH CONDITIONS ─────────────────────────────────────────────────────────
$conditions = $conn->query("
    SELECT id, condition_name, category FROM medical_conditions ORDER BY condition_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// ─── FORM SUBMISSION ──────────────────────────────────────────────────────────
$success = [];
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── ADD NEW CONDITION TO MASTER TABLE ──────────────────────────────────────
    if ($action === 'add_condition') {
        $name     = trim($_POST['condition_name'] ?? '');
        $category = $_POST['category'] ?? '';
        $desc     = trim($_POST['description'] ?? '');

        if ($name && $category) {
            try {
                $stmt = $conn->prepare("
                    INSERT INTO medical_conditions (condition_name, category, description)
                    VALUES (:name, :cat, :desc)
                ");
                $stmt->execute([':name' => $name, ':cat' => $category, ':desc' => $desc]);
                $success[] = "Condition \"$name\" added to master list.";
                // Refresh conditions list
                $conditions = $conn->query("SELECT id, condition_name, category FROM medical_conditions ORDER BY condition_name ASC")->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $errors[] = $e->getCode() == 23000 ? "Condition already exists." : $e->getMessage();
            }
        } else {
            $errors[] = "Condition name and category are required.";
        }
    }

    // ── LINK PATIENT TO CONDITION ──────────────────────────────────────────────
    if ($action === 'link_condition') {
        $pid   = (int)($_POST['patient_id'] ?? 0);
        $cid   = (int)($_POST['condition_id'] ?? 0);
        $date  = $_POST['diagnosis_date'] ?? '';
        $stat  = $_POST['condition_status'] ?? 'active';
        $notes = trim($_POST['condition_notes'] ?? '');

        if ($pid && $cid && $date) {
            try {
                $stmt = $conn->prepare("
                    INSERT INTO patient_conditions (patient_id, condition_id, diagnosis_date, status, notes)
                    VALUES (:pid, :cid, :date, :stat, :notes)
                ");
                $stmt->execute([':pid' => $pid, ':cid' => $cid, ':date' => $date, ':stat' => $stat, ':notes' => $notes]);
                $success[] = "Condition successfully linked to patient.";
            } catch (PDOException $e) {
                $errors[] = $e->getMessage();
            }
        } else {
            $errors[] = "Patient, condition, and diagnosis date are required.";
        }
    }

    // ── DISABILITY ASSESSMENT ──────────────────────────────────────────────────
    if ($action === 'disability_assessment') {
        $pid         = (int)($_POST['da_patient_id'] ?? 0);
        $cid         = (int)($_POST['da_condition_id'] ?? 0);
        $date        = $_POST['assessment_date'] ?? date('Y-m-d');
        $dtype       = trim($_POST['disability_type'] ?? '');
        $severity    = (int)($_POST['severity_percentage'] ?? 0);
        $mobility    = $_POST['mobility_status'] ?? '';
        $selfcare    = $_POST['self_care_status'] ?? '';
        $comm        = $_POST['communication_status'] ?? '';
        $clinical    = trim($_POST['clinical_findings'] ?? '');
        $recs        = trim($_POST['recommendations'] ?? '');
        $prognosis   = $_POST['prognosis'] ?? '';
        $permanence  = $_POST['permanence'] ?? '';

        if ($pid && $cid && $dtype) {
            // Verify condition is categorised as disability
            $check = $conn->prepare("SELECT category FROM medical_conditions WHERE id = :cid");
            $check->execute([':cid' => $cid]);
            $row = $check->fetch(PDO::FETCH_ASSOC);

            if ($row && $row['category'] === 'disability') {
                try {
                    $stmt = $conn->prepare("
                        INSERT INTO disability_assessments
                        (patient_id, doctor_id, condition_id, assessment_date, disability_type,
                         severity_percentage, mobility_status, self_care_status, communication_status,
                         clinical_findings, recommendations, prognosis, permanence)
                        VALUES (:pid,:did,:cid,:date,:dtype,:sev,:mob,:sc,:comm,:clin,:recs,:prog,:perm)
                    ");
                    $stmt->execute([
                        ':pid'  => $pid,  ':did'  => $doctor_id, ':cid'  => $cid,
                        ':date' => $date, ':dtype'=> $dtype,     ':sev'  => $severity,
                        ':mob'  => $mobility,  ':sc'   => $selfcare, ':comm' => $comm,
                        ':clin' => $clinical,  ':recs' => $recs,
                        ':prog' => $prognosis, ':perm' => $permanence
                    ]);
                    // Log admin activity
                    $logStmt = $conn->prepare("
                        INSERT INTO admin_activity_logs (admin_id, action, details, ip_address)
                        VALUES (:aid, 'disability_assessment_created', :details, :ip)
                    ");
                    $logStmt->execute([
                        ':aid'     => $doctor_id,
                        ':details' => "Assessment for patient_id=$pid, condition_id=$cid",
                        ':ip'      => $_SERVER['REMOTE_ADDR']
                    ]);
                    $success[] = "Disability assessment saved. The patient can now download the official PDF report.";
                } catch (PDOException $e) {
                    $errors[] = $e->getMessage();
                }
            } else {
                $errors[] = "Selected condition is not classified as a disability. Please select a disability condition.";
            }
        } else {
            $errors[] = "Patient, condition, and disability type are required.";
        }
    }

    // ── TREATMENT SCHEDULE ─────────────────────────────────────────────────────
    if ($action === 'treatment_schedule') {
        $pid       = (int)($_POST['ts_patient_id'] ?? 0);
        $cid       = (int)($_POST['ts_condition_id'] ?? 0);
        $ttype     = trim($_POST['treatment_type'] ?? '');
        $appt_date = $_POST['appointment_date'] ?? '';
        $appt_time = $_POST['appointment_time'] ?? '';
        $notes     = trim($_POST['ts_notes'] ?? '');

        if ($pid && $cid && $ttype && $appt_date && $appt_time) {
            try {
                $datetime = $appt_date . ' ' . $appt_time . ':00';
                $stmt = $conn->prepare("
                    INSERT INTO treatment_schedules
                    (patient_id, condition_id, doctor_id, treatment_type, appointment_date, notes)
                    VALUES (:pid, :cid, :did, :ttype, :date, :notes)
                ");
                $stmt->execute([
                    ':pid'   => $pid,
                    ':cid'   => $cid,
                    ':did'   => $doctor_id,
                    ':ttype' => $ttype,
                    ':date'  => $datetime,
                    ':notes' => $notes
                ]);
                $success[] = "Treatment session scheduled successfully.";
            } catch (PDOException $e) {
                $errors[] = $e->getMessage();
            }
        } else {
            $errors[] = "All treatment schedule fields are required.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ApexCare — Patient Health Management</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../static/dpatientconditionform.css">
</head>
<body>

<div class="page-wrap">

  <!-- TOP NAV: BREADCRUMB + QUICK LINKS -->
  <div class="top-nav">

    <!-- Breadcrumb trail -->
    <nav class="breadcrumb" aria-label="Breadcrumb">
      <a href="doctor_dashboard.php">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        Dashboard
      </a>
      <span class="sep">/</span>
      <a href="dpatientrecords.php">Patient Records</a>
      <span class="sep">/</span>
      <span class="current">Patient Condition Form</span>
    </nav>

    <!-- Quick navigation links matching the sidebar -->
    <div class="quick-nav">
      <a href="doctor_dashboard.php" class="qnav-link back-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        Back to Dashboard
      </a>
      <a href="doctor_appointments.php" class="qnav-link">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        Appointments
      </a>
      <a href="doctor_schedule.php" class="qnav-link">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
        My Schedule
      </a>
      <a href="dpatientrecords.php" class="qnav-link">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        Patient Records
      </a>
    </div>

  </div>

  <!-- PAGE TITLE -->
  <div class="page-title-block">
    <h1>Patient Health Management</h1>
    <p>Link conditions, complete disability assessments, and schedule treatment sessions.</p>
  </div>

  <!-- ALERTS -->
  <div class="alerts">
    <?php foreach ($success as $msg): ?>
      <div class="alert alert-success">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
          <span>✓ <?= htmlspecialchars($msg) ?></span>
          <div style="display:flex; gap:8px; flex-shrink:0;">
            <a href="dpatientrecords.php" class="qnav-link" style="padding:5px 12px; font-size:12px; background:rgba(26,156,138,.2); border-color:rgba(26,156,138,.4); color:var(--teal2); text-decoration:none;">
              View Patient Records
            </a>
            <a href="doctor_dashboard.php" class="qnav-link" style="padding:5px 12px; font-size:12px; text-decoration:none;">
              Go to Dashboard
            </a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    <?php foreach ($errors as $msg): ?>
      <div class="alert alert-error">✕ <?= htmlspecialchars($msg) ?></div>
    <?php endforeach; ?>
  </div>

  <!-- TABS -->
  <div class="tabs-nav" role="tablist">
    <button class="tab-btn active" data-tab="tab-condition" onclick="switchTab('tab-condition', this)">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
      Link Condition
    </button>
    <button class="tab-btn" data-tab="tab-disability" onclick="switchTab('tab-disability', this)">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      Disability Assessment
    </button>
    <button class="tab-btn" data-tab="tab-schedule" onclick="switchTab('tab-schedule', this)">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
      Treatment Schedule
    </button>
    <button class="tab-btn" data-tab="tab-new-condition" onclick="switchTab('tab-new-condition', this)">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
      New Condition
    </button>
  </div>

  <!-- ════════════════════════════════════════════════════════════════════════
       TAB 1 — LINK PATIENT TO CONDITION
       ════════════════════════════════════════════════════════════════════════ -->
  <div id="tab-condition" class="panel active">
    <div class="card">
      <div class="card-title">Link Patient to Condition</div>
      <div class="card-sub">Associate a patient with an existing medical condition for long-term tracking.</div>

      <form method="POST">
        <input type="hidden" name="action" value="link_condition">

        <div class="sec-label">Patient Selection</div>
        <div class="form-grid">
          <div class="field full">
            <label>Select Patient <span class="req">*</span></label>
            <select name="patient_id" id="patient-select" required onchange="previewPatient(this)">
              <option value="">— Search and select a patient —</option>
              <?php foreach ($patients as $p): ?>
                <option value="<?= $p['id'] ?>"
                        data-dob="<?= htmlspecialchars($p['date_of_birth']) ?>"
                        data-gender="<?= htmlspecialchars($p['gender']) ?>"
                        data-nid="<?= htmlspecialchars($p['national_id']) ?>">
                  <?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?> — ID: <?= htmlspecialchars($p['national_id']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <!-- Patient quick info strip -->
            <div id="patient-preview">
              <div class="pv-item"><span>Date of Birth</span><strong id="pv-dob">—</strong></div>
              <div class="pv-item"><span>Gender</span><strong id="pv-gender">—</strong></div>
              <div class="pv-item"><span>National ID</span><strong id="pv-nid">—</strong></div>
            </div>
          </div>
        </div>

        <div class="divider"></div>
        <div class="sec-label">Condition Details</div>
        <div class="form-grid">
          <div class="field">
            <label>Medical Condition <span class="req">*</span></label>
            <select name="condition_id" required>
              <option value="">— Select a condition —</option>
              <?php foreach ($conditions as $c): ?>
                <option value="<?= $c['id'] ?>">
                  <?= htmlspecialchars($c['condition_name']) ?> (<?= $c['category'] ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field">
            <label>Diagnosis Date <span class="req">*</span></label>
            <input type="date" name="diagnosis_date" required value="<?= date('Y-m-d') ?>">
          </div>

          <div class="field">
            <label>Current Status <span class="req">*</span></label>
            <select name="condition_status">
              <option value="active">Active</option>
              <option value="managed">Managed</option>
              <option value="recovered">Recovered</option>
            </select>
          </div>

          <div class="field full">
            <label>Clinical Notes</label>
            <textarea name="condition_notes" placeholder="Additional notes about this diagnosis, context, or observations..."></textarea>
          </div>
        </div>

        <div class="form-footer-nav">
          <button type="submit" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
            Save Patient Condition
          </button>
          <div class="nav-actions">
            <a href="dpatientrecords.php" class="qnav-link">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              Patient Records
            </a>
            <a href="doctor_appointments.php" class="qnav-link">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
              Appointments
            </a>
            <a href="doctor_dashboard.php" class="qnav-link back-btn">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
              Dashboard
            </a>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- ════════════════════════════════════════════════════════════════════════
       TAB 2 — DISABILITY ASSESSMENT
       ════════════════════════════════════════════════════════════════════════ -->
  <div id="tab-disability" class="panel">
    <div class="card">
      <div class="card-title">Disability Assessment</div>
      <div class="card-sub">Complete this form to generate an official disability certification. Only conditions classified as <em>disability</em> are eligible.</div>

      <div class="info-note">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
        This assessment will be recorded and the patient will be able to download the official ApexCare Disability Report PDF from their patient portal.
      </div>

      <form method="POST">
        <input type="hidden" name="action" value="disability_assessment">

        <div class="sec-label">Patient & Condition</div>
        <div class="form-grid">
          <div class="field">
            <label>Patient <span class="req">*</span></label>
            <select name="da_patient_id" required>
              <option value="">— Select patient —</option>
              <?php foreach ($patients as $p): ?>
                <option value="<?= $p['id'] ?>">
                  <?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?> — <?= htmlspecialchars($p['national_id']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field">
            <label>Disability Condition <span class="req">*</span></label>
            <select name="da_condition_id" required>
              <option value="">— Select disability condition —</option>
              <?php foreach ($conditions as $c): ?>
                <?php if ($c['category'] === 'disability'): ?>
                  <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['condition_name']) ?></option>
                <?php endif; ?>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field">
            <label>Assessment Date <span class="req">*</span></label>
            <input type="date" name="assessment_date" required value="<?= date('Y-m-d') ?>">
          </div>

          <div class="field">
            <label>Type of Disability <span class="req">*</span></label>
            <select name="disability_type" required>
              <option value="">— Select type —</option>
              <option value="Physical Impairment">Physical Impairment</option>
              <option value="Visual Impairment">Visual Impairment</option>
              <option value="Hearing Impairment">Hearing Impairment</option>
              <option value="Speech/Communication Impairment">Speech / Communication Impairment</option>
              <option value="Intellectual Disability">Intellectual Disability</option>
              <option value="Mental Health Condition">Mental Health Condition</option>
              <option value="Multiple Disabilities">Multiple Disabilities</option>
            </select>
          </div>
        </div>

        <div class="divider"></div>
        <div class="sec-label">Functional Assessment</div>
        <div class="form-grid">
          <div class="field">
            <label>Mobility Status</label>
            <select name="mobility_status">
              <option value="">— Select —</option>
              <option value="Independent">Independent</option>
              <option value="Requires Assistive Device">Requires Assistive Device</option>
              <option value="Requires Personal Assistance">Requires Personal Assistance</option>
              <option value="Wheelchair Dependent">Wheelchair Dependent</option>
            </select>
          </div>

          <div class="field">
            <label>Self-Care Ability</label>
            <select name="self_care_status">
              <option value="">— Select —</option>
              <option value="Fully Independent">Fully Independent</option>
              <option value="Partially Dependent">Partially Dependent</option>
              <option value="Fully Dependent">Fully Dependent</option>
            </select>
          </div>

          <div class="field">
            <label>Communication Ability</label>
            <select name="communication_status">
              <option value="">— Select —</option>
              <option value="Normal">Normal</option>
              <option value="Impaired">Impaired</option>
              <option value="Requires Aid">Requires Aid</option>
            </select>
          </div>

          <div class="field">
            <label>Severity of Impairment (%)</label>
            <div class="range-wrap">
              <input type="range" name="severity_percentage" id="severity-slider" min="0" max="100" value="0" oninput="document.getElementById('severity-val').textContent=this.value+'%'">
              <div class="range-val" id="severity-val">0%</div>
            </div>
          </div>
        </div>

        <div class="divider"></div>
        <div class="sec-label">Prognosis & Permanence</div>
        <div class="form-grid">
          <div class="field">
            <label>Condition Type</label>
            <div class="check-group">
              <?php foreach(['temporary'=>'Temporary','permanent'=>'Permanent','progressive'=>'Progressive'] as $v=>$l): ?>
                <label class="check-item"><input type="radio" name="permanence" value="<?= $v ?>"> <?= $l ?></label>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="field">
            <label>Prognosis</label>
            <div class="check-group">
              <?php foreach(['improving'=>'Likely to Improve','stable'=>'Stable','deteriorating'=>'Likely to Deteriorate'] as $v=>$l): ?>
                <label class="check-item"><input type="radio" name="prognosis" value="<?= $v ?>"> <?= $l ?></label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div class="divider"></div>
        <div class="sec-label">Clinical Findings & Recommendations</div>
        <div class="form-grid">
          <div class="field full">
            <label>Clinical Examination Findings</label>
            <textarea name="clinical_findings" rows="4" placeholder="Primary and secondary diagnosis, examination outcomes..."></textarea>
          </div>

          <div class="field full">
            <label>Recommendations</label>
            <textarea name="recommendations" rows="3" placeholder="Assistive devices, therapy referrals, follow-up care..."></textarea>
          </div>
        </div>

        <div class="form-footer-nav">
          <button type="submit" class="btn btn-gold">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Submit Disability Assessment
          </button>
          <div class="nav-actions">
            <a href="dpatientrecords.php" class="qnav-link">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              Patient Records
            </a>
            <a href="doctor_appointments.php" class="qnav-link">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
              Appointments
            </a>
            <a href="doctor_dashboard.php" class="qnav-link back-btn">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
              Dashboard
            </a>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- ════════════════════════════════════════════════════════════════════════
       TAB 3 — TREATMENT SCHEDULE
       ════════════════════════════════════════════════════════════════════════ -->
  <div id="tab-schedule" class="panel">
    <div class="card">
      <div class="card-title">Schedule Treatment Session</div>
      <div class="card-sub">Plan recurring sessions for chemotherapy, dialysis, antenatal care, physiotherapy, and more.</div>

      <form method="POST">
        <input type="hidden" name="action" value="treatment_schedule">

        <div class="sec-label">Patient & Condition</div>
        <div class="form-grid">
          <div class="field">
            <label>Patient <span class="req">*</span></label>
            <select name="ts_patient_id" required>
              <option value="">— Select patient —</option>
              <?php foreach ($patients as $p): ?>
                <option value="<?= $p['id'] ?>">
                  <?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?> — <?= htmlspecialchars($p['national_id']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field">
            <label>Related Condition <span class="req">*</span></label>
            <select name="ts_condition_id" required>
              <option value="">— Select condition —</option>
              <?php foreach ($conditions as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['condition_name']) ?> (<?= $c['category'] ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="divider"></div>
        <div class="sec-label">Session Details</div>
        <div class="form-grid">
          <div class="field full">
            <label>Treatment Type <span class="req">*</span></label>
            <select name="treatment_type" required onchange="toggleCustomTreatment(this)">
              <option value="">— Select treatment type —</option>
              <optgroup label="Oncology">
                <option value="Chemotherapy">Chemotherapy</option>
                <option value="Radiotherapy">Radiotherapy</option>
                <option value="Immunotherapy">Immunotherapy</option>
              </optgroup>
              <optgroup label="Renal Care">
                <option value="Haemodialysis">Haemodialysis</option>
                <option value="Peritoneal Dialysis">Peritoneal Dialysis</option>
              </optgroup>
              <optgroup label="Maternal Health">
                <option value="Antenatal Checkup">Antenatal Checkup</option>
                <option value="Postnatal Checkup">Postnatal Checkup</option>
              </optgroup>
              <optgroup label="Rehabilitation">
                <option value="Physiotherapy">Physiotherapy</option>
                <option value="Occupational Therapy">Occupational Therapy</option>
                <option value="Speech Therapy">Speech Therapy</option>
              </optgroup>
              <optgroup label="Mental Health">
                <option value="Psychotherapy Session">Psychotherapy Session</option>
                <option value="Psychiatric Review">Psychiatric Review</option>
              </optgroup>
              <option value="other">Other (specify below)</option>
            </select>
          </div>

          <div class="field full" id="custom-treatment-wrap" style="display:none">
            <label>Specify Treatment <span class="req">*</span></label>
            <input type="text" id="custom_treatment_input" placeholder="Enter custom treatment name..." oninput="syncCustomTreatment(this)">
            <!-- hidden field that gets submitted -->
            <input type="hidden" name="treatment_type_custom" id="treatment_type_custom">
          </div>

          <div class="field">
            <label>Appointment Date <span class="req">*</span></label>
            <input type="date" name="appointment_date" required value="<?= date('Y-m-d') ?>">
          </div>

          <div class="field">
            <label>Appointment Time <span class="req">*</span></label>
            <input type="time" name="appointment_time" required value="08:00">
          </div>

          <div class="field full">
            <label>Notes & Instructions for Patient</label>
            <textarea name="ts_notes" rows="3" placeholder="Pre-session instructions, dietary restrictions, medication reminders..."></textarea>
          </div>
        </div>

        <div class="form-footer-nav">
          <button type="submit" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M12 14v4M10 16h4"/></svg>
            Schedule Treatment Session
          </button>
          <div class="nav-actions">
            <a href="doctor_schedule.php" class="qnav-link">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
              My Schedule
            </a>
            <a href="doctor_appointments.php" class="qnav-link">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
              Appointments
            </a>
            <a href="doctor_dashboard.php" class="qnav-link back-btn">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
              Dashboard
            </a>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- ════════════════════════════════════════════════════════════════════════
       TAB 4 — ADD NEW CONDITION TO MASTER LIST
       ════════════════════════════════════════════════════════════════════════ -->
  <div id="tab-new-condition" class="panel">
    <div class="card">
      <div class="card-title">Add New Medical Condition</div>
      <div class="card-sub">Extend the master conditions list used across all patient records.</div>

      <form method="POST">
        <input type="hidden" name="action" value="add_condition">

        <div class="form-grid">
          <div class="field">
            <label>Condition Name <span class="req">*</span></label>
            <input type="text" name="condition_name" required placeholder="e.g. Type 2 Diabetes, Spinal Cord Injury">
          </div>

          <div class="field">
            <label>Category <span class="req">*</span></label>
            <select name="category" required>
              <option value="">— Select category —</option>
              <option value="chronic">Chronic</option>
              <option value="disability">Disability</option>
              <option value="temporary">Temporary</option>
              <option value="mental">Mental Health</option>
              <option value="other">Other</option>
            </select>
          </div>

          <div class="field full">
            <label>Description</label>
            <textarea name="description" rows="3" placeholder="Brief description of the condition for reference..."></textarea>
          </div>
        </div>

        <div class="divider"></div>

        <!-- Current conditions quick view -->
        <?php if (!empty($conditions)): ?>
        <div class="sec-label">Current Master Conditions</div>
        <div style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:24px;">
          <?php foreach ($conditions as $c): ?>
            <span style="font-size:12px; padding:5px 12px; border-radius:20px; background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.1); color:var(--muted);">
              <?= htmlspecialchars($c['condition_name']) ?>
              <em style="color:var(--teal); margin-left:4px;"><?= $c['category'] ?></em>
            </span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="form-footer-nav">
          <button type="submit" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
            Add to Master List
          </button>
          <div class="nav-actions">
            <a href="dpatientrecords.php" class="qnav-link">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              Patient Records
            </a>
            <a href="doctor_dashboard.php" class="qnav-link back-btn">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
              Dashboard
            </a>
          </div>
        </div>
      </form>
    </div>
  </div>

</div><!-- /page-wrap -->

<script>
// ─── TAB SWITCHING ─────────────────────────────────────────────────────────
function switchTab(id, btn) {
  document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById(id).classList.add('active');
  btn.classList.add('active');
}

// ─── PATIENT PREVIEW ───────────────────────────────────────────────────────
function previewPatient(sel) {
  const opt = sel.options[sel.selectedIndex];
  const preview = document.getElementById('patient-preview');
  if (!sel.value) { preview.classList.remove('show'); return; }
  document.getElementById('pv-dob').textContent    = opt.dataset.dob    || '—';
  document.getElementById('pv-gender').textContent = opt.dataset.gender || '—';
  document.getElementById('pv-nid').textContent    = opt.dataset.nid    || '—';
  preview.classList.add('show');
}

// ─── CUSTOM TREATMENT TOGGLE ───────────────────────────────────────────────
function toggleCustomTreatment(sel) {
  const wrap = document.getElementById('custom-treatment-wrap');
  wrap.style.display = sel.value === 'other' ? 'block' : 'none';
}

function syncCustomTreatment(inp) {
  document.getElementById('treatment_type_custom').value = inp.value;
}

// Override treatment_type on submit if custom
document.querySelector('#tab-schedule form')?.addEventListener('submit', function(e) {
  const sel = this.querySelector('select[name="treatment_type"]');
  if (sel.value === 'other') {
    const custom = document.getElementById('custom_treatment_input').value.trim();
    if (!custom) { e.preventDefault(); alert('Please specify the treatment type.'); return; }
    // replace the select value with custom text
    sel.name = '_treatment_type_original';
    const h = document.createElement('input');
    h.type = 'hidden'; h.name = 'treatment_type'; h.value = custom;
    this.appendChild(h);
  }
});
</script>
</body>
</html>