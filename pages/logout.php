<?php
// ------------------------------------------------------------
// pages/logout.php — Log out
//
// Only accepts a POST form with a valid CSRF token (the "Déconnexion"
// button in the header). A simple link (GET) is refused: otherwise any
// other website could log our users out with a hidden image.
// The cart is kept (see log_user_out() in functions.php).
// ------------------------------------------------------------

require '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_csrf_token($_POST['csrf_token'] ?? '')) {
    log_user_out();
    set_auth_notice('Vous êtes déconnecté(e). À bientôt !');
}

header('Location: ../index.php');
exit;
