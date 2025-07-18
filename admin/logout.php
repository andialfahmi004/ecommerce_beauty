<?php
session_start();

// Destroy admin session
unset($_SESSION['admin_id']);
unset($_SESSION['admin_nama']);
unset($_SESSION['admin_username']);

// Destroy entire session if no other user logged in
if (!isset($_SESSION['customer_id'])) {
    session_destroy();
}

// Redirect to login page
header("Location: login.php?message=logout_success");
exit();
?>