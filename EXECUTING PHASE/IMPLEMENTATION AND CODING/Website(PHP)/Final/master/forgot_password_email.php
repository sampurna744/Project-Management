<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'src/Exception.php';
require 'src/PHPMailer.php';
require 'src/SMTP.php';

function sendForgotPasswordVerificationEmail($to_email, $verification_code, $name) {
    // Gmail SMTP configuration
    $smtp_username = ""; // Your Gmail address
    $smtp_password = ""; // Your Gmail password

    // Create a PHPMailer instance
    $mail = new PHPMailer(true);

    try {
        // SMTP configuration
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_username;
        $mail->Password   = $smtp_password;
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom($smtp_username, "HudderFoods");
        $mail->addAddress($to_email, $name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = "Forgot Password Verification Code";
        $mail->Body    = "
                            <!DOCTYPE html>
                            <html lang='en'>
                            <head>
                                <meta charset='UTF-8'>
                                <meta http-equiv='X-UA-Compatible' content='IE=edge'>
                                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                                <title>Email Verification</title>
                            </head>
                            <body style='font-family: Arial, sans-serif; text-align: center;'>
                            
                                <!-- Logo -->
                                <img src='' alt='Logo' style='width: 100px; height: auto; margin-bottom: 20px;'>
                            
                                <!-- Heading -->
                                <h2 style='color: #333;'>Forgot Password Verification</h2>
                            
                                <!-- Text -->
                                <p style='color: #666; margin-bottom: 20px;'>Hello $name,<br> You recently requested to reset your password for your Cleckfax Trader Hub account. Use the following verification code to reset your password:</p>
                            
                                <!-- Verification Code -->
                                <h3 style='color: #333;'>Verification Code</h3>
                                <p style='color: #666; margin-bottom: 20px;'>Your verification code is: <strong>$verification_code</strong></p>
                            
                                <!-- Thank you message -->
                                <p style='color: #666;'>If you didn't request this, you can safely ignore this email. If you have any questions, feel free to contact us at <a href='mailto:contact@hudderfoods.com' style='color: #007bff;'>contact@hudderfoods.com</a>.</p>
                            
                                <!-- HudderFoods Logo -->
                                <img src='' alt='Logo' style='width: 100px; height: auto; margin-top: 20px;'>
                            
                            </body>
                            </html>
        ";

        // Send email
        $mail->send();
    } catch (Exception $e) {
        echo "Email sending failed: {$mail->ErrorInfo}";
    }
}
?>
