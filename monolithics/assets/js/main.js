/**
 * UniConnect - Monolithic University Management System
 * Main JavaScript Controller
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Mobile Sidebar Toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    const portalSidebar = document.querySelector('.portal-sidebar');

    if (sidebarToggle && portalSidebar) {
        sidebarToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            portalSidebar.classList.toggle('show');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function (e) {
            if (window.innerWidth < 992 && portalSidebar.classList.contains('show')) {
                if (!portalSidebar.contains(e.target) && e.target !== sidebarToggle) {
                    portalSidebar.classList.remove('show');
                }
            }
        });
    }

    // 2. Auto-dismiss Flash Alerts
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            } else {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 400);
            }
        }, 5000);
    });

    // 3. Confirmation Dialog for destructive actions
    const confirmElements = document.querySelectorAll('[data-confirm]');
    confirmElements.forEach(function (el) {
        el.addEventListener('click', function (e) {
            const message = el.getAttribute('data-confirm') || 'Are you sure you want to proceed?';
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    });

    // 4. Quick Demo Login Fillers
    const demoAdminBtn = document.getElementById('fillDemoAdmin');
    const demoStudentBtn = document.getElementById('fillDemoStudent');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');

    if (demoAdminBtn && emailInput && passwordInput) {
        demoAdminBtn.addEventListener('click', function () {
            emailInput.value = 'admin@uniconnect.edu';
            passwordInput.value = 'admin123';
            highlightInputs();
        });
    }

    if (demoStudentBtn && emailInput && passwordInput) {
        demoStudentBtn.addEventListener('click', function () {
            emailInput.value = 'john.doe@uniconnect.edu';
            passwordInput.value = 'student123';
            highlightInputs();
        });
    }

    function highlightInputs() {
        emailInput.classList.add('is-valid');
        passwordInput.classList.add('is-valid');
        setTimeout(() => {
            emailInput.classList.remove('is-valid');
            passwordInput.classList.remove('is-valid');
        }, 1200);
    }

    // 5. Interactive Grade Calculator for Grade Assignment Forms & Modals
    document.querySelectorAll('.modal').forEach(function (modal) {
        const assign = modal.querySelector('input[name="assignment_score"]');
        const mid = modal.querySelector('input[name="midterm_score"]');
        const fin = modal.querySelector('input[name="final_score"]');
        const totalEl = modal.querySelector('.live-total');
        const letterEl = modal.querySelector('.live-letter');
        const pointEl = modal.querySelector('.live-point');

        if (assign && mid && fin && totalEl) {
            function updateModalGrade() {
                const a = Math.max(0, Math.min(100, parseFloat(assign.value) || 0));
                const m = Math.max(0, Math.min(100, parseFloat(mid.value) || 0));
                const f = Math.max(0, Math.min(100, parseFloat(fin.value) || 0));

                const total = (a * 0.20) + (m * 0.30) + (f * 0.50);
                totalEl.textContent = total.toFixed(2) + '%';

                let letter = 'F';
                let point = '0.00';

                if (total >= 93) { letter = 'A+'; point = '4.00'; }
                else if (total >= 90) { letter = 'A'; point = '4.00'; }
                else if (total >= 87) { letter = 'A-'; point = '3.70'; }
                else if (total >= 83) { letter = 'B+'; point = '3.30'; }
                else if (total >= 80) { letter = 'B'; point = '3.00'; }
                else if (total >= 77) { letter = 'B-'; point = '2.70'; }
                else if (total >= 73) { letter = 'C+'; point = '2.30'; }
                else if (total >= 70) { letter = 'C'; point = '2.00'; }
                else if (total >= 60) { letter = 'D'; point = '1.00'; }

                if (letterEl) {
                    letterEl.textContent = letter;
                    if (letter.startsWith('A')) {
                        letterEl.className = 'badge bg-success live-letter';
                    } else if (letter.startsWith('B')) {
                        letterEl.className = 'badge bg-primary live-letter';
                    } else if (letter.startsWith('C')) {
                        letterEl.className = 'badge bg-info text-dark live-letter';
                    } else {
                        letterEl.className = 'badge bg-danger live-letter';
                    }
                }
                if (pointEl) pointEl.textContent = point;
            }

            assign.addEventListener('input', updateModalGrade);
            mid.addEventListener('input', updateModalGrade);
            fin.addEventListener('input', updateModalGrade);
        }
    });

    // 6. Instant Table Filter Helper
    const tableSearchInput = document.getElementById('tableSearchInput');
    const filterableTable = document.getElementById('filterableTable');

    if (tableSearchInput && filterableTable) {
        tableSearchInput.addEventListener('keyup', function () {
            const term = tableSearchInput.value.toLowerCase().trim();
            const rows = filterableTable.querySelectorAll('tbody tr');

            rows.forEach(function (row) {
                const text = row.textContent.toLowerCase();
                if (text.indexOf(term) > -1) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
});
