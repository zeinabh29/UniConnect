<?php
/**
 * UniConnect Microservices - Course, Enrollment & Grading Service Config
 * Owns: uniconnect_course_db
 */

require_once __DIR__ . '/../shared/config.php';
require_once __DIR__ . '/../shared/Database.php';
require_once __DIR__ . '/../shared/HttpClient.php';
require_once __DIR__ . '/../shared/JwtHelper.php';
require_once __DIR__ . '/../shared/Response.php';
require_once __DIR__ . '/../shared/Helpers.php';

function course_db() {
    return Database::connect(DB_COURSE);
}
