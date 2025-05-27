<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'src/Exception.php';
require 'src/PHPMailer.php';
require 'src/SMTP.php';

function sendVerificationEmail($to_email, $verification_code, $name) {
    // Gmail SMTP configuration
   $smtp_username = "adhikariroshankumar7@gmail.com"; // Your Gmail address
    $smtp_password = "nbei mnqe qgvp lpcy"; // Your Gmail password

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
        $mail->setFrom($smtp_username, "Cleckfax Trader Hub");
        $mail->addAddress($to_email, $name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = "Verification Code";
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
                                <img src='C:\xampp\htdocs\newpull\CleckFax_Traders_Hub_Logo_group6-removebg-preview.png' alt='Cleckfax Trader Hub Logo' style='width: 100px; height: auto; margin-bottom: 20px;'>
                            
                                <!-- Heading -->
                                <h2 style='color: #333;'>Email Verification</h2>
                            
                                <!-- Text -->
                                <p style='color: #666; margin-bottom: 20px;'>Thank you $name for registering with Cleckfax Trader Hub. Please verify your email address to complete the registration process.</p>
                            
                                <!-- Verification Code -->
                                <h3 style='color: #333;'>Verification Code</h3>
                                <p style='color: #666; margin-bottom: 20px;'>Your verification code is: <strong>$verification_code</strong></p>
                            
                                <!-- Thank you message -->
                                <p style='color: #666;'>Thank you for registering with Cleckfax Trader Hub. For any queries, please contact us at <a href='mailto:contact@CleckfaxTraderHub.com' style='color: #007bff;'>contact@CleckfaxTraderHub.com</a>.</p>
                            
                                <!--  Logo -->
                                <img src='C:\xampp\htdocs\newpull\CleckFax_Traders_Hub_Logo_group6-removebg-preview.png' alt='Cleckfax Trader Hub Logo' style='width: 100px; height: auto; margin-top: 20px;'>
                            
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
