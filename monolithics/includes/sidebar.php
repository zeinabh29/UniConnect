<?php
/**
 * UniConnect - Monolithic University Management System
 * Dynamic Responsive Sidebar
 */

$currentUser = current_user();
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));
$isAdmin = is_admin();
$isStudent = is_student();
?>
<aside class="portal-sidebar">
    <div class="brand-header">
        <a href="<?= BASE_URL ?>" class="brand-logo">
            <div class="brand-icon-box">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <div>
                <span class="d-block" style="line-height: 1.1;">UniConnect</span>
                <span class="d-block text-secondary small fw-normal" style="font-size: 0.68rem; letter-spacing: 0.5px;">PORTAL</span>
            </div>
        </a>
    </div>

    <div class="sidebar-menu">
        <?php if ($isAdmin): ?>
            <!-- Administrator Navigation -->
            <div class="sidebar-heading">Main</div>
            <a href="<?= BASE_URL ?>admin/dashboard.php" class="nav-link-item <?= ($currentPage === 'dashboard.php' && $currentDir === 'admin') ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Dashboard</span>
            </a>

            <div class="sidebar-heading">Academic Management</div>
            <a href="<?= BASE_URL ?>admin/students.php" class="nav-link-item <?= (in_array($currentPage, ['students.php', 'student-add.php', 'student-edit.php'])) ? 'active' : '' ?>">
                <i class="fa-solid fa-user-graduate"></i>
                <span>Students</span>
            </a>
            <a href="<?= BASE_URL ?>admin/courses.php" class="nav-link-item <?= (in_array($currentPage, ['courses.php', 'course-add.php', 'course-edit.php'])) ? 'active' : '' ?>">
                <i class="fa-solid fa-book-open"></i>
                <span>Courses Catalog</span>
            </a>
            <a href="<?= BASE_URL ?>admin/enrollments.php" class="nav-link-item <?= ($currentPage === 'enrollments.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-id-card-clip"></i>
                <span>Enrollments</span>
            </a>
            <a href="<?= BASE_URL ?>admin/grades.php" class="nav-link-item <?= ($currentPage === 'grades.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-award"></i>
                <span>Grades & Records</span>
            </a>

            <div class="sidebar-heading">Student Services</div>
            <a href="<?= BASE_URL ?>admin/certificates.php" class="nav-link-item <?= ($currentPage === 'certificates.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-file-shield"></i>
                <span>Certificate Requests</span>
            </a>
            <a href="<?= BASE_URL ?>admin/messages.php" class="nav-link-item <?= ($currentPage === 'messages.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-envelope-open-text"></i>
                <span>Contact Inquiries</span>
            </a>

        <?php elseif ($isStudent): ?>
            <!-- Student Navigation -->
            <div class="sidebar-heading">Student Portal</div>
            <a href="<?= BASE_URL ?>student/dashboard.php" class="nav-link-item <?= ($currentPage === 'dashboard.php' && $currentDir === 'student') ? 'active' : '' ?>">
                <i class="fa-solid fa-house-user"></i>
                <span>My Dashboard</span>
            </a>

            <div class="sidebar-heading">Academics</div>
            <a href="<?= BASE_URL ?>student/courses.php" class="nav-link-item <?= ($currentPage === 'courses.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-compass"></i>
                <span>Course Catalog</span>
            </a>
            <a href="<?= BASE_URL ?>student/my-courses.php" class="nav-link-item <?= ($currentPage === 'my-courses.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-book-bookmark"></i>
                <span>My Enrolled Courses</span>
            </a>
            <a href="<?= BASE_URL ?>student/grades.php" class="nav-link-item <?= ($currentPage === 'grades.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-square-poll-vertical"></i>
                <span>Grades & Transcript</span>
            </a>

            <div class="sidebar-heading">Services & Account</div>
            <a href="<?= BASE_URL ?>student/certificates.php" class="nav-link-item <?= (in_array($currentPage, ['certificates.php', 'certificate-view.php'])) ? 'active' : '' ?>">
                <i class="fa-solid fa-file-contract"></i>
                <span>Certificate Requests</span>
            </a>
            <a href="<?= BASE_URL ?>student/notifications.php" class="nav-link-item <?= ($currentPage === 'notifications.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-bell"></i>
                <span>Notifications</span>
            </a>
            <a href="<?= BASE_URL ?>student/profile.php" class="nav-link-item <?= ($currentPage === 'profile.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-id-badge"></i>
                <span>My Profile</span>
            </a>
        <?php endif; ?>

        <div class="sidebar-heading">General</div>
        <a href="<?= BASE_URL ?>index.php" class="nav-link-item" target="_blank">
            <i class="fa-solid fa-arrow-up-right-from-square"></i>
            <span>University Website</span>
        </a>
    </div>

    <!-- User Profile Footer -->
    <div class="sidebar-user-footer">
        <div class="sidebar-user-avatar">
            <?= strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)) ?>
        </div>
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?= e($currentUser['name'] ?? 'Guest') ?></div>
            <div class="sidebar-user-role">
                <?= $isAdmin ? '<span class="text-info fw-bold">Administrator</span>' : '<span class="text-white-50">' . e($currentUser['student_id'] ?? 'Student') . '</span>' ?>
            </div>
        </div>
        <a href="<?= BASE_URL ?>auth/logout.php" class="text-danger p-2" title="Sign Out" data-confirm="Are you sure you want to log out?">
            <i class="fa-solid fa-right-from-bracket"></i>
        </a>
    </div>
</aside>
