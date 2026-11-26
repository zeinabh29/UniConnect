<?php
/**
 * UniConnect Microservices - Notification Service Config
 * Owns: uniconnect_notification_db
 */

require_once __DIR__ . '/../shared/config.php';
require_once __DIR__ . '/../shared/Database.php';
require_once __DIR__ . '/../shared/JwtHelper.php';
require_once __DIR__ . '/../shared/Response.php';
require_once __DIR__ . '/../shared/Helpers.php';

function notification_db() {
    return Database::connect(DB_NOTIFICATION);
}
