<?php
// Top of every admin page. Set $title before including this file.
$current = basename($_SERVER['SCRIPT_NAME']);
$current = ['application_view.php' => 'applications.php', 'grantee_import.php' => 'grantees.php'][$current] ?? $current;
$role = current_staff_role();

$nav = [
    'dashboard.php'    => ['Dashboard', '🏠', ['administrator', 'scholarship_officer']],
    'students.php'     => ['Student Records', '👥', ['administrator']],
    'academic_changes.php' => ['Academic Change Requests', '↪', ['administrator']],
    'applications.php' => ['Applications', '📄', STAFF_ROLES],
    'scholarships.php' => ['Scholarship Programs', '🎓', ['administrator', 'scholarship_officer']],
    'audit_log.php'    => ['Audit Log', '🕘', ['administrator']],
    'grantees.php'     => ['Grantees', '🏅', ['administrator']],
    'export.php'       => ['Reports & Excel Export', '📊', ['administrator']],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> | LNU Scholarship Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/admin.css?v=20261031-modal">
</head>
<body>
<nav class="side" id="side" aria-label="Main navigation">
    <div class="brand">
        <div class="logo">LNU</div>
        <div><b>Leyte Normal University</b><small>SCHOLARSHIP ADMIN</small></div>
    </div>
    <?php foreach ($nav as $file => [$label, $icon, $roles]): ?>
        <?php if (in_array($role, $roles, true)): ?>
        <a href="<?= $file ?>"<?= $file === $current ? ' aria-current="page"' : '' ?>>
            <span aria-hidden="true"><?= $icon ?></span><?= e($label) ?>
        </a>
        <?php endif; ?>
    <?php endforeach; ?>
    <a href="../Registration_and_Login(UIUX)/logout.php"><span aria-hidden="true">↩</span>Sign Out</a>
</nav>
<div class="main">
    <header class="top">
        <button class="btn sec sm menu" type="button" onclick="document.getElementById('side').classList.toggle('open')">☰ Menu</button>
        <h1><?= e($title) ?></h1>
        <?php if ($role === 'administrator'): ?><form action="students.php" method="get">
            <label class="sr" for="gs">Search students</label>
            <input id="gs" name="q" placeholder="Search students…">
        </form><?php endif; ?>
    </header>
    <main class="content">
        <?= show_flash() ?>
