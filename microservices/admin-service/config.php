<?php
/**
 * UniConnect Microservices - Admin Service Config
 * Owns: uniconnect_admin_db
 */

require_once __DIR__ . '/../shared/config.php';
require_once __DIR__ . '/../shared/Database.php';
require_once __DIR__ . '/../shared/HttpClient.php';
require_once __DIR__ . '/../shared/JwtHelper.php';
require_once __DIR__ . '/../shared/Response.php';
require_once __DIR__ . '/../shared/Helpers.php';

function admin_db() {
    return Database::connect(DB_ADMIN);
}
