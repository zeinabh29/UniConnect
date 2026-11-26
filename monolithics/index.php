<?php
/**
 * UniConnect - Monolithic University Management System
 * Public Portal Gateway & University Landing Page
 */

require_once __DIR__ . '/config/config.php';

$contactSuccess = false;
$contactError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'contact') {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $csrf    = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf)) {
        $contactError = 'Security token expired. Please try again.';
    } elseif (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $contactError = 'Please fill in all contact form fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $contactError = 'Please enter a valid email address.';
    } else {
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare("
                INSERT INTO contact_messages (name, email, subject, message, is_read, created_at)
                VALUES (?, ?, ?, ?, 0, NOW())
            ");
            $stmt->execute([$name, $email, $subject, $message]);

            // Notify Administrator
            $adminId = $pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetchColumn();
            if ($adminId) {
                add_notification(
                    $pdo,
                    $adminId,
                    'New Contact Inquiry',
                    "Message received from {$name} ({$email}): {$subject}",
                    'info',
                    'admin/messages.php'
                );
            }

            $contactSuccess = true;
        } catch (Exception $e) {
            $contactError = 'Failed to submit inquiry: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> &mdash; University Management Portal</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            --brand-primary: #0f4c81;
            --brand-dark: #0f172a;
            --brand-accent: #2563eb;
        }
        body {
            font-family: 'Inter', sans-serif;
            color: #334155;
            background-color: #ffffff;
        }
        .hero-section {
            background: linear-gradient(135deg, #0f172a 0%, #0f4c81 60%, #1e3a8a 100%);
            color: #ffffff;
            padding: 100px 0 80px;
            position: relative;
            overflow: hidden;
        }
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: radial-gradient(circle at 80% 20%, rgba(37, 99, 235, 0.25) 0%, transparent 60%);
            pointer-events: none;
        }
        .navbar-landing {
            background: #ffffff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 15px 0;
        }
        .feature-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 30px;
            background: #ffffff;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            height: 100%;
        }
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.08);
            border-color: #cbd5e1;
        }
        .feature-icon {
            width: 52px;
            height: 52px;
            border-radius: 10px;
            background: #eff6ff;
            color: #2563eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 20px;
        }
        .stat-badge-box {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }
        .dept-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 25px;
            background: #ffffff;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
        }
    </style>
</head>
<body>

<!-- Navigation Header -->
<nav class="navbar navbar-expand-lg navbar-landing sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2 fw-bold text-dark fs-4" href="index.php">
            <img src="<?= BASE_URL ?>assets/img/logo.svg" alt="UniConnect Logo" width="36" height="36">
            <span><?= APP_NAME ?></span>
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navContent" aria-controls="navContent" aria-expanded="false" aria-label="Toggle navigation">
            <i class="fa-solid fa-bars"></i>
        </button>

        <div class="collapse navbar-collapse" id="navContent">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center gap-2">
                <li class="nav-item"><a class="nav-link text-secondary fw-semibold" href="#about">About</a></li>
                <li class="nav-item"><a class="nav-link text-secondary fw-semibold" href="#features">Features</a></li>
                <li class="nav-item"><a class="nav-link text-secondary fw-semibold" href="#departments">Departments</a></li>
                <li class="nav-item"><a class="nav-link text-secondary fw-semibold" href="#contact">Contact</a></li>
                <li class="nav-item ms-lg-2">
                    <a href="<?= BASE_URL ?>auth/login.php" class="btn btn-outline-primary fw-semibold px-3">
                        <i class="fa-solid fa-right-to-bracket me-1"></i> Sign In
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>auth/register.php" class="btn btn-primary fw-semibold px-3">
                        <i class="fa-solid fa-user-plus me-1"></i> Register
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container position-relative">
        <div class="row align-items-center gy-5">
            <div class="col-lg-7">
                <h1 class="display-4 fw-bold text-white mb-3" style="letter-spacing: -1px; line-height: 1.15;">
                    Empowering Higher Education Through Unified Technology
                </h1>
                <p class="lead text-white-50 mb-4" style="max-width: 620px;">
                    UniConnect integrates student enrollment, course curricula, grade evaluations, and official verified document issuance into one unified university portal.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="<?= BASE_URL ?>auth/login.php" class="btn btn-primary btn-lg px-4 fw-semibold shadow">
                        <i class="fa-solid fa-door-open me-2"></i> Enter Student Portal
                    </a>
                    <a href="<?= BASE_URL ?>auth/register.php" class="btn btn-outline-light btn-lg px-4 fw-semibold">
                        <i class="fa-solid fa-user-graduate me-2"></i> New Student Sign Up
                    </a>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="stat-badge-box">
                            <div class="fs-1 fw-bold text-white mb-1">5,400+</div>
                            <div class="small text-white-50">Active Students</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-badge-box">
                            <div class="fs-1 fw-bold text-white mb-1">120+</div>
                            <div class="small text-white-50">Curriculum Courses</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-badge-box">
                            <div class="fs-1 fw-bold text-white mb-1">98.4%</div>
                            <div class="small text-white-50">Graduation Success</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-badge-box">
                            <div class="fs-1 fw-bold text-white mb-1">24/7</div>
                            <div class="small text-white-50">Online Portal Access</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- About Section -->
<section class="py-5 bg-light" id="about">
    <div class="container py-4">
        <div class="row align-items-center gy-4">
            <div class="col-lg-6">
                <span class="text-primary fw-bold text-uppercase small tracking-wide">About UniConnect University</span>
                <h2 class="fw-bold text-dark mt-1 mb-3">A Connected Campus Built for Academic Excellence</h2>
                <p class="text-secondary">
                    UniConnect brings students, faculty, and academic administration together in a unified digital campus. From seamless course registration and academic advising to official transcripts and verified university certificates, every stage of your academic journey is supported in one place.
                </p>
                <div class="row g-3 mt-2">
                    <div class="col-sm-6">
                        <div class="d-flex align-items-start gap-2">
                            <i class="fa-solid fa-circle-check text-success mt-1"></i>
                            <div>
                                <h6 class="fw-bold mb-1 text-dark">Streamlined Registration</h6>
                                <p class="small text-muted mb-0">Browse degree courses, check seat availability, and manage schedules online.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="d-flex align-items-start gap-2">
                            <i class="fa-solid fa-circle-check text-success mt-1"></i>
                            <div>
                                <h6 class="fw-bold mb-1 text-dark">Dedicated Student Support</h6>
                                <p class="small text-muted mb-0">Direct access to academic records, registrar requests, and campus advisors.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="p-4 bg-white rounded-4 border shadow-sm">
                    <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-building-columns text-primary me-2"></i>University Highlights</h5>
                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                            <span>Accredited Academic Departments</span>
                            <span class="badge bg-primary-subtle text-primary">4 Core Disciplines</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                            <span>Official Transcript &amp; Credentials</span>
                            <span class="badge bg-success-subtle text-success">Verified Issuance</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                            <span>Registrar &amp; Advising Hours</span>
                            <span class="badge bg-info-subtle text-info-emphasis">Mon &ndash; Fri, 8AM &ndash; 5PM</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                            <span>Student Self-Service Portal</span>
                            <span class="badge bg-dark text-white">Available 24/7</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Key System Features -->
<section class="py-5" id="features">
    <div class="container py-4">
        <div class="text-center mb-5">
            <span class="text-primary fw-bold text-uppercase small">Functional Modules</span>
            <h2 class="fw-bold text-dark mt-1">Everything an Academic Institution Requires</h2>
            <p class="text-muted" style="max-width: 600px; margin: 0 auto;">Streamlining everyday academic governance for university staff, faculty, and undergraduates.</p>
        </div>

        <div class="row g-4">
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fa-solid fa-user-lock"></i></div>
                    <h5 class="fw-bold text-dark mb-2">Authentication & Security</h5>
                    <p class="text-muted small mb-0">Bcrypt password hashing, CSRF session protection, and strict role-based access control guarding administrative actions.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fa-solid fa-graduation-cap"></i></div>
                    <h5 class="fw-bold text-dark mb-2">Student Records Management</h5>
                    <p class="text-muted small mb-0">Comprehensive administrative directory with live search, department filters, student enrollment, and full CRUD operations.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fa-solid fa-book-open-reader"></i></div>
                    <h5 class="fw-bold text-dark mb-2">Curriculum & Course Catalog</h5>
                    <p class="text-muted small mb-0">Credit management, class capacity limits, duplicate registration prevention, and self-service student enrollment.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fa-solid fa-award"></i></div>
                    <h5 class="fw-bold text-dark mb-2">Automated Grading & GPA</h5>
                    <p class="text-muted small mb-0">Weighted assessment formula (Assignments 20%, Midterm 30%, Final 50%) with automatic letter grade and GPA computation.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fa-solid fa-file-shield"></i></div>
                    <h5 class="fw-bold text-dark mb-2">Verified Certificate Issuance</h5>
                    <p class="text-muted small mb-0">Students request official transcripts and verification documents; administrators review, endorse, and issue printable credentials.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fa-solid fa-bell"></i></div>
                    <h5 class="fw-bold text-dark mb-2">Activity Notification Center</h5>
                    <p class="text-muted small mb-0">Automatic alerts notifying students and administrators of enrollment changes, posted grades, and certificate status updates.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Departments Section -->
<section class="py-5 bg-light" id="departments">
    <div class="container py-4">
        <div class="text-center mb-5">
            <span class="text-primary fw-bold text-uppercase small">Academic Disciplines</span>
            <h2 class="fw-bold text-dark mt-1">Colleges & Departments</h2>
            <p class="text-muted">Rigorous undergraduate engineering and computational sciences</p>
        </div>

        <div class="row g-4">
            <div class="col-md-6 col-lg-3">
                <div class="dept-card h-100">
                    <div class="fw-bold text-primary mb-1"><i class="fa-solid fa-laptop-code me-2"></i>Computer Science</div>
                    <p class="text-muted small mb-0">Algorithms, systems programming, artificial intelligence, and software theory.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="dept-card h-100">
                    <div class="fw-bold text-primary mb-1"><i class="fa-solid fa-code-branch me-2"></i>Software Engineering</div>
                    <p class="text-muted small mb-0">Full-stack web architectures, agile methodologies, and enterprise development.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="dept-card h-100">
                    <div class="fw-bold text-primary mb-1"><i class="fa-solid fa-network-wired me-2"></i>Information Technology</div>
                    <p class="text-muted small mb-0">Network security, cyber defense, distributed computing, and cloud systems.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="dept-card h-100">
                    <div class="fw-bold text-primary mb-1"><i class="fa-solid fa-chart-pie me-2"></i>Data Science</div>
                    <p class="text-muted small mb-0">Statistical modeling, predictive analytics, big data, and machine learning.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Contact Form Section -->
<section class="py-5" id="contact">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card-academic p-4 p-md-5">
                    <div class="text-center mb-4">
                        <span class="text-primary fw-bold text-uppercase small">Get in Touch</span>
                        <h3 class="fw-bold text-dark mt-1">Contact University Administration</h3>
                        <p class="text-muted small">Have an inquiry regarding admissions, transfer credits, or campus visits? Send us a message.</p>
                    </div>

                    <?php if ($contactSuccess): ?>
                        <div class="alert alert-success d-flex align-items-center mb-4">
                            <i class="fa-solid fa-circle-check fs-4 me-3"></i>
                            <div>
                                <strong>Inquiry Received!</strong> Thank you for contacting UniConnect. Your message has been logged in the university inquiries database.
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($contactError)): ?>
                        <div class="alert alert-danger d-flex align-items-center mb-4">
                            <i class="fa-solid fa-circle-exclamation fs-4 me-3"></i>
                            <div><?= e($contactError) ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="#contact">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="contact">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="contact_name" class="form-label small fw-semibold text-secondary">Your Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="contact_name" name="name" placeholder="Johnathan Smith" required>
                            </div>
                            <div class="col-md-6">
                                <label for="contact_email" class="form-label small fw-semibold text-secondary">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="contact_email" name="email" placeholder="john@example.com" required>
                            </div>
                            <div class="col-12">
                                <label for="contact_subject" class="form-label small fw-semibold text-secondary">Subject <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="contact_subject" name="subject" placeholder="e.g. Question regarding Transfer Admissions" required>
                            </div>
                            <div class="col-12">
                                <label for="contact_message" class="form-label small fw-semibold text-secondary">Message <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="contact_message" name="message" rows="4" placeholder="Write your message or inquiry here..." required></textarea>
                            </div>
                            <div class="col-12 text-center mt-4">
                                <button type="submit" class="btn btn-primary btn-lg px-5 fw-semibold">
                                    <i class="fa-solid fa-paper-plane me-2"></i> Send Inquiry
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="bg-dark text-white py-5">
    <div class="container">
        <div class="row gy-4">
            <div class="col-lg-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <img src="<?= BASE_URL ?>assets/img/logo.svg" alt="UniConnect Crest" width="32" height="32">
                    <h5 class="fw-bold mb-0 text-white"><?= APP_NAME ?></h5>
                </div>
                <p class="text-white-50 small mb-3">
                    A comprehensive university portal dedicated to academic excellence, streamlined student services, and responsive registrar administration.
                </p>
                <div class="text-white-50 small">
                    <i class="fa-solid fa-location-dot me-2"></i> 100 University Boulevard, Academic City
                </div>
            </div>

            <div class="col-sm-6 col-lg-4">
                <h6 class="fw-bold text-white mb-3">Quick Navigation</h6>
                <ul class="list-unstyled small text-white-50">
                    <li class="mb-2"><a href="#about" class="text-white-50 text-decoration-none"><i class="fa-solid fa-angle-right me-1"></i> About University</a></li>
                    <li class="mb-2"><a href="#departments" class="text-white-50 text-decoration-none"><i class="fa-solid fa-angle-right me-1"></i> Academic Departments</a></li>
                    <li class="mb-2"><a href="<?= BASE_URL ?>auth/login.php" class="text-white-50 text-decoration-none"><i class="fa-solid fa-angle-right me-1"></i> Sign In to Portal</a></li>
                    <li class="mb-2"><a href="<?= BASE_URL ?>auth/register.php" class="text-white-50 text-decoration-none"><i class="fa-solid fa-angle-right me-1"></i> Student Registration</a></li>
                    <li class="mb-2"><a href="#contact" class="text-white-50 text-decoration-none"><i class="fa-solid fa-angle-right me-1"></i> Contact Admissions</a></li>
                </ul>
            </div>

            <div class="col-sm-6 col-lg-4">
                <h6 class="fw-bold text-white mb-3">Campus Offices &amp; Support</h6>
                <ul class="list-unstyled small text-white-50">
                    <li class="mb-2"><i class="fa-solid fa-envelope me-2 text-primary"></i> admissions@uniconnect.edu</li>
                    <li class="mb-2"><i class="fa-solid fa-file-signature me-2 text-primary"></i> registrar@uniconnect.edu</li>
                    <li class="mb-2"><i class="fa-solid fa-phone me-2 text-primary"></i> +1 (555) 100-2025</li>
                    <li class="mb-0"><i class="fa-regular fa-clock me-2 text-primary"></i> Mon &ndash; Fri: 8:00 AM &ndash; 5:00 PM</li>
                </ul>
            </div>
        </div>

        <div class="border-top border-secondary mt-5 pt-4 text-center small text-white-50">
            &copy; <?= date('Y') ?> <?= APP_NAME ?> University &bull; All Rights Reserved.
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
