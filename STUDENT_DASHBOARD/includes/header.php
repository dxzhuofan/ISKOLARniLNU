<?php
// Expects: $me (from require_student), $pageTitle, $active
$nst = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND channel = 'in_app' AND is_read = 0");
$nst->execute([$_SESSION['user_id']]);
$unread = (int)$nst->fetchColumn();
$nl = $pdo->prepare("SELECT notification_id, application_id, notification_type, title, message, is_read, created_at FROM notifications
                     WHERE user_id = ? AND channel = 'in_app' ORDER BY created_at DESC, notification_id DESC LIMIT 6");
$nl->execute([$_SESSION['user_id']]);
$navNotifs = $nl->fetchAll();
$nav = [
    'dashboard'    => ['dashboard.php', 'Dashboard'],
    'scholarships' => ['scholarships.php', 'Scholarships'],
    'applications' => ['my_applications.php', 'My Applications'],
    'grantee'      => ['grantee_info.php', 'Grantee Information'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Student Portal') ?> · LNU Scholarship Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
</head>
<body>
<nav class="topbar">
  <button class="icon-btn burger" id="burger" aria-label="Open menu" aria-expanded="false">☰</button>
  <a class="brand" href="dashboard.php">
    <span class="logo">LNU</span>
    <span><b>Leyte Normal University</b><small>SCHOLARSHIP PORTAL</small></span>
  </a>
  <div class="links" id="navLinks">
    <?php foreach ($nav as $k => [$href, $label]): ?>
      <a href="<?= $href ?>" class="<?= ($active ?? '') === $k ? 'on' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>
  <div class="tools">
    <div class="drop">
      <button class="icon-btn" id="bell" aria-label="Notifications" aria-expanded="false">🔔<?php if ($unread): ?><span class="dot" id="bellCount"><?= $unread ?></span><?php endif; ?></button>
      <div class="pop" id="notifPop">
        <div class="pop-head"><b>Notifications</b><button type="button" class="link-btn" id="markAll">Mark all as read</button></div>
        <div class="pop-body">
          <?php if (!$navNotifs): ?><p class="empty">You have no notifications yet.</p><?php endif; ?>
          <?php foreach ($navNotifs as $n): ?>
            <a class="alert-row <?= $n['is_read'] ? 'read' : '' ?>" href="<?= $n['application_id'] ? 'application_view.php?id=' . (int)$n['application_id'] : 'dashboard.php#alerts' ?>">
              <i class="ind c-<?= notif_color($n['notification_type']) ?>"></i>
              <span><b><?= e($n['title']) ?></b><small><?= e($n['message']) ?> · <?= e(time_ago($n['created_at'])) ?></small></span>
            </a>
          <?php endforeach; ?>
        </div>
        <a class="pop-foot" href="dashboard.php#alerts">View all</a>
      </div>
    </div>
    <div class="drop">
      <button class="profile-btn" id="profBtn" aria-expanded="false"><span class="avatar"><?= e(initials($me)) ?></span><span class="pname"><?= e($me['first_name'] . ' ' . $me['last_name']) ?></span> ▾</button>
      <div class="pop pop-sm" id="profPop">
        <a href="profile.php">My Profile</a>
        <a href="account.php">Account Settings</a>
        <a href="account.php#password">Change Password</a>
        <a href="logout.php">Log Out</a>
      </div>
    </div>
  </div>
</nav>
<main class="page">
<?php foreach (flash() as [$t, $m]): ?>
  <div class="flash f-<?= e($t) ?>" role="status"><?= e($m) ?></div>
<?php endforeach; ?>
