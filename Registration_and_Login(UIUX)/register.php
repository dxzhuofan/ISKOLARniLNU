<?php
require_once __DIR__ . '/config.php';
if(current_user()) redirect(current_user()['role']==='administrator'?'../ADMIN_DASHBOARD/dashboard.php':'../STUDENT_DASHBOARD/dashboard.php');
$errors=[]; $firstName=$middleName=$lastName=$studentNumber=$mobile=$email='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf(); $firstName=trim((string)($_POST['first_name']??'')); $middleName=trim((string)($_POST['middle_name']??'')); $lastName=trim((string)($_POST['last_name']??'')); $studentNumber=trim((string)($_POST['student_number']??'')); $mobile=trim((string)($_POST['mobile_number']??'')); $email=strtolower(trim((string)($_POST['email']??''))); $password=(string)($_POST['password']??''); $confirm=(string)($_POST['confirm_password']??'');
 if($firstName===''||mb_strlen($firstName)>80)$errors[]='Enter your first name (up to 80 characters).'; if($lastName===''||mb_strlen($lastName)>80)$errors[]='Enter your last name (up to 80 characters).'; if($middleName!==''&&mb_strlen($middleName)>80)$errors[]='Middle name must be 80 characters or fewer.'; if($studentNumber===''||mb_strlen($studentNumber)>30)$errors[]='Enter your student number (up to 30 characters).'; if($mobile===''||mb_strlen($mobile)>20)$errors[]='Enter your mobile number (up to 20 characters).'; if(!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($email)>150)$errors[]='Enter a valid email address (up to 150 characters).'; if(strlen($password)<8||!preg_match('/[A-Za-z]/',$password)||!preg_match('/[0-9]/',$password))$errors[]='Password must be at least 8 characters and include a letter and a number.'; if($password!==$confirm)$errors[]='The passwords do not match.';
 if(!$errors)try{$pdo=db();$pdo->beginTransaction();$r=$pdo->prepare("SELECT role_id FROM roles WHERE role_name='applicant' LIMIT 1");$r->execute();$roleId=$r->fetchColumn();if(!$roleId)throw new RuntimeException('The applicant role is missing from the roles table.');$q=$pdo->prepare('INSERT INTO users(role_id,email,password_hash,account_status) VALUES(?,?,?,\'active\')');$q->execute([$roleId,$email,password_hash($password,PASSWORD_DEFAULT)]);$uid=(int)$pdo->lastInsertId();$a=$pdo->prepare('INSERT INTO applicants(user_id,student_number,first_name,middle_name,last_name,mobile_number) VALUES(?,?,?,?,?,?)');$a->execute([$uid,$studentNumber,$firstName,$middleName!==''?$middleName:null,$lastName,$mobile]);$pdo->commit();redirect('login.php?registered=1');}catch(PDOException $ex){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();$errors[]=$ex->getCode()==='23000'?'That email or student number is already registered.':'Could not create the account. Confirm the database connection and required tables.';}catch(Throwable $ex){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();$errors[]='Registration setup issue: '.$ex->getMessage();}
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Create Student Account | LNU Scholarship Portal</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page register-page">
<header class="topbar">
  <a class="brand" href="login.php"><span class="brand-mark">LNU</span><span class="brand-copy"><strong>Leyte Normal University</strong><small>SCHOLARSHIP MANAGEMENT PORTAL</small></span></a>
  <a class="outline-btn" href="login.php">Sign In</a>
</header>
<main class="single-layout register-layout">
<section class="form-panel register-panel"><div class="form-accent"></div><div class="form-inner">
  <p class="kicker">GET STARTED</p><h2>Create your student account</h2><p class="muted">Enter the details used for your LNU applicant profile.</p>
  <?php if($errors): ?><div class="alert error" role="alert"><strong>Please check the following:</strong><ul><?php foreach($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <div class="register-steps" role="tablist" aria-label="Registration steps">
    <button class="register-step active" type="button" role="tab" aria-selected="true" aria-controls="register-step-1" data-step="1"><span class="step-number">1</span><span><strong>Personal details</strong><small>Name and student number</small></span></button>
    <span class="step-connector" aria-hidden="true"></span>
    <button class="register-step" type="button" role="tab" aria-selected="false" aria-controls="register-step-2" data-step="2"><span class="step-number">2</span><span><strong>Account security</strong><small>Contact and password</small></span></button>
  </div>
  <form method="post" action="register.php" id="registerForm" novalidate>
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <section class="register-step-panel active" id="register-step-1" role="tabpanel" aria-label="Personal details">
      <div class="register-fields two-col">
        <div class="register-field"><label for="first_name">First name</label><input id="first_name" name="first_name" autocomplete="given-name" value="<?= e($firstName) ?>" required maxlength="80" placeholder="Enter first name"></div>
        <div class="register-field"><label for="middle_name">Middle name <span class="optional">(optional)</span></label><input id="middle_name" name="middle_name" autocomplete="additional-name" value="<?= e($middleName) ?>" maxlength="80" placeholder="Enter middle name"></div>
        <div class="register-field"><label for="last_name">Last name</label><input id="last_name" name="last_name" autocomplete="family-name" value="<?= e($lastName) ?>" required maxlength="80" placeholder="Enter last name"></div>
        <div class="register-field"><label for="student_number">Student number</label><input id="student_number" name="student_number" placeholder="e.g. 2026-00001" value="<?= e($studentNumber) ?>" required maxlength="30"></div>
      </div>
      <div class="register-actions"><span class="step-hint">Step 1 of 2 · Personal information</span><button class="primary-btn" type="button" id="registerNext">Continue <span aria-hidden="true">→</span></button></div>
    </section>
    <section class="register-step-panel" id="register-step-2" role="tabpanel" aria-label="Account security" hidden>
      <div class="register-fields two-col">
        <div class="register-field"><label for="mobile_number">Mobile number</label><input id="mobile_number" name="mobile_number" type="tel" autocomplete="tel" placeholder="09XXXXXXXXX" value="<?= e($mobile) ?>" required maxlength="20"></div>
        <div class="register-field"><label for="email">LNU email address</label><input id="email" name="email" type="email" autocomplete="email" placeholder="yourname@lnu.edu.ph" value="<?= e($email) ?>" required maxlength="150"></div>
        <div class="register-field"><label for="password">Create password</label><div class="password-wrap"><input id="password" name="password" type="password" autocomplete="new-password" placeholder="At least 8 characters" required><button type="button" class="toggle-password" data-toggle="password" aria-label="Show password"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></div></div>
        <div class="register-field"><label for="confirm_password">Confirm password</label><div class="password-wrap"><input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" placeholder="Re-enter password" required><button type="button" class="toggle-password" data-toggle="confirm_password" aria-label="Show confirm password"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></div></div>
      </div>
      <div class="password-rules" id="passwordRules"><span data-rule="length">○ At least 8 characters</span><span data-rule="letter">○ At least one letter</span><span data-rule="number">○ At least one number</span></div>
      <label class="check-row terms-row"><input type="checkbox" id="agree" required><span>I confirm the information is accurate and agree to the portal's account terms.</span></label>
      <div class="register-actions"><button class="secondary-btn" type="button" id="registerBack">← Back</button><span class="step-hint">Step 2 of 2 · Secure your account</span><button class="primary-btn" type="submit">Create Account <span aria-hidden="true">→</span></button></div>
    </section>
  </form>
  <p class="form-foot">Already registered? <a href="login.php">Sign in instead</a></p>
  <div class="security-note"><span class="lock-icon">✓</span><span><strong>Account details are stored securely</strong><br>Your password is saved as a secure hash.</span></div>
</div></section>
</main>
<footer class="site-footer"><span>© <?= date('Y') ?> Leyte Normal University</span><span>Scholarship Management Portal</span><a href="mailto:scholarships@lnu.edu.ph">Need help?</a></footer>
<script src="assets/js/auth.js"></script>
<script>
(() => {
 const form=document.getElementById('registerForm'); if(!form)return;
 const tabs=[...document.querySelectorAll('.register-step')], panels=[...document.querySelectorAll('.register-step-panel')];
 let current=1;
 function showStep(n){current=n;tabs.forEach((tab)=>{const active=Number(tab.dataset.step)===n;tab.classList.toggle('active',active);tab.setAttribute('aria-selected',String(active));});panels.forEach((panel)=>{const active=panel.id===`register-step-${n}`;panel.classList.toggle('active',active);panel.hidden=!active;});}
 function validateStep(n){const panel=document.getElementById(`register-step-${n}`);for(const field of panel.querySelectorAll('input[required]')){if(field.type==='checkbox'&&!field.checked){field.setCustomValidity('Please confirm the account terms.');}else if(field.type==='checkbox'){field.setCustomValidity('');}else if(!field.checkValidity()){field.setCustomValidity(field.type==='email'?'Enter a valid email address.':'Please complete this field.');}else{field.setCustomValidity('');}if(!field.checkValidity()){field.reportValidity();field.focus();return false;}}
  if(n===2){const pw=document.getElementById('password'),confirm=document.getElementById('confirm_password');if(pw.value.length<8||!/[A-Za-z]/.test(pw.value)||!/[0-9]/.test(pw.value)){pw.setCustomValidity('Use at least 8 characters, including a letter and a number.');pw.reportValidity();pw.focus();return false;}pw.setCustomValidity('');if(confirm.value!==pw.value){confirm.setCustomValidity('Passwords do not match.');confirm.reportValidity();confirm.focus();return false;}confirm.setCustomValidity('');}
  return true;}
 document.getElementById('registerNext').addEventListener('click',()=>{if(validateStep(1)){showStep(2);document.getElementById('mobile_number').focus({preventScroll:true});}});
 document.getElementById('registerBack').addEventListener('click',()=>showStep(1));
 tabs.forEach(tab=>tab.addEventListener('click',()=>{const target=Number(tab.dataset.step);if(target===1||target===current+1&&validateStep(current))showStep(target);}));
 const hasErrors=!!document.querySelector('.alert.error');showStep(hasErrors?2:1);
})();
</script>
</body></html>
