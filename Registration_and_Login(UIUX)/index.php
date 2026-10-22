<?php
require_once __DIR__ . '/config.php';
if (current_user()) {
    redirect(current_user()['role'] === 'administrator' ? '../ADMIN_DASHBOARD/dashboard.php' : '../STUDENT_DASHBOARD/dashboard.php');
}
redirect('login.php');
