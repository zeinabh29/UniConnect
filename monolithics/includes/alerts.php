<?php
/**
 * UniConnect - Monolithic University Management System
 * Flash Alerts Component
 */

if (function_exists('has_flash') && has_flash()):
    $flash = get_flash();
    $alertType = $flash['type'] ?? 'info';
    $icon = 'fa-circle-info';

    switch ($alertType) {
        case 'success':
            $icon = 'fa-circle-check';
            break;
        case 'danger':
            $icon = 'fa-circle-exclamation';
            break;
        case 'warning':
            $icon = 'fa-triangle-exclamation';
            break;
        case 'info':
        default:
            $icon = 'fa-circle-info';
            break;
    }
?>
    <div class="alert alert-<?= e($alertType) ?> alert-dismissible fade show shadow-sm mb-4" role="alert">
        <div class="d-flex align-items-center">
            <i class="fa-solid <?= $icon ?> fs-5 me-2"></i>
            <div><?= e($flash['message']) ?></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
