<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "receptionist") {
    header("Location: login.php");
    exit();
}

/* ── DELETE ─────────────────────────────────────── */
if (isset($_GET["delete"])) {
    $id = intval($_GET["delete"]);
    $conn->prepare("DELETE FROM articles WHERE id = ?")->execute([$id]);
    header("Location: receptionist_articles.php?msg=deleted");
    exit();
}

/* ── TOGGLE STATUS ───────────────────────────────── */
if (isset($_GET["toggle"])) {
    $id = intval($_GET["toggle"]);
    $conn->prepare("
        UPDATE articles
        SET status = IF(status='published','draft','published')
        WHERE id = ?
    ")->execute([$id]);
    header("Location: receptionist_articles.php?msg=toggled");
    exit();
}

/* ── FETCH ALL ARTICLES ──────────────────────────── */
$articles      = $conn->query("SELECT * FROM articles ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$totalArticles = count($articles);

$publishedCount = 0; $draftCount = 0; $maleCount = 0; $femaleCount = 0;
foreach ($articles as $a) {
    if ($a['status'] === 'published') $publishedCount++; else $draftCount++;
    if ($a['target_gender'] === 'male')   $maleCount++;
    elseif ($a['target_gender'] === 'female') $femaleCount++;
}

/* ── TOAST MESSAGE ───────────────────────────────── */
$toastMsg = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'deleted') $toastMsg = 'Article deleted successfully.';
    if ($_GET['msg'] === 'toggled') $toastMsg = 'Article status updated.';
    if ($_GET['msg'] === 'created') $toastMsg = 'New article created and saved!';
    if ($_GET['msg'] === 'updated') $toastMsg = 'Article updated successfully.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Articles — ApexCare</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../static/receptionist_sidebar.css">
    <link rel="stylesheet" href="../static/receptionist.css">
    <link rel="stylesheet" href="../static/receptionist_articles.css">
</head>
<body>

<div class="layout">
    <?php include "../static/includes/receptionist_sidebar.php"; ?>

    <main class="content">

        <!-- ── Page Header ─────────────────────────────── -->
        <div class="page-header">
            <div class="page-header-left">
                <h1>
                    <span class="r-header-icon"><i class="fas fa-newspaper"></i></span>
                    Manage Articles
                </h1>
                <p class="page-header-sub">Create and manage health articles shown to patients</p>
            </div>
            <div class="header-badge">
                <i class="fas fa-calendar-day"></i>
                <?php echo date('d M Y'); ?>
            </div>
        </div>

        <!-- ── Toast ───────────────────────────────────── -->
        <?php if ($toastMsg): ?>
            <div class="action-toast" id="actionToast">
                <i class="fas fa-circle-check"></i>
                <?php echo htmlspecialchars($toastMsg); ?>
            </div>
        <?php endif; ?>

        <!-- ── Stats ───────────────────────────────────── -->
        <div class="stats-row">
            <div class="stat-card amber">
                <div class="stat-icon amber"><i class="fas fa-newspaper"></i></div>
                <div>
                    <div class="stat-value"><?php echo $totalArticles; ?></div>
                    <div class="stat-label">Total</div>
                </div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon green"><i class="fas fa-eye"></i></div>
                <div>
                    <div class="stat-value"><?php echo $publishedCount; ?></div>
                    <div class="stat-label">Published</div>
                </div>
            </div>
            <div class="stat-card slate">
                <div class="stat-icon slate"><i class="fas fa-pen-to-square"></i></div>
                <div>
                    <div class="stat-value"><?php echo $draftCount; ?></div>
                    <div class="stat-label">Drafts</div>
                </div>
            </div>
            <div class="stat-card purple">
                <div class="stat-icon purple"><i class="fas fa-venus-mars"></i></div>
                <div>
                    <div class="stat-value"><?php echo $maleCount; ?>M&nbsp;/&nbsp;<?php echo $femaleCount; ?>F</div>
                    <div class="stat-label">Gender Split</div>
                </div>
            </div>
        </div>

        <!-- ── Toolbar ─────────────────────────────────── -->
        <div class="toolbar">
            <div class="search-wrap">
                <i class="fas fa-magnifying-glass search-icon"></i>
                <input
                    type="text"
                    class="search-input"
                    id="searchInput"
                    placeholder="Search by title…"
                    oninput="filterTable()"
                >
            </div>

            <select class="filter-select" id="statusFilter" onchange="filterTable()">
                <option value="">All statuses</option>
                <option value="published">Published</option>
                <option value="draft">Draft</option>
            </select>

            <select class="filter-select" id="genderFilter" onchange="filterTable()">
                <option value="">All genders</option>
                <option value="all">All genders</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
            </select>

            <span class="result-count">
                Showing <span id="visibleCount"><?php echo $totalArticles; ?></span> of <?php echo $totalArticles; ?>
            </span>

            <a href="add_articles.php" class="btn-create">
                <i class="fas fa-plus-circle"></i>
                New Article
            </a>
        </div>

        <!-- ── Articles Table ──────────────────────────── -->
        <div class="table-card">
            <div class="table-card-head">
                <div class="table-card-head-left">
                    <span class="table-icon"><i class="fas fa-table-list"></i></span>
                    <div>
                        <div class="table-card-title">Article Library</div>
                        <div class="table-card-sub">articles · healthrecord_db</div>
                    </div>
                </div>
            </div>

            <?php if ($totalArticles > 0): ?>

            <table class="articles-table" id="articlesTable">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Gender</th>
                        <th>Age Range</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php foreach ($articles as $article):
                        $genderVal  = $article['target_gender'];
                        $statusVal  = $article['status'];
                        $genderIcon = $genderVal === 'male' ? 'mars' : ($genderVal === 'female' ? 'venus' : 'venus-mars');
                        $preview    = substr(strip_tags($article['content']), 0, 80);
                    ?>
                    <tr data-title="<?php echo strtolower(htmlspecialchars($article['title'])); ?>"
                        data-status="<?php echo $statusVal; ?>"
                        data-gender="<?php echo $genderVal; ?>">

                        <td>
                            <div class="title-cell">
                                <div class="title-icon"><i class="fas fa-file-medical"></i></div>
                                <div>
                                    <div class="title-text"><?php echo htmlspecialchars($article['title']); ?></div>
                                    <div class="title-preview"><?php echo htmlspecialchars($preview); ?>…</div>
                                </div>
                            </div>
                        </td>

                        <td>
                            <span class="gender-badge <?php echo $genderVal; ?>">
                                <i class="fas fa-<?php echo $genderIcon; ?>"></i>
                                <?php echo ucfirst($genderVal); ?>
                            </span>
                        </td>

                        <td>
                            <span class="age-chip">
                                <?php echo $article['min_age']; ?>–<?php echo $article['max_age']; ?> yrs
                            </span>
                        </td>

                        <td>
                            <span class="status-badge <?php echo $statusVal; ?>">
                                <i class="fas fa-<?php echo $statusVal === 'published' ? 'eye' : 'eye-slash'; ?>"></i>
                                <?php echo ucfirst($statusVal); ?>
                            </span>
                        </td>

                        <td>
                            <div class="action-cell">
                                <a href="edit_article.php?id=<?php echo $article['id']; ?>"
                                   class="btn-art edit">
                                    <i class="fas fa-pen"></i> Edit
                                </a>
                                <a href="?toggle=<?php echo $article['id']; ?>"
                                   class="btn-art <?php echo $statusVal === 'published' ? 'toggle-draft' : 'toggle-pub'; ?>">
                                    <i class="fas fa-<?php echo $statusVal === 'published' ? 'eye-slash' : 'eye'; ?>"></i>
                                    <?php echo $statusVal === 'published' ? 'Unpublish' : 'Publish'; ?>
                                </a>
                                <button
                                    class="btn-art del"
                                    onclick="confirmDelete(<?php echo $article['id']; ?>, '<?php echo addslashes(htmlspecialchars($article['title'])); ?>')">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div id="noResults">
                <div class="empty-icon" style="width:48px;height:48px;font-size:20px;">
                    <i class="fas fa-magnifying-glass"></i>
                </div>
                <p style="font-size:13.5px;font-weight:600;color:var(--text-muted);">No articles match your filters.</p>
            </div>

            <div class="table-footer">
                <span class="table-footer-info">
                    <i class="fas fa-newspaper"></i>
                    <span id="footerCount"><?php echo $totalArticles; ?></span> article<?php echo $totalArticles !== 1 ? 's' : ''; ?> total
                </span>
                <span class="table-footer-info">
                    <i class="fas fa-circle-check"></i>
                    healthrecord_db · articles
                </span>
            </div>

            <?php else: ?>

            <div class="empty-state">
                <div class="empty-icon"><i class="fas fa-newspaper"></i></div>
                <h3>No articles yet</h3>
                <p>Create your first health article to start sharing content with patients.</p>
                <a href="add_articles.php" class="btn-create" style="margin-top:4px;">
                    <i class="fas fa-plus-circle"></i>
                    Create First Article
                </a>
            </div>

            <?php endif; ?>
        </div>

    </main>
</div>

<!-- ── Delete Confirmation Modal ─────────────────── -->
<div class="modal-backdrop" id="deleteModal">
    <div class="modal-box">
        <div class="modal-icon"><i class="fas fa-trash"></i></div>
        <h3>Delete Article?</h3>
        <p id="deleteModalText">This action cannot be undone.</p>
        <div class="modal-actions">
            <button class="btn-modal-cancel" onclick="closeModal()">Cancel</button>
            <a href="#" class="btn-modal-delete" id="confirmDeleteLink">
                <i class="fas fa-trash"></i> Delete
            </a>
        </div>
    </div>
</div>

<script>
    /* ── Delete modal ── */
    function confirmDelete(id, title) {
        document.getElementById('deleteModalText').textContent =
            'Are you sure you want to delete "' + title + '"? This cannot be undone.';
        document.getElementById('confirmDeleteLink').href = '?delete=' + id;
        document.getElementById('deleteModal').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        document.getElementById('deleteModal').classList.remove('open');
        document.body.style.overflow = '';
    }

    document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

    /* ── Auto-dismiss toast ── */
    const toast = document.getElementById('actionToast');
    if (toast) {
        setTimeout(() => {
            toast.style.transition = 'opacity 0.5s ease';
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 500);
        }, 5000);
    }

    /* ── Live filter ── */
    const rows  = document.querySelectorAll('#tableBody tr');
    const total = rows.length;

    function filterTable() {
        const search = document.getElementById('searchInput').value.toLowerCase().trim();
        const status = document.getElementById('statusFilter').value;
        const gender = document.getElementById('genderFilter').value;
        let visible  = 0;

        rows.forEach(row => {
            const matchTitle  = !search || row.dataset.title.includes(search);
            const matchStatus = !status || row.dataset.status === status;
            const matchGender = !gender || row.dataset.gender === gender;
            const show = matchTitle && matchStatus && matchGender;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        document.getElementById('visibleCount').textContent = visible;
        document.getElementById('footerCount').textContent  = visible;
        const noResults = document.getElementById('noResults');
        noResults.classList.toggle('show', visible === 0 && total > 0);
    }
</script>

</body>
</html>