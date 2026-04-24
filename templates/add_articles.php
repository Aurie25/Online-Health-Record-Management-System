<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "receptionist") {
    header("Location: login.php");
    exit();
}

$errors  = [];
$title   = '';
$content = '';
$gender  = 'all';
$min_age = 0;
$max_age = 120;
$status  = 'published';

/* ── HANDLE SUBMISSION ───────────────────────────── */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title   = trim($_POST["title"]   ?? '');
    $content = trim($_POST["content"] ?? '');
    $gender  = $_POST["gender"]       ?? 'all';
    $min_age = intval($_POST["min_age"] ?? 0);
    $max_age = intval($_POST["max_age"] ?? 120);
    $status  = $_POST["status"]       ?? 'published';

    if (empty($title))   $errors[] = "Article title is required.";
    if (empty($content)) $errors[] = "Article content is required.";
    if ($min_age > $max_age) $errors[] = "Minimum age cannot be greater than maximum age.";
    if ($min_age < 0 || $max_age > 150) $errors[] = "Age values must be between 0 and 150.";

    if (empty($errors)) {
        try {
            /* articles.created_at has a DEFAULT so we don't need to supply it */
            $stmt = $conn->prepare("
                INSERT INTO articles (title, content, target_gender, min_age, max_age, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$title, $content, $gender, $min_age, $max_age, $status]);
            header("Location: receptionist_articles.php?msg=created");
            exit();
        } catch (PDOException $e) {
            $errors[] = "Database error: " . htmlspecialchars($e->getMessage());
        }
    }
}

/* Helpers for select repopulation */
$sel = fn($field, $val) => (isset($_POST[$field]) && $_POST[$field] === $val) ? 'selected' : '';
$esc = fn($v) => htmlspecialchars($v);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Article — ApexCare</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../static/receptionist_sidebar.css">
    <link rel="stylesheet" href="../static/receptionist.css">
    <link rel="stylesheet" href="../static/add_articles.css">
</head>
<body>

<div class="layout">
    <?php include "../static/includes/receptionist_sidebar.php"; ?>

    <main class="content">

        <!-- ── Page Header ─────────────────────────────── -->
        <div class="page-header">
            <div class="page-header-left">
                <h1>
                    <span class="r-header-icon"><i class="fas fa-pen-to-square"></i></span>
                    Create New Article
                </h1>
                <p class="page-header-sub">Write and publish a health article for patients</p>
            </div>
            <div class="header-badge">
                <i class="fas fa-calendar-day"></i>
                <?php echo date('d M Y'); ?>
            </div>
        </div>

        <!-- ── Back nav ─────────────────────────────────── -->
        <div class="back-nav">
            <a href="receptionist_articles.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Back to Articles
            </a>
        </div>

        <!-- ── Errors ───────────────────────────────────── -->
        <?php if (!empty($errors)): ?>
            <div class="r-alert error">
                <i class="fas fa-circle-exclamation"></i>
                <div>
                    <strong>Please fix the following:</strong>
                    <ul>
                        <?php foreach ($errors as $err): ?>
                            <li><?php echo htmlspecialchars($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <!-- ── Editor grid ──────────────────────────────── -->
        <form method="POST" id="articleForm">
        <div class="article-editor-grid">

            <!-- ── LEFT: Form ─────────────────────────── -->
            <div>

                <!-- Basic Info -->
                <div class="form-section-card">
                    <div class="section-card-head">
                        <span class="section-card-icon amber"><i class="fas fa-circle-info"></i></span>
                        <span class="section-card-title">Basic Information</span>
                    </div>
                    <div class="section-card-body">

                        <!-- Title -->
                        <div class="r-form-group">
                            <label class="r-label">
                                <i class="fas fa-heading"></i>
                                Article Title <span class="req">*</span>
                            </label>
                            <input
                                type="text"
                                name="title"
                                class="r-input"
                                id="titleInput"
                                placeholder="e.g., Understanding High Blood Pressure"
                                value="<?php echo $esc($title); ?>"
                                maxlength="255"
                                required
                                oninput="updatePreview()"
                            >
                            <div class="char-counter" id="titleCounter">
                                <i class="far fa-keyboard"></i>
                                <span id="titleCount">0</span> / 255
                            </div>
                        </div>

                        <!-- Content -->
                        <div class="r-form-group">
                            <label class="r-label">
                                <i class="fas fa-file-lines"></i>
                                Article Content <span class="req">*</span>
                            </label>
                            <textarea
                                name="content"
                                class="r-textarea"
                                id="contentInput"
                                placeholder="Write your article content here…&#10;&#10;You can use Markdown-style formatting — see the syntax guide on the right."
                                required
                                oninput="updatePreview()"
                            ><?php echo $esc($content); ?></textarea>
                            <div class="char-counter" id="contentCounter">
                                <i class="far fa-keyboard"></i>
                                <span id="contentCount">0</span> characters
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Audience Targeting -->
                <div class="form-section-card">
                    <div class="section-card-head">
                        <span class="section-card-icon slate"><i class="fas fa-bullseye"></i></span>
                        <span class="section-card-title">Audience Targeting</span>
                    </div>
                    <div class="section-card-body">

                        <!-- Gender -->
                        <div class="r-form-group">
                            <label class="r-label">
                                <i class="fas fa-venus-mars"></i>
                                Target Gender
                            </label>
                            <div class="r-select-wrap">
                                <select name="gender" class="r-select" id="genderSelect" onchange="updatePreview()">
                                    <option value="all"    <?php echo $sel('gender','all'); ?>>All Genders</option>
                                    <option value="male"   <?php echo $sel('gender','male'); ?>>Male Only</option>
                                    <option value="female" <?php echo $sel('gender','female'); ?>>Female Only</option>
                                </select>
                            </div>
                        </div>

                        <!-- Age Range -->
                        <div class="r-form-group">
                            <label class="r-label">
                                <i class="fas fa-people-group"></i>
                                Age Range
                            </label>
                            <div class="age-row">
                                <div>
                                    <div class="age-label-small">Minimum age</div>
                                    <input type="number" name="min_age" class="r-input"
                                           id="minAge"
                                           value="<?php echo $min_age; ?>"
                                           min="0" max="150"
                                           oninput="updateAgeHint()">
                                </div>
                                <div>
                                    <div class="age-label-small">Maximum age</div>
                                    <input type="number" name="max_age" class="r-input"
                                           id="maxAge"
                                           value="<?php echo $max_age; ?>"
                                           min="0" max="150"
                                           oninput="updateAgeHint()">
                                </div>
                            </div>
                            <div class="age-hint" id="ageHint">
                                <i class="fas fa-users"></i>
                                Visible to patients aged <strong id="ageHintText"><?php echo $min_age; ?>–<?php echo $max_age; ?></strong> years
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Publication Settings -->
                <div class="form-section-card">
                    <div class="section-card-head">
                        <span class="section-card-icon green"><i class="fas fa-rocket"></i></span>
                        <span class="section-card-title">Publication Settings</span>
                    </div>
                    <div class="section-card-body">

                        <div class="r-form-group">
                            <label class="r-label">
                                <i class="fas fa-circle-dot"></i>
                                Status
                            </label>
                            <div class="status-options">

                                <label class="status-option published <?php echo $status === 'published' ? 'selected' : ''; ?>"
                                       onclick="selectStatus('published', this)">
                                    <input type="radio" name="status" value="published"
                                           <?php echo $status === 'published' ? 'checked' : ''; ?>>
                                    <div class="status-option-icon"><i class="fas fa-globe"></i></div>
                                    <span class="status-option-label">Published</span>
                                    <span class="status-option-sub">Visible to patients now</span>
                                </label>

                                <label class="status-option draft <?php echo $status === 'draft' ? 'selected' : ''; ?>"
                                       onclick="selectStatus('draft', this)">
                                    <input type="radio" name="status" value="draft"
                                           <?php echo $status === 'draft' ? 'checked' : ''; ?>>
                                    <div class="status-option-icon"><i class="fas fa-pen-to-square"></i></div>
                                    <span class="status-option-label">Draft</span>
                                    <span class="status-option-sub">Save without publishing</span>
                                </label>

                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="article-actions">
                            <button type="submit" class="btn-save" id="submitBtn">
                                <i class="fas fa-paper-plane"></i>
                                Save Article
                            </button>
                            <a href="receptionist_articles.php" class="btn-cancel-link">
                                <i class="fas fa-xmark"></i>
                                Cancel
                            </a>
                        </div>

                    </div>
                </div>

            </div>

            <!-- ── RIGHT: Markdown guide + Live Preview ── -->
            <div>

                <!-- Markdown hint card -->
                <div class="markdown-hint-card">
                    <div class="markdown-hint-head">
                        <i class="fas fa-code"></i>
                        <span class="markdown-hint-title">Formatting Guide</span>
                    </div>
                    <div class="markdown-hint-body">
                        <table class="md-table">
                            <tr>
                                <td><code class="md-syntax">## Heading</code></td>
                                <td class="md-result">Section heading</td>
                            </tr>
                            <tr>
                                <td><code class="md-syntax">### Sub-heading</code></td>
                                <td class="md-result">Smaller heading</td>
                            </tr>
                            <tr>
                                <td><code class="md-syntax">**bold**</code></td>
                                <td class="md-result"><strong>Bold text</strong></td>
                            </tr>
                            <tr>
                                <td><code class="md-syntax">*italic*</code></td>
                                <td class="md-result"><em>Italic text</em></td>
                            </tr>
                            <tr>
                                <td><code class="md-syntax">- item</code></td>
                                <td class="md-result">Bullet list</td>
                            </tr>
                            <tr>
                                <td><code class="md-syntax">1. item</code></td>
                                <td class="md-result">Numbered list</td>
                            </tr>
                            <tr>
                                <td><code class="md-syntax">> quote</code></td>
                                <td class="md-result">Block quote</td>
                            </tr>
                            <tr>
                                <td><code class="md-syntax">==highlight==</code></td>
                                <td class="md-result">Highlighted text</td>
                            </tr>
                            <tr>
                                <td><code class="md-syntax">---</code></td>
                                <td class="md-result">Divider line</td>
                            </tr>
                            <tr>
                                <td><code class="md-syntax">🩺 💊 ❤️</code></td>
                                <td class="md-result">Emojis just work!</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Live preview card -->
                <div class="preview-card">
                    <div class="preview-card-head">
                        <div class="preview-card-title">
                            <i class="fas fa-eye"></i>
                            Patient Preview
                        </div>
                        <div class="preview-live-dot" title="Live preview"></div>
                    </div>
                    <div class="preview-body" id="previewBody">
                        <div class="preview-empty" id="previewEmpty">
                            <i class="fas fa-pen-nib"></i>
                            <span>Start typing to see how your article will look to patients</span>
                        </div>
                        <div id="previewContent" style="display:none;">
                            <div class="preview-article-title" id="previewTitle"></div>
                            <div class="preview-article-meta" id="previewMeta"></div>
                            <div class="preview-article-body" id="previewBody2"></div>
                        </div>
                    </div>
                </div>

            </div>

        </div>
        </form>

    </main>
</div>

<script>
    /* ── Character counters ── */
    const titleInput   = document.getElementById('titleInput');
    const contentInput = document.getElementById('contentInput');

    function updateTitleCounter() {
        const len = titleInput.value.length;
        document.getElementById('titleCount').textContent = len;
        const el = document.getElementById('titleCounter');
        el.className = 'char-counter' + (len > 220 ? ' warn' : '') + (len >= 255 ? ' over' : '');
    }

    function updateContentCounter() {
        const len = contentInput.value.length;
        document.getElementById('contentCount').textContent = len;
    }

    titleInput.addEventListener('input', updateTitleCounter);
    contentInput.addEventListener('input', updateContentCounter);

    /* Init */
    updateTitleCounter();
    updateContentCounter();

    /* ── Age hint ── */
    function updateAgeHint() {
        const min = document.getElementById('minAge').value || 0;
        const max = document.getElementById('maxAge').value || 120;
        document.getElementById('ageHintText').textContent = min + '–' + max;
    }

    /* ── Status radio cards ── */
    function selectStatus(val, label) {
        document.querySelectorAll('.status-option').forEach(el => {
            el.classList.remove('selected');
        });
        label.classList.add('selected');
        label.querySelector('input[type="radio"]').checked = true;
    }

    /* ── Minimal markdown renderer for preview ── */
    function renderMarkdownPreview(text) {
        if (!text) return '';
        let t = text
            .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') /* safe escape */
            .replace(/^## (.+)$/gm, '<strong style="display:block;font-size:14px;font-weight:800;color:#1e293b;margin:12px 0 4px;">$1</strong>')
            .replace(/^### (.+)$/gm, '<strong style="display:block;font-size:13px;font-weight:700;color:#334155;margin:10px 0 3px;">$1</strong>')
            .replace(/\*\*\*(.+?)\*\*\*/g, '<strong><em>$1</em></strong>')
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.+?)\*/g, '<em>$1</em>')
            .replace(/==(.+?)==/g, '<mark style="background:rgba(245,158,11,0.2);padding:1px 4px;border-radius:3px;">$1</mark>')
            .replace(/^> (.+)$/gm, '<span style="display:block;border-left:3px solid #f59e0b;padding:4px 10px;color:#92400e;font-style:italic;margin:6px 0;background:#fffbeb;border-radius:0 6px 6px 0;">$1</span>')
            .replace(/^---$/gm, '<hr style="border:none;border-top:1px dashed #e2e8f0;margin:10px 0;">')
            .replace(/^- (.+)$/gm, '• $1<br>')
            .replace(/^\d+\. (.+)$/gm, '→ $1<br>')
            .replace(/\n/g, '<br>');
        return t;
    }

    function updatePreview() {
        const title   = titleInput.value.trim();
        const content = contentInput.value.trim();
        const gender  = document.getElementById('genderSelect').value;

        const empty   = document.getElementById('previewEmpty');
        const wrapper = document.getElementById('previewContent');

        if (!title && !content) {
            empty.style.display   = 'flex';
            wrapper.style.display = 'none';
            return;
        }

        empty.style.display   = 'none';
        wrapper.style.display = 'block';

        document.getElementById('previewTitle').textContent = title || 'Untitled Article';

        /* Meta chips */
        const genderLabel = gender === 'all' ? 'All genders' : (gender === 'male' ? 'Male' : 'Female');
        const genderIcon  = gender === 'male' ? 'mars' : (gender === 'female' ? 'venus' : 'venus-mars');
        const min = document.getElementById('minAge').value || 0;
        const max = document.getElementById('maxAge').value || 120;

        document.getElementById('previewMeta').innerHTML = `
            <span class="preview-meta-chip"><i class="fas fa-${genderIcon}"></i>${genderLabel}</span>
            <span class="preview-meta-chip"><i class="fas fa-people-group"></i>Ages ${min}–${max}</span>
            <span class="preview-meta-chip"><i class="far fa-clock"></i>${Math.max(1,Math.ceil(content.split(/\s+/).filter(Boolean).length/200))} min read</span>
        `;

        /* Show first 400 chars of rendered content */
        const preview = content.length > 400 ? content.substring(0, 400) + '…' : content;
        document.getElementById('previewBody2').innerHTML = renderMarkdownPreview(preview);
    }

    /* Initial render if values are repopulated after error */
    updatePreview();

    /* ── Submit loading state ── */
    document.getElementById('articleForm').addEventListener('submit', function() {
        const btn = document.getElementById('submitBtn');
        btn.classList.add('loading');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
    });
</script>

</body>
</html>