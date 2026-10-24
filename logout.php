<?php
session_start();
include("include/connection.php");

if (isset($_SESSION['user_email'])) {
    $email = mysqli_real_escape_string($con, $_SESSION['user_email']);
    mysqli_query($con, "UPDATE users SET log_in='Offline', last_seen=NOW() WHERE user_email='$email'");
}

session_destroy();
header("Location:signin.php");
exit();
?>
