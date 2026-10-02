<?php

require_once __DIR__ . '/Env.php';
require_once __DIR__ . '/../lib/phpmailer/src/Exception.php';
require_once __DIR__ . '/../lib/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../lib/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * SMTP mailer built on PHPMailer (vendored in lib/phpmailer).
 *
 * Rules this class enforces so mail can never take the site down:
 *  - every failure is caught, logged and reported as `false` - nothing throws;
 *  - if the MAIL_* settings are missing we skip sending instead of fataling,
 *    so a fresh checkout still works without mail configured;
 *  - a recipient address is validated before we try to connect;
 *  - each send gets its own PHPMailer instance, so no recipient, reply-to or
 *    body state can leak between emails in the same request;
 *  - every attempt is appended to storage/mail.log as well as error_log(), so
 *    problems can be diagnosed without digging through the web server log.
 */
class Mailer
{
    const TIMEOUT = 20; // seconds, per connection

    private static $lastError = null;

    /** True when enough MAIL_* settings are present to attempt a send. */
    public static function isConfigured()
    {
        return self::config() !== null;
    }

    /** The most recent failure message (null when the last send worked). */
    public static function lastError()
    {
        return self::$lastError;
    }

    private static function config()
    {
        $host = trim((string) Env::get('MAIL_HOST'));
        $username = trim((string) Env::get('MAIL_USERNAME'));
        $password = (string) Env::get('MAIL_PASSWORD');
        $from = trim((string) Env::get('MAIL_FROM_ADDRESS'));

        // Any missing piece means "not configured" - skip rather than half-send.
        if ($host === '' || $username === '' || $password === '' || $from === '') {
            return null;
        }

        return [
            'host' => $host,
            'port' => (int) (Env::get('MAIL_PORT') ?: 587),
            'username' => $username,
            'password' => $password,
            'encryption' => strtolower(trim((string) (Env::get('MAIL_ENCRYPTION') ?: 'tls'))),
            'from' => $from,
            'fromName' => (string) (Env::get('MAIL_FROM_NAME') ?: 'ShopWave'),
        ];
    }

    /**
     * Send one email.
     *
     * @return bool true when the SMTP server accepted the message.
     */
    public static function send($toEmail, $toName, $subject, $html, $text = null, $replyToEmail = null)
    {
        self::$lastError = null;

        $config = self::config();

        if ($config === null) {
            return self::fail('skipped - SMTP is not configured (MAIL_* settings missing)');
        }

        $toEmail = trim((string) $toEmail);

        if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return self::fail('skipped - invalid recipient address');
        }

        $subject = trim((string) $subject);

        if ($subject === '' || trim((string) $html) === '') {
            return self::fail('skipped - empty subject or body');
        }

        try {
            $mail = new PHPMailer(true); // exceptions on, so failures are visible
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';

            $mail->isSMTP();
            $mail->Host = $config['host'];
            $mail->Port = $config['port'];
            $mail->SMTPAuth = true;
            $mail->Username = $config['username'];
            $mail->Password = $config['password'];
            $mail->Timeout = self::TIMEOUT;

            // Gmail (and friends) need explicit TLS: port 465 is implicit SSL,
            // everything else uses STARTTLS.
            if ($config['encryption'] === 'ssl' || $config['encryption'] === 'smtps' || $config['port'] === 465) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
            $mail->SMTPAutoTLS = true;

            $mail->setFrom($config['from'], $config['fromName']);
            $mail->addAddress($toEmail, $toName !== '' ? (string) $toName : $toEmail);

            if ($replyToEmail !== null && filter_var($replyToEmail, FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo($replyToEmail, $toName !== '' ? (string) $toName : '');
            }

            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = $text !== null ? (string) $text : self::htmlToText($html);

            $sent = $mail->send();

            self::log('sent to=' . maskEmail($toEmail) . ' subject=' . $subject);

            return $sent;
        } catch (Throwable $e) {
            return self::fail($e->getMessage(), $toEmail, $subject);
        }
    }

    /** Very small HTML -> text fallback so every mail has a plain-text part. */
    public static function htmlToText($html)
    {
        $text = preg_replace('#<(br|/p|/div|/tr|/h[1-6]|/li)[^>]*>#i', "\n", (string) $html);
        $text = preg_replace('#<li[^>]*>#i', '  - ', (string) $text);
        $text = strip_tags((string) $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+\n/", "\n", (string) $text);
        $text = preg_replace("/\n{3,}/", "\n\n", (string) $text);

        return trim($text);
    }

    private static function fail($reason, $toEmail = '', $subject = '')
    {
        self::$lastError = $reason;

        $line = 'FAILED (' . $reason . ')';
        if ($toEmail !== '') {
            $line .= ' to=' . maskEmail($toEmail);
        }
        if ($subject !== '') {
            $line .= ' subject=' . $subject;
        }

        self::log($line);

        return false;
    }

    private static function log($line)
    {
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $line;

        error_log('[mail] ' . $line);

        $dir = __DIR__ . '/../storage';
        if (is_dir($dir) && is_writable($dir)) {
            @file_put_contents($dir . '/mail.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }
}

/** Keeps the domain in a log line but hides most of the local part. */
if (!function_exists('maskEmail')) {
    function maskEmail($email)
    {
        $email = (string) $email;
        $at = strpos($email, '@');

        if ($at === false || $at < 2) {
            return '***';
        }

        return substr($email, 0, 2) . '***' . substr($email, $at);
    }
}