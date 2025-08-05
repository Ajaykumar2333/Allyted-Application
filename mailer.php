<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . "/vendor/autoload.php";

function sendMail($to, $name, $subject, $message, $isHtml = false) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Uncomment for debugging
        $mail->isSMTP();
        $mail->SMTPAuth = true;
        $mail->Host = "smtpout.secureserver.net";
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->Username = "ta@allyted.com";
        $mail->Password = "Allyted@2025";

        // Recipients
        $mail->setFrom("ta@allyted.com", "Allyted Team");
        $mail->addAddress($to, $name);
        $mail->addReplyTo("ta@allyted.com", "Allyted Team");

        // Content
        $mail->isHtml($isHtml);
        $mail->Subject = $subject;
        if ($isHtml) {
            $mail->Body = $message;
            $mail->AltBody = strip_tags($message); // Plain text fallback for non-HTML email clients
        } else {
            $mail->Body = "Hello $name,\n\n$message";
        }

        // Send the email
        $mail->send();
        return true;
    } catch (Exception $e) {
        throw new Exception("Failed to send email to $to: {$mail->ErrorInfo}");
    }
}
?>