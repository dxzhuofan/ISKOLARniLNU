<?php
// No login page yet: clearing the session just returns to the dashboard (auto sign-in applies again).
session_start();
$_SESSION = [];
session_destroy();
header('Location: dashboard.php');
exit;
