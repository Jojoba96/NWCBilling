<?php
session_start();

if (isset($_SESSION['user_id'])) {
    // If logged in, redirect based on role
    if ($_SESSION['role'] == 2) {
        header('Location: /NWCBilling/build/Employee.php', true, 302);
    } else {
        header('Location: /NWCBilling/build/Customer.php', true, 302);
    }
} else {
    // Not logged in, go to login page
    header('Location: /NWCBilling/build/pages/sign-in.php', true, 302);
}
exit;

?>


