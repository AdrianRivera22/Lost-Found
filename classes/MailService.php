<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

class MailService {

    /**
     * Send an email.
     *
     * @param string $to_email    Recipient's email
     * @param string $to_name     Recipient's name
     * @param string $subject     Email subject
     * @param string $body        HTML body of the email
     * @param string|null $image_path  Optional: Full filesystem path to an image to embed
     * @param string|null $image_cid   Optional: Content ID (cid) for the embedded image
     * @return bool
     */
    public function sendEmail($to_email, $to_name, $subject, $body, $image_path = null, $image_cid = null) {
        
        $mail = new PHPMailer(true);

        try {
            // $mail->SMTPDebug = SMTP::DEBUG_SERVER;  // debugging
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;

            // ---------------------------------------------------------
            // TODO: REPLACE THESE WITH YOUR NEW ACCOUNT CREDENTIALS
            // ---------------------------------------------------------
            $mail->Username   = 'lostfoundreportsystem@gmail.com';         // <--- Put new email here
            $mail->Password   = 'bzzp eeer gkbo dvkm';          // <--- Put new App Password here
            // ---------------------------------------------------------

            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Sender Info
            $mail->setFrom($mail->Username, 'Lost & Found System');

            // Recipients
            // [FIXED] Originally this was hardcoded to the sender. 
            // It now correctly sends to the dynamic $to_email argument.
            $mail->addAddress($to_email, $to_name);

            // --- Content ---
            $mail->isHTML(true); 

            // Embed image if provided
            if ($image_path !== null && $image_cid !== null && file_exists($image_path)) {
                $mail->addEmbeddedImage($image_path, $image_cid);
            }

            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags($body); 

            $mail->send();
            return true;

        } catch (Exception $e) {
            error_log("PHPMailer Error: " . $mail->ErrorInfo);
            return false;
        }
    }
}
?>