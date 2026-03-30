<?php
session_start();
if (isset($_SESSION['user'])) {
    header('Location: ceas-dashboard.php');
} else {
    header('Location: login.php');
}
exit();
?>
