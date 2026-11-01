<?php
declare(strict_types=1);

function send_portal_email(string $recipient, string $subject, string $body): bool
{
    $host = trim((string)(getenv('LNU_MAIL_HOST') ?: ''));
    if ($host === '') {
        $from = getenv('LNU_MAIL_FROM') ?: 'no-reply@lnu.edu.ph';
        $headers = 'From: ' . $from . "\r\n" . 'Content-Type: text/plain; charset=UTF-8';
        $sent = mail($recipient, $subject, $body, $headers);
        if (!$sent) {
            error_log('LNU mail delivery failed for ' . $recipient . ' (' . $subject . '). Configure SMTP for reliable delivery.');
        }
        return $sent;
    }

    $username = trim((string)(getenv('LNU_MAIL_USERNAME') ?: ''));
    $password = (string)(getenv('LNU_MAIL_PASSWORD') ?: '');
    $from = trim((string)(getenv('LNU_MAIL_FROM') ?: $username));
    if ($username === '' || $password === '' || $from === '') {
        error_log('LNU SMTP is enabled but LNU_MAIL_USERNAME, LNU_MAIL_PASSWORD, or LNU_MAIL_FROM is missing.');
        return false;
    }
    $encryption = strtolower((string)(getenv('LNU_MAIL_ENCRYPTION') ?: 'tls'));
    if (!in_array($encryption, ['tls', 'ssl'], true)) {
        error_log('LNU SMTP encryption must be set to tls or ssl.');
        return false;
    }

    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        error_log('LNU SMTP is enabled but PHPMailer is missing. Run composer install in Registration_and_Login(UIUX).');
        return false;
    }
    require_once $autoload;

    try {
        $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = $host;
        $mailer->SMTPAuth = true;
        $mailer->Username = $username;
        $mailer->Password = $password;
        $mailer->Port = (int)(getenv('LNU_MAIL_PORT') ?: 587);
        $mailer->Timeout = 15;
        $mailer->SMTPSecure = $encryption === 'ssl'
            ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->CharSet = 'UTF-8';
        $mailer->setFrom($from, 'LNU Scholarship Portal');
        $mailer->addAddress($recipient);
        $mailer->Subject = $subject;
        $mailer->Body = $body;
        $mailer->isHTML(false);
        $mailer->send();
        return true;
    } catch (PHPMailer\PHPMailer\Exception $exception) {
        error_log('LNU SMTP delivery failed for ' . $recipient . ' (' . $subject . '): ' . $exception->getMessage());
        return false;
    }
}
