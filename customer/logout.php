<?php
session_start();

// Destroy customer session
unset($_SESSION['customer_id']);
unset($_SESSION['customer_nama']);
unset($_SESSION['customer_username']);

// Destroy entire session if no other user logged in
if (!isset($_SESSION['admin_id'])) {
    session_destroy();
}

// Redirect to login page
header("Location: login.php?message=logout_success");
exit();
?>