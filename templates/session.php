<?php
// Start session securely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Optional: regenerate session ID after login (security)
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}
?>