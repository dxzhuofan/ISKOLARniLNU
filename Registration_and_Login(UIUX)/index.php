<?php
require_once __DIR__ . '/config.php';
if (current_user()) {
    redirect(dashboard_path_for_role((string)current_user()['role']));
}
redirect('login.php');
