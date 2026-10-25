<?php
session_start();
$_SESSION = [];
session_destroy();
header('Location: ../Registration_and_Login(UIUX)/login.php');
exit;
