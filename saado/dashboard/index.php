<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}
require_once '../config/database.php';
$userType = $_SESSION['user_type'] ?? '';
$userName = $_SESSION['user_name'] ?? '';

if ($userType === 'client') {
    include 'client.php';
} else {
    include 'transporter.php';
}

?>
