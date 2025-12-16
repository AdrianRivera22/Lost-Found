<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

class MailService {

    public function sendEmail($to_email, $to_name, $subject, $body, $image_path = null, $image_cid = null) {
        
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;


            $mail->Username   = 'lostfoundreportsystem@gmail.com';         
            $mail->Password   = 'bzzp eeer gkbo dvkm';          

            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;


            $mail->setFrom($mail->Username, 'Lost & Found System');


            $mail->addAddress($to_email, $to_name);

            // content
            $mail->isHTML(true); 

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