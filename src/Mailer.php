<?php
namespace App;

// Require PHPMailer files directly if installed manually,
// or rely on Composer's autoloader if present.
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../bootstrap.php';

class Mailer
{
    private $mail;

    public function __construct()
    {
        $this->mail = new PHPMailer(true);

        // Server settings
        // $this->mail->SMTPDebug = \PHPMailer\PHPMailer\SMTP::DEBUG_SERVER; // Enable verbose debug output
        $this->mail->isSMTP();
        $this->mail->Host       = SMTP_HOST;
        $this->mail->SMTPAuth   = true;
        $this->mail->Username   = SMTP_USERNAME;
        $this->mail->Password   = SMTP_PASSWORD;
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port       = SMTP_PORT;

        // Default Sender
        $this->mail->setFrom(SMTP_FROM_EMAIL, 'JobPulse AI');
    }

    /**
     * Send an email unconditionally.
     *
     * @param string $toEmail Recipient's email address
     * @param string $subject Subject of the email
     * @param string $body HTML body of the email
     * @param string $altBody Plain-text fallback body
     * @return bool True on success, false on failure
     */
    public function send(string $toEmail, string $subject, string $body, string $altBody = ''): bool
    {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($toEmail);

            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body    = $body;
            
            if (!empty($altBody)) {
                $this->mail->AltBody = $altBody;
            } else {
                $this->mail->AltBody = strip_tags($body); // Make a simple text fallback
            }

            $this->mail->send();
            return true;
        } catch (Exception $e) {
            // Log error or handle it securely without crashing out
            error_log("Message could not be sent. Mailer Error: {$this->mail->ErrorInfo}");
            return false;
        }
    }
}

