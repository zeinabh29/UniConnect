<?php
/**
 * UniConnect Microservices Architecture
 * Frontend: Logout Handler
 */

require_once __DIR__ . '/../config/gateway_client.php';

logout_user_session();
header("Location: " . BASE_URL . "auth/login.php");
exit;
