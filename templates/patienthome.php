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

$patient_id = $_SESSION['user_id'];

/* -------------------------
   FETCH PATIENT DATA
--------------------------*/
$stmt = $conn->prepare("
    SELECT first_name, gender, date_of_birth
    FROM users
    WHERE id = ?
");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$patient_name   = htmlspecialchars($patient['first_name']);
$patient_gender = strtolower($patient['gender']);

$dob         = new DateTime($patient['date_of_birth']);
$today       = new DateTime();
$patient_age = $today->diff($dob)->y;

/* Store first name in session for sidebar avatar */
$_SESSION['first_name'] = $patient['first_name'];

/* -------------------------
   TIME-BASED GREETING
--------------------------*/
$hour     = (int) date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$greetIcon = $hour < 12 ? 'sun' : ($hour < 17 ? 'cloud-sun' : 'moon');

/* -------------------------
   PERSONALISED ARTICLES
--------------------------*/
$stmt = $conn->prepare("
    SELECT * FROM articles
    WHERE status = 'published'
    AND (target_gender = 'all' OR target_gender = ?)
    AND ? BETWEEN min_age AND max_age
    ORDER BY created_at DESC
");
$stmt->execute([$patient_gender, $patient_age]);
$articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home — ApexCare Patient Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../static/patient_sidebar.css">
    <link rel="stylesheet" href="../static/phomepage.css">
</head>
<body>

<div class="layout">

    <?php include "../static/includes/patient_sidebar.php"; ?>

    <main class="content">

        <!-- ── Welcome Hero ─────────────────────────── -->
        <div class="welcome-hero">
            <div class="welcome-left">
                <div class="welcome-greeting">
                    <i class="fas fa-<?php echo $greetIcon; ?>"></i>
                    <?php echo $greeting; ?>
                </div>
                <h1>Welcome back, <span><?php echo $patient_name; ?></span> 👋</h1>
                <div class="welcome-meta">
                    <span class="welcome-chip">
                        <i class="fas fa-cake-candles"></i>
                        <?php echo $patient_age; ?> years old
                    </span>
                    <span class="welcome-chip">
                        <i class="fas fa-venus-mars"></i>
                        <?php echo ucfirst($patient_gender); ?>
                    </span>
                    <span class="welcome-chip">
                        <i class="far fa-calendar"></i>
                        <?php echo date('d M Y'); ?>
                    </span>
                </div>
            </div>
            <div class="welcome-right">
                <a href="patient_book.php" class="btn-book">
                    <i class="fas fa-calendar-plus"></i>
                    Book Appointment
                </a>
            </div>
        </div>

        <!-- ── Health Articles ───────────────────────── -->
        <div class="articles-section">
            <div class="section-heading">
                <div class="section-heading-left">
                    <span class="section-icon gold"><i class="fas fa-newspaper"></i></span>
                    <h2>Health Articles For You</h2>
                </div>
                <span class="section-count"><?php echo count($articles); ?> articles</span>
            </div>

            <?php if (count($articles) > 0): ?>
                <div class="articles-grid">
                    <?php foreach ($articles as $article): ?>
                        <a href="view_article.php?id=<?php echo $article['id']; ?>" class="article-card-link">
                            <article class="article-card">
                                <div class="article-tag">
                                    <i class="fas fa-user-check"></i>
                                    Personalised
                                </div>
                                <h3><?php echo htmlspecialchars($article['title']); ?></h3>
                                <p>
                                    <?php echo htmlspecialchars(substr(strip_tags($article['content']), 0, 165)); ?>…
                                </p>
                                <div class="article-meta">
                                    <span class="read-time">
                                        <i class="far fa-clock"></i>
                                        <?php echo max(1, ceil(str_word_count($article['content']) / 200)); ?> min read
                                    </span>
                                    <span class="continue-reading">
                                        Read more
                                        <i class="fas fa-arrow-right"></i>
                                    </span>
                                </div>
                            </article>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-articles">
                    <div class="no-articles-icon"><i class="fas fa-newspaper"></i></div>
                    <p>No personalised articles available right now — check back soon.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- ── Hospital Updates + Calculators ─────────── -->
        <div class="bottom-grid">

            <!-- Hospital Updates -->
            <div class="info-card">
                <div class="info-card-head">
                    <div class="info-card-head-left">
                        <span class="section-icon teal"><i class="fas fa-hospital"></i></span>
                        <div>
                            <div class="info-card-title">Hospital Updates</div>
                            <div class="info-card-sub">Latest promotions &amp; announcements</div>
                        </div>
                    </div>
                </div>
                <div class="info-card-body">
                    <div class="promo-block">
                        <div class="promo-img-wrap">
                            <img src="../discount.jpg" alt="Discounted Checkups">
                        </div>
                        <div>
                            <div class="promo-badge">
                                <i class="fas fa-tag"></i>
                                20% OFF
                            </div>
                            <p class="promo-text">Get 20% off all general checkups this week! Book your appointment today.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Health Calculators -->
            <div class="info-card">
                <div class="info-card-head">
                    <div class="info-card-head-left">
                        <span class="section-icon navy"><i class="fas fa-calculator"></i></span>
                        <div>
                            <div class="info-card-title">Health Calculators</div>
                            <div class="info-card-sub">Useful online tools</div>
                        </div>
                    </div>
                </div>
                <div class="info-card-body">
                    <div class="calc-list">
                        <a href="https://www.calculator.net/bmi-calculator.html" target="_blank" rel="noopener" class="calc-btn">
                            <div class="calc-icon"><i class="fas fa-weight-scale"></i></div>
                            <span class="calc-label">BMI Calculator</span>
                            <i class="fas fa-arrow-up-right-from-square calc-arrow"></i>
                        </a>
                        <a href="https://www.webmd.com/diet/healthtool-food-calorie-counter" target="_blank" rel="noopener" class="calc-btn">
                            <div class="calc-icon"><i class="fas fa-fire-flame-curved"></i></div>
                            <span class="calc-label">Calories Counter</span>
                            <i class="fas fa-arrow-up-right-from-square calc-arrow"></i>
                        </a>
                        <a href="https://periodtracker.website/" target="_blank" rel="noopener" class="calc-btn">
                            <div class="calc-icon"><i class="fas fa-circle-nodes"></i></div>
                            <span class="calc-label">Period Tracker</span>
                            <i class="fas fa-arrow-up-right-from-square calc-arrow"></i>
                        </a>
                    </div>
                </div>
            </div>

        </div>

    </main>
</div>

</body>
</html>