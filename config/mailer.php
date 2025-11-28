<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/SMTP.php';
require_once __DIR__ . '/../PHPMailer/Exception.php';

// Load environment variables
function loadEnv($path) {
    if (!file_exists($path)) {
        throw new Exception('.env file not found');
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

loadEnv(__DIR__ . '/../.env');

function sendVerificationEmail($toEmail, $toName, $verificationToken) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->SMTPDebug = 0; // Set to 2 for debugging
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USERNAME'];
        $mail->Password   = $_ENV['SMTP_PASSWORD'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $_ENV['SMTP_PORT'];
        $mail->CharSet    = 'UTF-8';
        
        // Recipients
        $mail->setFrom($_ENV['SMTP_FROM_EMAIL'], $_ENV['SMTP_FROM_NAME']);
        $mail->addAddress($toEmail, $toName);
        
        // Content
        $verification_link = "http://" . $_SERVER['HTTP_HOST'] . "/spital/verify-email.php?token=" . $verificationToken;
        
        $mail->isHTML(true);
        $mail->Subject = 'Verifică adresa de email - Spital';
        $mail->Body    = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #d82323; color: white; padding: 20px; text-align: center; }
                    .content { background: #f9f9f9; padding: 30px; }
                    .button { display: inline-block; padding: 12px 30px; background: #d82323; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                    .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Bine ai venit!</h1>
                    </div>
                    <div class='content'>
                        <p>Bună " . htmlspecialchars($toName) . ",</p>
                        <p>Mulțumim că te-ai înregistrat pe platforma noastră!</p>
                        <p>Te rugăm să verifici adresa de email făcând clic pe butonul de mai jos:</p>
                        <p style='text-align: center;'>
                            <a href='" . $verification_link . "' class='button'>Verifică Email</a>
                        </p>
                        <p>Sau copiază și lipește acest link în browser:</p>
                        <p style='word-break: break-all; background: white; padding: 10px; border-radius: 5px;'>" . $verification_link . "</p>
                        <p><strong>Acest link va expira în 24 de ore.</strong></p>
                        <p>Dacă nu ai creat acest cont, te rugăm să ignori acest email.</p>
                    </div>
                    <div class='footer'>
                        <p>&copy; 2025 Spital. Toate drepturile rezervate.</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        $mail->AltBody = "Bună " . $toName . ",\n\nMulțumim că te-ai înregistrat! Te rugăm să verifici adresa de email accesând: " . $verification_link . "\n\nAcest link va expira în 24 de ore.";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail error: {$mail->ErrorInfo}");
        return false;
    }
}
?>
