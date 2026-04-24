<?php
session_start();

$isLoggedIn = isset($_SESSION['user_id']);
$userRole   = $isLoggedIn ? $_SESSION['role'] : null;
$userName   = $isLoggedIn ? $_SESSION['first_name'] : null;

require_once "../db.php";

$contactSuccess = "";
$contactError = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["send_message"])) {

    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $message = trim($_POST["message"]);

    if (empty($name) || empty($email) || empty($message)) {
        $contactError = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $contactError = "Invalid email format.";
    } else {
        $userId = isset($_SESSION["user_id"]) ? $_SESSION["user_id"] : null;

        $stmt = $conn->prepare("
            INSERT INTO messages (user_id, name, email, message)
            VALUES (:uid, :name, :email, :message)
        ");

        $stmt->execute([
            "uid" => $userId,
            "name" => $name,
            "email" => $email,
            "message" => $message
        ]);

        $contactSuccess = "Message sent successfully!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>APEXCARE HOSPITAL - Modern Healthcare for All</title>

    <!-- Preconnect for fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts: Playfair Display + DM Sans -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="../static/mainwebsitehomepage.css">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../favicon.ico">
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <i class="fas fa-heartbeat"></i>
                <span>APEXCARE</span>
            </div>

            <!-- Mobile menu button -->
            <button class="mobile-menu-btn" onclick="toggleMobileMenu()">
                <i class="fas fa-bars"></i>
            </button>

            <ul class="nav-menu">
                <li><a href="#home" class="active">Home</a></li>
                <li><a href="#about">About Us</a></li>
                <li><a href="#services">Services</a></li>
                <li><a href="#contact">Contact</a></li>

                <?php if ($isLoggedIn): ?>
                    <?php
                    switch ($userRole) {
                        case 'patient':      $dashboard = "patienthome.php"; break;
                        case 'doctor':       $dashboard = "doctorhome.php"; break;
                        case 'receptionist': $dashboard = "receptionisthome.php"; break;
                        case 'admin':        $dashboard = "adminhome.php"; break;
                        default:             $dashboard = "home.php";
                    }
                    ?>
                    <li class="user-menu">
                        <a href="<?php echo $dashboard; ?>" class="dashboard-link">
                            <i class="fas fa-user-circle"></i>
                            <span><?php echo htmlspecialchars($userName); ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="logout.php" class="logout-link">
                            <i class="fas fa-sign-out-alt"></i>
                            <span class="hide-mobile">Logout</span>
                        </a>
                    </li>
                <?php else: ?>
                    <li>
                        <a href="login.php" class="login-btn">
                            <i class="fas fa-sign-in-alt"></i>
                            <span>Log in</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="hero-content">
            <div class="hero-eyebrow">Nairobi, Kenya &bull; Est. 2010</div>
            <h1 class="hero-title">APEX<span>CARE</span></h1>
            <p class="hero-subtitle">The modern choice for all your medical needs</p>
            <p class="hero-description">Experience world-class healthcare with compassionate doctors, seamless records management, and state-of-the-art facilities.</p>

            <?php if (!$isLoggedIn): ?>
                <div class="hero-buttons">
                    <a href="login.php" class="btn btn-primary"><i class="fas fa-arrow-right"></i> Get Started</a>
                    <a href="services.php" class="btn btn-outline">Learn More</a>
                </div>
            <?php else: ?>
                <div class="hero-buttons">
                    <a href="<?php echo $dashboard; ?>" class="btn btn-primary">
                        <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                    </a>
                </div>
            <?php endif; ?>

            <div class="hero-stats">
                <div class="stat-item">
                    <span class="stat-number">25+</span>
                    <span class="stat-label">Specialists</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number">5000+</span>
                    <span class="stat-label">Happy Patients</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number">15+</span>
                    <span class="stat-label">Years Experience</span>
                </div>
            </div>
        </div>

        <a href="#about" class="scroll-down">
            <i class="fas fa-chevron-down"></i>
        </a>
    </section>

    <!-- About Section -->
    <section class="about" id="about">
        <div class="container">
            <div class="section-header">
                <span class="section-subtitle">Who We Are</span>
                <h2 class="section-title">About ApexCare</h2>
                <div class="section-divider"></div>
            </div>

            <div class="about-grid">
                <div class="about-card mission-card">
                    <div class="card-icon">
                        <i class="fas fa-bullseye"></i>
                    </div>
                    <h3>Our Mission</h3>
                    <p>To provide modern, affordable, and compassionate healthcare for all our patients, ensuring quality treatment with the best medical practices.</p>
                    <ul class="feature-list">
                        <li><i class="fas fa-check-circle"></i> Patient-centered care</li>
                        <li><i class="fas fa-check-circle"></i> Affordable treatments</li>
                        <li><i class="fas fa-check-circle"></i> 24/7 emergency services</li>
                    </ul>
                </div>

                <div class="about-card specialize-card">
                    <div class="card-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <h3>What We Specialize In</h3>
                    <p>From emergency services to specialized treatments, we focus on delivering excellence in every area of healthcare.</p>
                    <ul class="feature-list">
                        <li><i class="fas fa-check-circle"></i> Cardiology</li>
                        <li><i class="fas fa-check-circle"></i> Neurology</li>
                        <li><i class="fas fa-check-circle"></i> Orthopedics</li>
                        <li><i class="fas fa-check-circle"></i> Pediatrics</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Why Choose Us Section -->
    <section class="why-choose">
        <div class="container">
            <div class="section-header light">
                <span class="section-subtitle">Why Us</span>
                <h2 class="section-title">Why Choose ApexCare?</h2>
                <div class="section-divider"></div>
            </div>

            <div class="reasons-grid">
                <div class="reason-card">
                    <div class="reason-image">
                        <img src="../1doctor.jpg" alt="Experienced Doctors" onerror="this.src='https://via.placeholder.com/500x320?text=Expert+Doctors'">
                    </div>
                    <div class="reason-content">
                        <div class="reason-badge"><i class="fas fa-user-md"></i> Our Team</div>
                        <h3>Expert Doctors</h3>
                        <p>Our team consists of highly qualified specialists with years of experience in their respective fields, committed to delivering evidence-based, personalized care.</p>
                    </div>
                </div>


                <div class="reason-card reverse">
                    <div class="reason-image">
                        <img src="../hospital bed.jpg" alt="Modern Facilities" onerror="this.src='https://via.placeholder.com/500x320?text=Modern+Facilities'">
                    </div>
                    <div class="reason-content">
                        <div class="reason-badge"><i class="fas fa-hospital"></i> Infrastructure</div>
                        <h3>Modern Facilities</h3>
                        <p>State-of-the-art medical equipment and comfortable patient rooms designed for the best care experience — because healing starts with the right environment.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section class="services" id="services">
        <div class="container">
            <div class="section-header">
                <span class="section-subtitle">What We Offer</span>
                <h2 class="section-title">Our Services</h2>
                <div class="section-divider"></div>
            </div>

            <div class="services-grid">
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <h3>Visual Care</h3>
                    <p>Comprehensive eye checkups and treatments with modern optometric equipment.</p>
                    <a href="#" class="service-link">Learn More <i class="fas fa-arrow-right"></i></a>
                </div>

                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-video"></i>
                    </div>
                    <h3>Online Consultation</h3>
                    <p>Connect with our doctors remotely through secure video consultations.</p>
                    <a href="#" class="service-link">Learn More <i class="fas fa-arrow-right"></i></a>
                </div>

                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h3>Book Appointment</h3>
                    <p>Schedule visits easily online with our streamlined booking system.</p>
                    <a href="#" class="service-link">Learn More <i class="fas fa-arrow-right"></i></a>
                </div>

                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-ambulance"></i>
                    </div>
                    <h3>Emergency Care</h3>
                    <p>24/7 emergency services with rapid response and critical care units.</p>
                    <a href="#" class="service-link">Learn More <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact" id="contact">
        <div class="container">
            <div class="section-header">
                <span class="section-subtitle">Get In Touch</span>
                <h2 class="section-title">Contact Us</h2>
                <div class="section-divider"></div>
            </div>

            <div class="contact-grid">
                <div class="contact-info">
                    <h3>Visit Our Facility</h3>

                    <div class="info-item">
                        <div class="icon-wrap"><i class="fas fa-map-marker-alt"></i></div>
                        <div>
                            <h4>Address</h4>
                            <p>Nairobi City, Kenya</p>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="icon-wrap"><i class="fas fa-phone-alt"></i></div>
                        <div>
                            <h4>Phone</h4>
                            <p>+254 716 918 427</p>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="icon-wrap"><i class="fas fa-envelope"></i></div>
                        <div>
                            <h4>Email</h4>
                            <p>apexcare@gmail.com</p>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="icon-wrap"><i class="fas fa-clock"></i></div>
                        <div>
                            <h4>Working Hours</h4>
                            <p>Mon – Fri: 8:00 AM – 8:00 PM</p>
                            <p>Saturday: 9:00 AM – 5:00 PM</p>
                            <p>Sunday: 10:00 AM – 2:00 PM</p>
                        </div>
                    </div>

                    <div class="social-links">
                        <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>

                <div class="contact-form-container">
                    <?php if ($contactSuccess): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?php echo htmlspecialchars($contactSuccess); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($contactError): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo htmlspecialchars($contactError); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="contact-form">
                        <h3>Send us a message</h3>

                        <div class="form-group">
                            <input type="text" name="name" placeholder="Your Full Name" required>
                            <i class="fas fa-user"></i>
                        </div>

                        <div class="form-group">
                            <input type="email" name="email" placeholder="Your Email Address" required>
                            <i class="fas fa-envelope"></i>
                        </div>

                        <div class="form-group">
                            <textarea name="message" placeholder="Your Message" rows="5" required></textarea>
                            <i class="fas fa-pencil-alt"></i>
                        </div>

                        <button type="submit" name="send_message" class="btn-submit">
                            <i class="fas fa-paper-plane"></i>
                            Send Message
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-col">
                    <div class="footer-logo">
                        <i class="fas fa-heartbeat"></i>
                        <span>APEXCARE</span>
                    </div>
                    <p>Your health is our priority. We're committed to providing exceptional healthcare services with compassion, expertise, and seamless digital health records management.</p>
                </div>

                <div class="footer-col">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="#home">Home</a></li>
                        <li><a href="#about">About Us</a></li>
                        <li><a href="#services">Services</a></li>
                        <li><a href="#contact">Contact</a></li>
                    </ul>
                </div>

                <div class="footer-col">
                    <h4>Our Services</h4>
                    <ul>
                        <li><a href="#">Visual Care</a></li>
                        <li><a href="#">Online Consultation</a></li>
                        <li><a href="#">Book Appointment</a></li>
                        <li><a href="#">Emergency Care</a></li>
                    </ul>
                </div>

                <div class="footer-col">
                    <h4>Newsletter</h4>
                    <p>Subscribe for health tips and hospital updates.</p>
                    <form class="newsletter-form">
                        <div class="newsletter-input-wrap">
                            <input type="email" placeholder="Your email address">
                            <button type="submit"><i class="fas fa-paper-plane"></i></button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; 2025 ApexCare Hospital. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- JavaScript -->
    <script>
        function toggleMobileMenu() {
            document.querySelector('.nav-menu').classList.toggle('active');
            const icon = document.querySelector('.mobile-menu-btn i');
            icon.classList.toggle('fa-bars');
            icon.classList.toggle('fa-times');
        }

        document.querySelectorAll('.nav-menu a').forEach(link => {
            link.addEventListener('click', () => {
                document.querySelector('.nav-menu').classList.remove('active');
                const icon = document.querySelector('.mobile-menu-btn i');
                icon.classList.add('fa-bars');
                icon.classList.remove('fa-times');
            });
        });

        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });

        // Navbar scroll effect
        window.addEventListener('scroll', () => {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 60) {
                navbar.style.borderBottomColor = 'rgba(26,158,154,0.2)';
            } else {
                navbar.style.borderBottomColor = 'rgba(255,255,255,0.07)';
            }
        });
    </script>
</body>
</html>