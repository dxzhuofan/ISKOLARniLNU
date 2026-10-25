<?php
// Top of every admin page. Set $title before including this file.
$current = basename($_SERVER['SCRIPT_NAME']);
$current = ['application_view.php' => 'applications.php', 'grantee_import.php' => 'grantees.php'][$current] ?? $current;

$nav = [
    'dashboard.php'    => ['Dashboard', '🏠'],
    'students.php'     => ['Student Records', '👥'],
    'applications.php' => ['Applications', '📄'],
    'scholarships.php' => ['Scholarship Programs', '🎓'],
    'grantees.php'     => ['Grantees', '🏅'],
    'export.php'       => ['Reports & Excel Export', '📊'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> | LNU Scholarship Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<nav class="side" id="side" aria-label="Main navigation">
    <div class="brand">
        <div class="logo">LNU</div>
        <div><b>Leyte Normal University</b><small>SCHOLARSHIP ADMIN</small></div>
    </div>
    <?php foreach ($nav as $file => [$label, $icon]): ?>
        <a href="<?= $file ?>"<?= $file === $current ? ' aria-current="page"' : '' ?>>
            <span aria-hidden="true"><?= $icon ?></span><?= e($label) ?>
        </a>
    <?php endforeach; ?>
    <a href="../Registration_and_Login(UIUX)/logout.php"><span aria-hidden="true">↩</span>Sign Out</a>
</nav>
<div class="main">
    <header class="top">
        <button class="btn sec sm menu" type="button" onclick="document.getElementById('side').classList.toggle('open')">☰ Menu</button>
        <h1><?= e($title) ?></h1>
        <form action="students.php" method="get">
            <label class="sr" for="gs">Search students</label>
            <input id="gs" name="q" placeholder="Search students…">
        </form>
    </header>
    <main class="content">
        <?= show_flash() ?>
