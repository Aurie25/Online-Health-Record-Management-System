<?php
// Conditions · Disability Assessments + PDF · Treatment Schedule

session_start();
require_once '../db.php';

// ─── AUTH ─────────────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php"); exit();
}

$patient_id  = (int)$_SESSION['user_id'];
$firstName   = htmlspecialchars($_SESSION['first_name'] ?? 'Patient');
$currentPage = basename($_SERVER['PHP_SELF']);

// ─── FETCH CONDITIONS ─────────────────────────────────────────────────────────
$stmtC = $conn->prepare("
    SELECT pc.id, pc.diagnosis_date, pc.status AS condition_status, pc.notes,
           mc.condition_name, mc.category, mc.description
    FROM   patient_conditions pc
    JOIN   medical_conditions mc ON mc.id = pc.condition_id
    WHERE  pc.patient_id = :pid
    ORDER  BY pc.diagnosis_date DESC
");
$stmtC->execute([':pid' => $patient_id]);
$myConditions = $stmtC->fetchAll(PDO::FETCH_ASSOC);

// ─── FETCH DISABILITY ASSESSMENTS ────────────────────────────────────────────
$stmtA = $conn->prepare("
    SELECT da.*, mc.condition_name,
           CONCAT(u.first_name, ' ', u.last_name) AS doctor_name
    FROM   disability_assessments da
    JOIN   medical_conditions mc ON mc.id = da.condition_id
    JOIN   users u               ON u.id  = da.doctor_id
    WHERE  da.patient_id = :pid
    ORDER  BY da.assessment_date DESC
");
$stmtA->execute([':pid' => $patient_id]);
$myAssessments = $stmtA->fetchAll(PDO::FETCH_ASSOC);

// ─── FETCH TREATMENT SCHEDULES ────────────────────────────────────────────────
$stmtS = $conn->prepare("
    SELECT ts.*, mc.condition_name,
           CONCAT(u.first_name, ' ', u.last_name) AS doctor_name
    FROM   treatment_schedules ts
    JOIN   medical_conditions mc ON mc.id = ts.condition_id
    LEFT JOIN users u            ON u.id  = ts.doctor_id
    WHERE  ts.patient_id = :pid
    ORDER  BY ts.appointment_date ASC
");
$stmtS->execute([':pid' => $patient_id]);
$mySchedules = $stmtS->fetchAll(PDO::FETCH_ASSOC);

// ─── SPLIT SCHEDULES ──────────────────────────────────────────────────────────
$upcoming = array_filter($mySchedules, fn($s) =>
    strtotime($s['appointment_date']) >= strtotime('today') && $s['status'] === 'scheduled'
);
$past = array_filter($mySchedules, fn($s) =>
    strtotime($s['appointment_date']) < strtotime('today') || $s['status'] !== 'scheduled'
);

// ─── QUICK FLAGS ──────────────────────────────────────────────────────────────
$hasDisability = !empty($myAssessments);
$isPregnant    = !empty(array_filter($myConditions,
    fn($c) => stripos($c['condition_name'], 'pregnan') !== false
));

// ─── CATEGORY → LEFT-RULE COLOUR (inline only for dynamic colour) ─────────────
$catRuleColors = [
    'chronic'    => '#d4971a',
    'disability' => '#ef4444',
    'temporary'  => '#22c55e',
    'mental'     => '#a855f7',
    'other'      => '#64748b',
];

// ─── TREATMENT EMOJI MAP ─────────────────────────────────────────────────────
$emojiMap = [
    'Chemotherapy'         => '🧪', 'Radiotherapy'         => '☢️',
    'Immunotherapy'        => '💉', 'Haemodialysis'        => '💧',
    'Peritoneal Dialysis'  => '💧', 'Antenatal Checkup'    => '🤱',
    'Postnatal Checkup'    => '🤱', 'Physiotherapy'        => '🏃',
    'Occupational Therapy' => '🔧', 'Speech Therapy'       => '🗣️',
    'Psychotherapy Session'=> '🧠', 'Psychiatric Review'   => '🧠',
];

function treatmentEmoji(string $type, array $map): string {
    foreach ($map as $k => $v) {
        if (stripos($type, $k) !== false) return $v;
    }
    return '📋';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Health — ApexCare</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<!--
    Load order:
    1. patient_globals.css  — CSS custom properties, sidebar, content layout
    2. patient_health_tracker.css — this page's component styles
-->
<link rel="stylesheet" href="../static/patient_sidebar.css">
<link rel="stylesheet" href="../static/patienthealthtracker.css">
</head>
<body>

<?php include '../static/includes/patient_sidebar.php'; ?>

<div class="content" id="mainContent">

  <!-- ═══════════════════════════════════════════════════
       HERO
       ═══════════════════════════════════════════════════ -->
  <div class="tracker-hero">

    <div class="tracker-hero-left">
      <div class="tracker-eyebrow">
        <i class="fas fa-heart-pulse"></i>
        Health Overview
      </div>
      <h1>Hello, <span><?= $firstName ?></span>.<br>Your health at a glance.</h1>
    </div>

    <div class="tracker-hero-right">
      <?php if ($hasDisability): ?>
        <div class="tracker-chip red">
          <i class="fas fa-wheelchair"></i> Disability on Record
        </div>
      <?php endif; ?>
      <?php if ($isPregnant): ?>
        <div class="tracker-chip teal">
          <i class="fas fa-baby"></i> Pregnancy Tracked
        </div>
      <?php endif; ?>
      <?php if (!empty($upcoming)): ?>
        <div class="tracker-chip gold">
          <i class="fas fa-calendar-check"></i>
          <?= count($upcoming) ?> Upcoming Session<?= count($upcoming) > 1 ? 's' : '' ?>
        </div>
      <?php endif; ?>
      <?php if (empty($myConditions) && !$hasDisability): ?>
        <div class="tracker-chip green">
          <i class="fas fa-circle-check"></i> No Active Conditions
        </div>
      <?php endif; ?>
    </div>

  </div>

  <!-- ═══════════════════════════════════════════════════
       STAT STRIP
       ═══════════════════════════════════════════════════ -->
  <div class="stat-strip">

    <div class="stat-card teal">
      <div class="stat-icon teal"><i class="fas fa-stethoscope"></i></div>
      <div>
        <div class="stat-val"><?= count($myConditions) ?></div>
        <div class="stat-lbl">Conditions</div>
      </div>
    </div>

    <div class="stat-card red">
      <div class="stat-icon red"><i class="fas fa-wheelchair"></i></div>
      <div>
        <div class="stat-val"><?= count($myAssessments) ?></div>
        <div class="stat-lbl">Assessments</div>
      </div>
    </div>

    <div class="stat-card gold">
      <div class="stat-icon gold"><i class="fas fa-calendar-check"></i></div>
      <div>
        <div class="stat-val"><?= count($upcoming) ?></div>
        <div class="stat-lbl">Upcoming</div>
      </div>
    </div>

    <div class="stat-card purple">
      <div class="stat-icon purple"><i class="fas fa-clock-rotate-left"></i></div>
      <div>
        <div class="stat-val"><?= count($past) ?></div>
        <div class="stat-lbl">Past Sessions</div>
      </div>
    </div>

  </div>

  <!-- ═══════════════════════════════════════════════════
       SECTION 1 · MY CONDITIONS
       ═══════════════════════════════════════════════════ -->
  <div class="conditions-section">

    <div class="section-heading">
      <div class="section-heading-left">
        <div class="section-icon teal"><i class="fas fa-stethoscope"></i></div>
        <h2>My Conditions</h2>
      </div>
      <?php if (!empty($myConditions)): ?>
        <span class="section-count"><?= count($myConditions) ?> total</span>
      <?php endif; ?>
    </div>

    <?php if (empty($myConditions)): ?>
      <div class="empty-state">
        <div class="empty-icon"><i class="fas fa-notes-medical"></i></div>
        <p>No conditions linked to your profile yet.</p>
      </div>
    <?php else: ?>
      <div class="conditions-grid">
        <?php foreach ($myConditions as $c):
          $cat     = $c['category'] ?? 'other';
          $stat    = $c['condition_status'];
          $ruleCol = $catRuleColors[$cat] ?? '#64748b';
        ?>
        <div class="cond-card">
          <!-- category colour rule — only inline because it's dynamic per row -->
          <div class="cond-rule" style="background:<?= $ruleCol ?>"></div>

          <div class="cond-tag <?= htmlspecialchars($cat) ?>">
            <i class="fas fa-circle-dot"></i>
            <?= ucfirst(htmlspecialchars($cat)) ?>
          </div>

          <div class="cond-name"><?= htmlspecialchars($c['condition_name']) ?></div>

          <?php if ($c['description']): ?>
            <div class="cond-desc"><?= htmlspecialchars($c['description']) ?></div>
          <?php endif; ?>

          <?php if ($c['notes']): ?>
            <div class="cond-notes">
              <?= htmlspecialchars(substr($c['notes'], 0, 130)) ?><?= strlen($c['notes']) > 130 ? '…' : '' ?>
            </div>
          <?php endif; ?>

          <div class="cond-footer">
            <div class="cond-date">
              <i class="fas fa-calendar-alt"></i>
              <?= date('d M Y', strtotime($c['diagnosis_date'])) ?>
            </div>
            <div class="cond-status <?= htmlspecialchars($stat) ?>">
              <?= ucfirst(htmlspecialchars($stat)) ?>
              <i class="fas fa-arrow-right"></i>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>

  <!-- ═══════════════════════════════════════════════════
       SECTION 2 · DISABILITY ASSESSMENTS
       ═══════════════════════════════════════════════════ -->
  <div class="assessment-section">

    <div class="section-heading">
      <div class="section-heading-left">
        <div class="section-icon red"><i class="fas fa-wheelchair"></i></div>
        <h2>Disability Assessments</h2>
      </div>
      <?php if (!empty($myAssessments)): ?>
        <span class="section-count">
          <?= count($myAssessments) ?> report<?= count($myAssessments) !== 1 ? 's' : '' ?>
        </span>
      <?php endif; ?>
    </div>

    <?php if (empty($myAssessments)): ?>
      <div class="empty-state">
        <div class="empty-icon"><i class="fas fa-file-medical"></i></div>
        <p>No disability assessments on record. Your doctor will create one if applicable.</p>
      </div>
    <?php else: ?>

      <?php
      $progLabels = ['improving' => 'Improving', 'stable' => 'Stable', 'deteriorating' => 'Deteriorating'];
      $progIcons  = ['improving' => 'fa-arrow-trend-up', 'stable' => 'fa-minus', 'deteriorating' => 'fa-arrow-trend-down'];
      foreach ($myAssessments as $a):
        $sev       = (int)($a['severity_percentage'] ?? 0);
        $prognosis = $a['prognosis']  ?? 'stable';
        $perm      = $a['permanence'] ?? '';
      ?>
      <div class="assess-card">

        <!-- Card header -->
        <div class="assess-head">
          <div class="assess-head-left">
            <div class="assess-head-icon"><i class="fas fa-clipboard-list"></i></div>
            <div>
              <div class="assess-head-title"><?= htmlspecialchars($a['disability_type']) ?></div>
              <div class="assess-head-sub">
                <?= htmlspecialchars($a['condition_name']) ?>
                &nbsp;·&nbsp; <?= date('d M Y', strtotime($a['assessment_date'])) ?>
                &nbsp;·&nbsp; Dr. <?= htmlspecialchars($a['doctor_name']) ?>
              </div>
            </div>
          </div>
          <div class="assess-head-right">
            <?php if ($prognosis): ?>
              <span class="badge <?= $prognosis ?>">
                <i class="fas <?= $progIcons[$prognosis] ?? 'fa-minus' ?>"></i>
                <?= $progLabels[$prognosis] ?? ucfirst($prognosis) ?>
              </span>
            <?php endif; ?>
            <?php if ($perm): ?>
              <span class="badge <?= $perm ?>"><?= ucfirst($perm) ?></span>
            <?php endif; ?>
          </div>
        </div>

        <!-- Card body -->
        <div class="assess-body">

          <!-- Severity -->
          <?php if ($sev > 0): ?>
          <div class="severity-wrap">
            <div class="severity-label-row">
              <span>Severity of Impairment</span>
              <span class="severity-pct"><?= $sev ?>%</span>
            </div>
            <div class="severity-track">
              <div class="severity-fill" data-width="<?= $sev ?>"></div>
            </div>
          </div>
          <?php endif; ?>

          <!-- Functional grid -->
          <?php if ($a['mobility_status'] || $a['self_care_status'] || $a['communication_status']): ?>
          <div class="functional-grid">
            <?php if ($a['mobility_status']): ?>
            <div class="func-item">
              <div class="func-label"><i class="fas fa-person-walking"></i>Mobility</div>
              <div class="func-val"><?= htmlspecialchars($a['mobility_status']) ?></div>
            </div>
            <?php endif; ?>
            <?php if ($a['self_care_status']): ?>
            <div class="func-item">
              <div class="func-label"><i class="fas fa-hands"></i>Self-Care</div>
              <div class="func-val"><?= htmlspecialchars($a['self_care_status']) ?></div>
            </div>
            <?php endif; ?>
            <?php if ($a['communication_status']): ?>
            <div class="func-item">
              <div class="func-label"><i class="fas fa-comments"></i>Communication</div>
              <div class="func-val"><?= htmlspecialchars($a['communication_status']) ?></div>
            </div>
            <?php endif; ?>
          </div>
          <?php endif; ?>

          <!-- Clinical findings -->
          <?php if ($a['clinical_findings']): ?>
          <div class="clinical-block findings">
            <strong>Clinical Findings</strong>
            <?= nl2br(htmlspecialchars($a['clinical_findings'])) ?>
          </div>
          <?php endif; ?>

          <!-- Recommendations -->
          <?php if ($a['recommendations']): ?>
          <div class="clinical-block recs">
            <strong><i class="fas fa-lightbulb" style="margin-right:5px"></i>Recommendations</strong>
            <?= nl2br(htmlspecialchars($a['recommendations'])) ?>
          </div>
          <?php endif; ?>

          <!-- PDF download -->
          <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-top:4px;">
            <?php if ($a['pdf_path']): ?>
              <a href="<?= htmlspecialchars($a['pdf_path']) ?>" class="btn-pdf" download>
                <i class="fas fa-file-pdf"></i> Download Official Report
              </a>
            <?php else: ?>
              <a href="generatedisabilitypdf.php?id=<?= (int)$a['id'] ?>" class="btn-pdf">
                <i class="fas fa-file-pdf"></i> Generate &amp; Download PDF
              </a>
            <?php endif; ?>
            <span class="pdf-note">
              <i class="fas fa-shield-halved"></i>
              Official ApexCare certified document
            </span>
          </div>

        </div><!-- /assess-body -->
      </div><!-- /assess-card -->
      <?php endforeach; ?>

    <?php endif; ?>
  </div>

  <!-- ═══════════════════════════════════════════════════
       SECTION 3 · TREATMENT SCHEDULE
       ═══════════════════════════════════════════════════ -->
  <div class="schedule-section">

    <div class="section-heading">
      <div class="section-heading-left">
        <div class="section-icon gold"><i class="fas fa-calendar-check"></i></div>
        <h2>Treatment Schedule</h2>
      </div>
      <?php if (!empty($upcoming)): ?>
        <span class="section-count"><?= count($upcoming) ?> upcoming</span>
      <?php endif; ?>
    </div>

    <?php if (empty($mySchedules)): ?>
      <div class="empty-state">
        <div class="empty-icon"><i class="fas fa-calendar-xmark"></i></div>
        <p>No treatment sessions scheduled yet.</p>
      </div>
    <?php else: ?>

      <!-- UPCOMING -->
      <?php if (!empty($upcoming)): ?>
        <div class="tl-sublabel">
          <i class="fas fa-circle-dot"></i> Upcoming Sessions
        </div>
        <div class="timeline">
          <?php foreach ($upcoming as $s):
            $emoji = treatmentEmoji($s['treatment_type'], $emojiMap);
            $dt    = new DateTime($s['appointment_date']);
          ?>
          <div class="tl-item">
            <div class="tl-dot upcoming"></div>
            <div class="tl-card">
              <div class="tl-emoji"><?= $emoji ?></div>
              <div class="tl-body">
                <div class="tl-treatment"><?= htmlspecialchars($s['treatment_type']) ?></div>
                <div class="tl-condition">
                  <i class="fas fa-circle-dot"></i><?= htmlspecialchars($s['condition_name']) ?>
                </div>
                <?php if ($s['notes']): ?>
                  <div class="tl-notes"><?= htmlspecialchars($s['notes']) ?></div>
                <?php endif; ?>
                <div class="tl-badge scheduled">
                  <i class="fas fa-clock"></i> Scheduled
                </div>
              </div>
              <div class="tl-right">
                <div class="tl-date"><?= $dt->format('d M Y') ?></div>
                <div class="tl-time">
                  <i class="fas fa-clock"></i><?= $dt->format('H:i') ?>
                </div>
                <?php if ($s['doctor_name']): ?>
                  <div class="tl-doctor">Dr. <?= htmlspecialchars($s['doctor_name']) ?></div>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <!-- PAST (collapsible) -->
      <?php if (!empty($past)): ?>
        <button class="btn-toggle-past" id="pastToggle" onclick="togglePast()">
          <i class="fas fa-chevron-down"></i>
          Show Past Sessions (<?= count($past) ?>)
        </button>

        <div id="pastSessions" style="display:none;margin-top:16px;">
          <div class="tl-sublabel past">
            <i class="fas fa-clock-rotate-left"></i> Past Sessions
          </div>
          <div class="timeline">
            <?php foreach ($past as $s):
              $emoji  = treatmentEmoji($s['treatment_type'], $emojiMap);
              $dt     = new DateTime($s['appointment_date']);
              $status = $s['status'] ?? 'completed';
            ?>
            <div class="tl-item">
              <div class="tl-dot <?= htmlspecialchars($status) ?>"></div>
              <div class="tl-card" style="opacity:0.72">
                <div class="tl-emoji"><?= $emoji ?></div>
                <div class="tl-body">
                  <div class="tl-treatment"><?= htmlspecialchars($s['treatment_type']) ?></div>
                  <div class="tl-condition">
                    <i class="fas fa-circle-dot"></i><?= htmlspecialchars($s['condition_name']) ?>
                  </div>
                  <?php if ($s['notes']): ?>
                    <div class="tl-notes"><?= htmlspecialchars($s['notes']) ?></div>
                  <?php endif; ?>
                  <div class="tl-badge <?= htmlspecialchars($status) ?>">
                    <?= ucfirst($status) ?>
                  </div>
                </div>
                <div class="tl-right">
                  <div class="tl-date"><?= $dt->format('d M Y') ?></div>
                  <div class="tl-time">
                    <i class="fas fa-clock"></i><?= $dt->format('H:i') ?>
                  </div>
                  <?php if ($s['doctor_name']): ?>
                    <div class="tl-doctor">Dr. <?= htmlspecialchars($s['doctor_name']) ?></div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

    <?php endif; ?>
  </div>

</div><!-- /content -->

<script>
// Animate severity bars after page load
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.severity-fill[data-width]').forEach(bar => {
        requestAnimationFrame(() => {
            setTimeout(() => { bar.style.width = bar.dataset.width + '%'; }, 200);
        });
    });
});

// Toggle past sessions panel
const PAST_COUNT = <?= count($past) ?>;

function togglePast() {
    const panel  = document.getElementById('pastSessions');
    const btn    = document.getElementById('pastToggle');
    const isOpen = panel.style.display !== 'none';

    panel.style.display = isOpen ? 'none' : 'block';
    btn.classList.toggle('open', !isOpen);
    btn.innerHTML = isOpen
        ? '<i class="fas fa-chevron-down"></i> Show Past Sessions (' + PAST_COUNT + ')'
        : '<i class="fas fa-chevron-up"></i>   Hide Past Sessions (' + PAST_COUNT + ')';
}
</script>

</body>
</html>