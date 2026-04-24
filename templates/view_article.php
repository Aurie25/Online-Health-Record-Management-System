<?php
session_start();
require '../db.php';

/* -------------------------
   SESSION PROTECTION
--------------------------*/
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit();
}

/* -------------------------
   GET ARTICLE ID
--------------------------*/
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: patienthome.php");
    exit();
}

$article_id = intval($_GET['id']);
$patient_id = $_SESSION['user_id'];

/* -------------------------
   FETCH PATIENT DATA
--------------------------*/
$stmt = $conn->prepare("SELECT first_name, gender, date_of_birth FROM users WHERE id = ?");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$_SESSION['first_name'] = $patient['first_name'];

$patient_gender = strtolower($patient['gender']);
$dob            = new DateTime($patient['date_of_birth']);
$today          = new DateTime();
$patient_age    = $today->diff($dob)->y;

/* -------------------------
   FETCH THE ARTICLE
   (verify patient has access)
--------------------------*/
$stmt = $conn->prepare("
    SELECT * FROM articles
    WHERE id = ?
    AND status = 'published'
    AND (target_gender = 'all' OR target_gender = ?)
    AND ? BETWEEN min_age AND max_age
");
$stmt->execute([$article_id, $patient_gender, $patient_age]);
$article = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$article) {
    header("Location: patienthome.php?error=not_found");
    exit();
}

/* -------------------------
   SAFE MARKDOWN RENDERER
   Supports: headings, bold, italic, bold-italic,
   inline code, code blocks, blockquotes,
   unordered/ordered lists, horizontal rules,
   highlight ==text==, and emojis (plain Unicode).
   Does NOT execute scripts — tags are stripped first.
--------------------------*/
function renderArticle(string $raw): string {

    /* 1. Strip any HTML tags the admin may have accidentally pasted */
    $text = strip_tags($raw);

    /* 2. Preserve emojis — they're already valid UTF-8, nothing to do */

    /* 3. Fenced code blocks ```lang\n...\n``` */
    $text = preg_replace_callback(
        '/```(\w*)\n(.*?)```/s',
        fn($m) => '<pre class="art-code-block"><code>' . htmlspecialchars(trim($m[2])) . '</code></pre>',
        $text
    );

    /* 4. Blockquotes  > text */
    $text = preg_replace(
        '/^> (.+)$/m',
        '<blockquote class="art-blockquote">$1</blockquote>',
        $text
    );

    /* 5. Horizontal rule --- */
    $text = preg_replace('/^-{3,}$/m', '<hr class="art-rule">', $text);

    /* 6. Headings  ## H2  ### H3  #### H4 */
    $text = preg_replace('/^#### (.+)$/m', '<h4 class="art-h4">$1</h4>', $text);
    $text = preg_replace('/^### (.+)$/m',  '<h3 class="art-h3">$1</h3>', $text);
    $text = preg_replace('/^## (.+)$/m',   '<h2 class="art-h2">$1</h2>', $text);
    $text = preg_replace('/^# (.+)$/m',    '<h2 class="art-h2">$1</h2>', $text); /* treat H1 as H2 inside body */

    /* 7. Unordered lists  - item  or  * item */
    $text = preg_replace_callback(
        '/((?:^[-*] .+\n?)+)/m',
        function ($m) {
            $items = preg_replace('/^[-*] (.+)$/m', '<li>$1</li>', trim($m[1]));
            return '<ul class="art-ul">' . $items . '</ul>';
        },
        $text
    );

    /* 8. Ordered lists  1. item */
    $text = preg_replace_callback(
        '/((?:^\d+\. .+\n?)+)/m',
        function ($m) {
            $items = preg_replace('/^\d+\. (.+)$/m', '<li>$1</li>', trim($m[1]));
            return '<ol class="art-ol">' . $items . '</ol>';
        },
        $text
    );

    /* 9. Inline: bold-italic ***text*** */
    $text = preg_replace('/\*\*\*(.+?)\*\*\*/s', '<strong><em>$1</em></strong>', $text);

    /* 10. Bold **text** */
    $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);

    /* 11. Italic *text* */
    $text = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $text);

    /* 12. Inline code `code` */
    $text = preg_replace('/`([^`]+)`/', '<code class="art-inline-code">$1</code>', $text);

    /* 13. Highlight ==text== */
    $text = preg_replace('/==(.+?)==/', '<mark class="art-mark">$1</mark>', $text);

    /* 14. Wrap bare paragraphs (lines not already wrapped in a block tag) */
    $lines  = explode("\n", $text);
    $output = '';
    $skip   = ['<h2','<h3','<h4','<ul','<ol','<li','<pre','<blockquote','<hr'];

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            $output .= "\n";
            continue;
        }
        $isBlock = false;
        foreach ($skip as $tag) {
            if (str_starts_with($trimmed, $tag)) { $isBlock = true; break; }
        }
        $output .= $isBlock ? $trimmed . "\n" : '<p class="art-p">' . $trimmed . '</p>' . "\n";
    }

    /* 15. Collapse multiple blank lines left by block elements */
    $output = preg_replace('/\n{3,}/', "\n\n", $output);

    return $output;
}

/* -------------------------
   DERIVED ARTICLE STATS
--------------------------*/
$wordCount = str_word_count(strip_tags($article['content']));
$readTime  = max(1, ceil($wordCount / 200));

$genderLabel = $article['target_gender'] === 'all'
    ? 'All genders'
    : ucfirst($article['target_gender']) . 's';

$ageLabel = $article['min_age'] . '–' . $article['max_age'] . ' years';

$publishDate = date('d M Y', strtotime($article['created_at']));

/* -------------------------
   FETCH RELATED ARTICLES
--------------------------*/
$stmt = $conn->prepare("
    SELECT id, title,
           LEFT(REGEXP_REPLACE(content, '<[^>]+>', ''), 130) AS preview
    FROM articles
    WHERE status = 'published'
    AND id != ?
    AND (target_gender = 'all' OR target_gender = ?)
    AND ? BETWEEN min_age AND max_age
    ORDER BY created_at DESC
    LIMIT 3
");
$stmt->execute([$article_id, $patient_gender, $patient_age]);
$related = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($article['title']); ?> — ApexCare</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../static/patient_sidebar.css">
    <link rel="stylesheet" href="../static/view_article.css">
</head>
<body>

<!-- Reading progress bar -->
<div class="read-progress-wrap">
    <div class="read-progress-bar" id="progressBar"></div>
</div>

<div class="layout">
    <?php include "../static/includes/patient_sidebar.php"; ?>

    <main class="content">

        <!-- ── Back nav ─────────────────────────────────── -->
        <div class="back-nav">
            <a href="patienthome.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Back to Home
            </a>
        </div>

        <!-- ── Article card ─────────────────────────────── -->
        <article class="article-full">

            <!-- Header -->
            <header class="article-header">
                <div class="article-eyebrow">
                    <i class="fas fa-user-check"></i>
                    Personalised for you
                </div>

                <h1><?php echo htmlspecialchars($article['title']); ?></h1>

                <div class="article-metadata">
                    <span class="meta-item">
                        <i class="far fa-calendar"></i>
                        <?php echo $publishDate; ?>
                    </span>
                    <span class="meta-item">
                        <i class="far fa-clock"></i>
                        <?php echo $readTime; ?> min read
                    </span>
                    <span class="meta-item">
                        <i class="fas fa-venus-mars"></i>
                        <?php echo $genderLabel; ?>
                    </span>
                    <span class="meta-item">
                        <i class="fas fa-people-group"></i>
                        Ages <?php echo $ageLabel; ?>
                    </span>
                </div>
            </header>

            <!-- Body -->
            <div class="article-body" id="articleBody">
                <?php echo renderArticle($article['content']); ?>
            </div>

            <!-- Footer -->
            <footer class="article-footer">
                <div class="article-actions">
                    <button class="action-btn" onclick="window.print()" title="Print article">
                        <i class="fas fa-print"></i>
                        Print
                    </button>
                    <button class="action-btn" id="saveBtn" title="Save for later">
                        <i class="far fa-bookmark"></i>
                        Save
                    </button>
                    <button class="action-btn" id="shareBtn" title="Share article">
                        <i class="far fa-share-from-square"></i>
                        Share
                    </button>
                </div>

                <div class="article-feedback" id="feedbackSection">
                    <span class="feedback-label">Was this helpful?</span>
                    <div class="feedback-buttons">
                        <button class="feedback-btn yes" id="feedYes" onclick="sendFeedback(1)">
                            <i class="far fa-thumbs-up"></i> Yes
                        </button>
                        <button class="feedback-btn no" id="feedNo" onclick="sendFeedback(0)">
                            <i class="far fa-thumbs-down"></i> No
                        </button>
                    </div>
                </div>
            </footer>

        </article>

        <!-- ── Related articles ─────────────────────────── -->
        <?php if (!empty($related)): ?>
        <section class="related-articles">
            <div class="related-heading">
                <div class="related-heading-icon"><i class="fas fa-newspaper"></i></div>
                <h2>You Might Also Like</h2>
            </div>
            <div class="related-grid">
                <?php foreach ($related as $rel): ?>
                <a href="view_article.php?id=<?php echo $rel['id']; ?>" class="related-card">
                    <h3><?php echo htmlspecialchars($rel['title']); ?></h3>
                    <p><?php echo htmlspecialchars($rel['preview']); ?>…</p>
                    <span class="read-more">
                        Read more <i class="fas fa-arrow-right"></i>
                    </span>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

    </main>
</div>

<!-- ── Share modal ─────────────────────────────────── -->
<div class="modal-backdrop" id="shareModal">
    <div class="modal-box">
        <div class="modal-top">
            <span class="modal-title">Share this article</span>
            <button class="modal-close" id="closeModal" aria-label="Close">
                <i class="fas fa-xmark"></i>
            </button>
        </div>
        <div class="share-options">
            <button class="share-option" onclick="shareVia('email')">
                <div class="share-icon email"><i class="fas fa-envelope"></i></div>
                Send via Email
            </button>
            <button class="share-option" onclick="shareVia('copy')">
                <div class="share-icon copy"><i class="fas fa-link"></i></div>
                Copy Link
            </button>
            <button class="share-option" onclick="shareVia('whatsapp')">
                <div class="share-icon whatsapp"><i class="fab fa-whatsapp"></i></div>
                Share on WhatsApp
            </button>
        </div>
    </div>
</div>

<!-- Copy toast -->
<div class="copy-toast" id="copyToast">
    <i class="fas fa-circle-check"></i>
    Link copied to clipboard!
</div>

<script>
    const articleTitle = <?php echo json_encode($article['title']); ?>;
    const articleUrl   = window.location.href;

    /* ── Reading progress bar ── */
    const progressBar = document.getElementById('progressBar');
    window.addEventListener('scroll', () => {
        const body    = document.body;
        const html    = document.documentElement;
        const total   = Math.max(body.scrollHeight, html.scrollHeight) - html.clientHeight;
        const pct     = total > 0 ? (window.scrollY / total) * 100 : 0;
        progressBar.style.width = pct.toFixed(1) + '%';
    });

    /* ── Save button toggle ── */
    const saveBtn = document.getElementById('saveBtn');
    let isSaved   = false;

    saveBtn.addEventListener('click', () => {
        isSaved = !isSaved;
        const icon = saveBtn.querySelector('i');
        if (isSaved) {
            icon.classList.replace('far', 'fas');
            saveBtn.classList.add('saved');
        } else {
            icon.classList.replace('fas', 'far');
            saveBtn.classList.remove('saved');
        }
    });

    /* ── Share modal ── */
    const shareModal = document.getElementById('shareModal');

    document.getElementById('shareBtn').addEventListener('click', () => {
        shareModal.classList.add('open');
        document.body.style.overflow = 'hidden';
    });

    document.getElementById('closeModal').addEventListener('click', closeShare);
    shareModal.addEventListener('click', e => { if (e.target === shareModal) closeShare(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeShare(); });

    function closeShare() {
        shareModal.classList.remove('open');
        document.body.style.overflow = '';
    }

    function shareVia(method) {
        if (method === 'email') {
            window.location.href = `mailto:?subject=${encodeURIComponent(articleTitle)}&body=${encodeURIComponent('Check out this health article: ' + articleUrl)}`;
            closeShare();
        } else if (method === 'copy') {
            navigator.clipboard.writeText(articleUrl).then(() => {
                closeShare();
                showToast();
            }).catch(() => {
                /* Fallback for older browsers */
                const ta = document.createElement('textarea');
                ta.value = articleUrl;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                closeShare();
                showToast();
            });
        } else if (method === 'whatsapp') {
            window.open(`https://wa.me/?text=${encodeURIComponent(articleTitle + '\n' + articleUrl)}`, '_blank', 'noopener');
            closeShare();
        }
    }

    function showToast() {
        const toast = document.getElementById('copyToast');
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 2800);
    }

    /* ── Feedback buttons ── */
    let feedbackGiven = false;

    function sendFeedback(helpful) {
        if (feedbackGiven) return;
        feedbackGiven = true;

        const yesBtn   = document.getElementById('feedYes');
        const noBtn    = document.getElementById('feedNo');
        const section  = document.getElementById('feedbackSection');

        if (helpful) {
            yesBtn.classList.add('active');
        } else {
            noBtn.classList.add('active');
        }

        /* Swap buttons for thank-you message after brief pause */
        setTimeout(() => {
            section.innerHTML = `
                <span class="feedback-label" style="color:var(--teal-dark);font-weight:700;">
                    <i class="fas fa-circle-check" style="color:var(--teal);margin-right:6px;"></i>
                    ${helpful ? 'Thanks for your feedback!' : "We'll work on improving this."}
                </span>`;
        }, 700);
    }
</script>

</body>
</html>