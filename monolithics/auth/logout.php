<?php
/**
 * UniConnect - Monolithic University Management System
 * Authentication: Logout Handler
 */

require_once __DIR__ . '/../config/config.php';

logout_user();
header("Location: " . BASE_URL . "auth/login.php");
exit;
