<?php
//Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

//Load Composer's autoloader
require 'vendor/autoload.php';

//Create an instance; passing `true` enables exceptions
$mail = new PHPMailer(true);

try {
    //Server settings
    $mail->isSMTP();                                            // Send using SMTP
    $mail->Host       = 'smtpout.secureserver.net';                   // Set the SMTP server to send through (GoDaddy Office365)
    $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
    $mail->Username   = 'ta@allyted.com';                       // Your GoDaddy email address
    $mail->Password   = 'Allyted@2025';                  // Your GoDaddy email password or app password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         // Enable TLS encryption
    $mail->Port       = 587;                                    // TCP port to connect to (TLS)

    //Recipients
    $mail->setFrom('ta@allyted.com', 'Allyted Solutions');      // Sender's email and name
    $mail->addAddress('ajayallyted@gmail.com', 'Recipient Name');  // Add a recipient

    // Optional additional recipients:
    // $mail->addReplyTo('info@allyted.com', 'Information');
    // $mail->addCC('cc@example.com');
    // $mail->addBCC('bcc@example.com');

    //Attachments (optional)
    // $mail->addAttachment('/path/to/file.pdf');  

    //Content
    $mail->isHTML(true);                                        // Set email format to HTML
    $mail->Subject = 'Test Email from Allyted';
    $mail->Body    = 'This is a <b>test email</b> sent using PHPMailer via GoDaddy SMTP.';
    $mail->AltBody = 'This is a test email sent using PHPMailer via GoDaddy SMTP.';

    $mail->send();
    echo 'Message has been sent successfully';
} catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
}
