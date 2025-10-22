<?php
require_once 'includes/functions.php';

if (isset($_GET['admin']) && $_GET['admin'] === 'true') {
    logoutAdmin();
    header('Location: adminpanel.php');
} else {
    logoutCustomer();
    header('Location: login.php');
}
exit;
?>