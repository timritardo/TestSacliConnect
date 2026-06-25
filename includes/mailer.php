<?php
/**
 * includes/mailer.php
 * Shared PHPMailer factory.  Call createMailer() to get a pre-configured
 * PHPMailer instance ready for sending from the school Gmail account.
 *
 * Usage:
 *   require_once 'includes/mailer.php';
 *   $mail = createMailer();
 *   $mail->addAddress($recipientEmail, $recipientName);
 *   $mail->Subject = '...';
 *   $mail->Body    = '...';
 *   $mail->send();
 *
 * NOTE: Move the SMTP credentials to .env and read them with
 *       getenv() or $_ENV after loading with a dotenv library.
 *       For now they are centralised here so they only appear in ONE place.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Creates and returns a configured PHPMailer instance.
 *
 * @param  bool  $exceptions  Pass true to throw exceptions instead of returning false on error.
 * @return PHPMailer
 */
function createMailer(bool $exceptions = true): PHPMailer
{
    // ─── SMTP Credentials ─────────────────────────────────────────────────
    // TODO: Load these from .env via getenv() once a dotenv loader is added.
    $smtp_host     = 'smtp.gmail.com';
    $smtp_username = 'sacliconnect@gmail.com';
    $smtp_password = getenv('SMTP_PASSWORD') ?: '';
    $smtp_port     = 587;
    $from_name     = APP_SCHOOL . ' – SacliConnect';
    // ──────────────────────────────────────────────────────────────────────

    $mail = new PHPMailer($exceptions);
    $mail->isSMTP();
    $mail->Host       = $smtp_host;
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtp_username;
    $mail->Password   = $smtp_password;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = $smtp_port;
    $mail->isHTML(true);
    $mail->setFrom($smtp_username, $from_name);

    return $mail;
}
