<?php
require_once __DIR__ . '/config.php';
if (current_user()) redirect(current_user()['role'] === 'administrator' ? '../ADMIN_DASHBOARD/dashboard.php' : '../STUDENT_DASHBOARD/dashboard.php');
$error = ''; $activeRole = (($_POST['role'] ?? $_GET['role'] ?? 'student') === 'admin') ? 'admin' : 'student'; $email='';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf(); $email = strtolower(trim((string)($_POST['email'] ?? ''))); $password=(string)($_POST['password'] ?? '');
  $activeRole = (($_POST['role'] ?? 'student') === 'admin') ? 'admin' : 'student';
  if ($email === '' || strlen($email) > 150 || $password === '') $error='Enter your LNU email address or student number and password.';
  else try {
    $pdo=db();
    $q=$pdo->prepare('SELECT DISTINCT u.user_id,u.role_id,u.email,u.password_hash,u.account_status,u.failed_login_count,u.locked_until,u.must_change_password,r.role_name FROM users u JOIN roles r ON r.role_id=u.role_id LEFT JOIN applicants a ON a.user_id=u.user_id WHERE u.email=:email OR a.student_number=:student_number LIMIT 1');
    $q->execute(['email'=>$email,'student_number'=>$email]); $u=$q->fetch();
    $expectedRole=$activeRole==='admin'?'administrator':'applicant';
    if (!$u || !password_verify($password,$u['password_hash'])) {
      if ($u) { $pdo->prepare('UPDATE users SET failed_login_count=LEAST(failed_login_count+1,255), locked_until=IF(failed_login_count+1>=5,DATE_ADD(NOW(),INTERVAL 15 MINUTE),locked_until) WHERE user_id=?')->execute([$u['user_id']]); }
      $pdo->prepare('INSERT INTO login_attempts(user_id,email_attempted,ip_address,user_agent,was_successful) VALUES(?,?,?,?,0)')->execute([$u['user_id']??null,$email,$_SERVER['REMOTE_ADDR']??null,substr($_SERVER['HTTP_USER_AGENT']??'',0,255)]);
      $error='The email or password is incorrect.';
    } elseif ($u['role_name']!==$expectedRole) $error='This account does not match the selected role tab.';
    elseif ($u['account_status']!=='active') $error='This account is not active. Contact the scholarship office or system administrator.';
    elseif (!empty($u['locked_until']) && strtotime($u['locked_until'])>time()) $error='Too many failed attempts. Please try again after the temporary lock expires.';
    else {
      session_regenerate_id(true);
      $name='LNU User';
      if ($u['role_name']==='applicant') {
        $p=$pdo->prepare('SELECT first_name,middle_name,last_name FROM applicants WHERE user_id=? LIMIT 1'); $p->execute([$u['user_id']]); $profile=$p->fetch();
        if ($profile) $name=trim($profile['first_name'].' '.($profile['middle_name']??'').' '.$profile['last_name']);
      } else {
        $p=$pdo->prepare('SELECT first_name,middle_name,last_name FROM staff_profiles WHERE user_id=? LIMIT 1'); $p->execute([$u['user_id']]); $profile=$p->fetch();
        if ($profile) $name=trim($profile['first_name'].' '.($profile['middle_name']??'').' '.$profile['last_name']);
      }
      $_SESSION['user']=['id'=>(int)$u['user_id'],'name'=>$name,'email'=>$u['email'],'role'=>$u['role_name'],'must_change_password'=>(bool)$u['must_change_password']];
      $_SESSION['user_id']=(int)$u['user_id'];
      $_SESSION['role_id']=(int)$u['role_id'];
      $pdo->prepare('UPDATE users SET failed_login_count=0,locked_until=NULL,last_login_at=NOW() WHERE user_id=?')->execute([$u['user_id']]);
      $pdo->prepare('INSERT INTO login_attempts(user_id,email_attempted,ip_address,user_agent,was_successful) VALUES(?,?,?,?,1)')->execute([$u['user_id'],$email,$_SERVER['REMOTE_ADDR']??null,substr($_SERVER['HTTP_USER_AGENT']??'',0,255)]);
      redirect($u['role_name']==='administrator'?'../ADMIN_DASHBOARD/dashboard.php':'../STUDENT_DASHBOARD/dashboard.php');
    }
  } catch (PDOException $ex) { $error='Database connection or query failed. Check config.php and confirm your database is lnu_scholarship_db.'; }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Sign In | LNU Scholarship Portal</title><link rel="stylesheet" href="css/style.css"></head><body class="auth-page">
<header class="topbar"><a class="brand" href="login.php"><span class="brand-mark">LNU</span><span class="brand-copy"><strong>Leyte Normal University</strong><small>SCHOLARSHIP MANAGEMENT PORTAL</small></span></a><a class="outline-btn" href="register.php">Create Account</a></header>
<main class="auth-layout"><section class="intro-panel"><span class="eyebrow"><i></i> AY 2026–2027 Applications Now Open</span><h1 class="animated-hero-title" aria-label="Fund Your Education."><span class="title-line title-line-one">Fund Your</span><span class="title-line title-line-two">Education<span class="title-period">.</span></span></h1><p class="intro-copy">The LNU Scholarship Portal lets you browse programs, apply online, upload documents with ML-powered OCR verification, and monitor your application status — all in one place.</p><div class="stats"><div><b class="stat-icon">◆</b><strong>6</strong><span>Scholarship<br>Programs</span></div><div><b class="stat-icon">♟</b><strong>215</strong><span>Available<br>Slots</span></div><div><b class="stat-icon">▥</b><strong>₱40K</strong><span>Max Grant /<br>Sem</span></div><div><b class="stat-icon">✓</b><strong>94%</strong><span>OCR<br>Accuracy</span></div></div><div class="intro-note">A clearer path to educational support.</div>
<div class="student-illustration" aria-hidden="true">
  <img class="student-art" src="images/lnu-student-illustration.png" alt="">
</div></section>
<section class="form-panel"><div class="form-accent"></div><div class="form-inner"><h2 id="form-title">Student Sign In</h2><p class="muted">Access your LNU Scholarship Portal account.</p>
<div class="role-tabs" data-active="<?= e($activeRole) ?>" role="tablist" aria-label="Account type"><button type="button" class="role-tab <?= $activeRole==='student'?'active':'' ?>" data-role="student" role="tab" aria-selected="<?= $activeRole==='student'?'true':'false' ?>">♧ &nbsp; Student</button><button type="button" class="role-tab <?= $activeRole==='admin'?'active':'' ?>" data-role="admin" role="tab" aria-selected="<?= $activeRole==='admin'?'true':'false' ?>">♙ &nbsp; Administrator</button></div>
<?php if($error): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?><?php if(isset($_GET['registered'])): ?><div class="alert success">Your student account was created. You can sign in now.</div><?php endif; ?>
<form method="post" action="login.php" id="loginForm" novalidate><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="role" id="roleInput" value="<?= e($activeRole) ?>"><label for="email">LNU email address or student number</label><input id="email" name="email" type="text" autocomplete="username" placeholder="yourname@lnu.edu.ph or 2026-00001" value="<?= e($email) ?>" required maxlength="150"><div class="field-heading"><label for="password">Password</label><a href="forgot_password.php">Forgot password?</a></div><div class="password-wrap"><input id="password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required><button type="button" class="toggle-password" data-toggle="password" aria-label="Show password"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></div><label class="check-row"><input type="checkbox" name="remember" value="1"><span>Keep me signed in</span></label><button class="primary-btn full" type="submit" formnovalidate>Sign In to Portal <span aria-hidden="true">↪</span></button></form><p class="form-foot">New to the portal? <a href="register.php">Create a student account</a></p><div class="security-note"><span class="lock-icon">✓</span><span><strong>Secure access</strong><br>Administrator access is restricted to authorized accounts.</span></div></div></section></main><footer class="site-footer"><span>© <?= date('Y') ?> Leyte Normal University</span><span>Scholarship Management Portal</span><a href="mailto:scholarships@lnu.edu.ph">Need help?</a></footer><script src="js/auth.js"></script></body></html>
