<?php
require_once __DIR__ . '/config.php';

// Simple PHPMailer wrapper
// Install PHPMailer via: composer require phpmailer/phpmailer
// Or download from https://github.com/PHPMailer/PHPMailer

function sendMail($to, $toName, $subject, $htmlBody) {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\n";
        $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
        return mail($to, $subject, $htmlBody, $headers);
    }

    require_once $autoload;
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USER;
        $mail->Password   = MAIL_PASS;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail error: " . $e->getMessage());
        return false;
    }
}

function emailTemplate($title, $content) {
    return '<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<style>
body{font-family:Georgia,serif;background:#f5f5f5;margin:0;padding:20px}
.wrap{max-width:560px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08)}
.head{background:#1a1a1a;padding:32px;text-align:center}
.head h1{color:#fff;margin:0;font-size:22px;letter-spacing:2px}
.body{padding:36px}
.body h2{color:#1a1a1a;margin-top:0}
.body p{color:#555;line-height:1.7}
.btn{display:inline-block;margin:20px 0;padding:14px 32px;background:#1a1a1a;color:#fff;text-decoration:none;border-radius:4px;font-size:14px;letter-spacing:1px}
.foot{background:#f9f9f9;padding:20px;text-align:center;color:#aaa;font-size:12px}
</style></head>
<body><div class="wrap">
<div class="head"><h1>✂ BarberBook</h1></div>
<div class="body"><h2>'.$title.'</h2>'.$content.'</div>
<div class="foot">© '.date('Y').' BarberBook — All rights reserved</div>
</div></body></html>';
}

function sendBookingConfirmation($clientEmail, $clientName, $appt, $barberName, $shopName, $service) {
    $content = '<p>Hi <strong>'.htmlspecialchars($clientName).'</strong>,</p>
    <p>Your appointment has been confirmed!</p>
    <table style="width:100%;border-collapse:collapse;margin:20px 0">
    <tr><td style="padding:8px;color:#888">Shop</td><td style="padding:8px;font-weight:bold">'.htmlspecialchars($shopName).'</td></tr>
    <tr style="background:#f9f9f9"><td style="padding:8px;color:#888">Barber</td><td style="padding:8px;font-weight:bold">'.htmlspecialchars($barberName).'</td></tr>
    <tr><td style="padding:8px;color:#888">Service</td><td style="padding:8px">'.htmlspecialchars($service).'</td></tr>
    <tr style="background:#f9f9f9"><td style="padding:8px;color:#888">Date</td><td style="padding:8px">'.date('l, F j, Y', strtotime($appt['appointment_date'])).'</td></tr>
    <tr><td style="padding:8px;color:#888">Time</td><td style="padding:8px">'.date('g:i A', strtotime($appt['appointment_time'])).'</td></tr>
    </table>
    <p>See you soon!</p>';
    return sendMail($clientEmail, $clientName, 'Appointment Confirmed - BarberBook', emailTemplate('Booking Confirmed ✓', $content));
}

function sendRescheduleRequest($clientEmail, $clientName, $newDate, $newTime, $token, $barberName) {
    $acceptUrl = SITE_URL . '/reschedule_response.php?token=' . $token . '&action=accept';
    $denyUrl   = SITE_URL . '/reschedule_response.php?token=' . $token . '&action=deny';
    $content = '<p>Hi <strong>'.htmlspecialchars($clientName).'</strong>,</p>
    <p>Your barber <strong>'.htmlspecialchars($barberName).'</strong> has requested to reschedule your appointment to:</p>
    <p style="font-size:18px;font-weight:bold;color:#1a1a1a">'.date('l, F j, Y', strtotime($newDate)).' at '.date('g:i A', strtotime($newTime)).'</p>
    <p>Please respond:</p>
    <a href="'.$acceptUrl.'" class="btn" style="margin-right:12px;background:#2d6a4f">✓ Accept</a>
    <a href="'.$denyUrl.'" class="btn" style="background:#c0392b">✗ Decline</a>
    <p style="font-size:12px;color:#aaa;margin-top:24px">This link expires in 48 hours.</p>';
    return sendMail($clientEmail, $clientName, 'Reschedule Request - BarberBook', emailTemplate('Reschedule Request', $content));
}

function sendCancellationEmail($clientEmail, $clientName, $date, $time, $barberName) {
    $content = '<p>Hi <strong>'.htmlspecialchars($clientName).'</strong>,</p>
    <p>Your appointment with <strong>'.htmlspecialchars($barberName).'</strong> on <strong>'.date('l, F j, Y', strtotime($date)).' at '.date('g:i A', strtotime($time)).'</strong> has been cancelled.</p>
    <p>We apologize for any inconvenience. You can book a new appointment anytime.</p>
    <a href="'.SITE_URL.'" class="btn">Book Again</a>';
    return sendMail($clientEmail, $clientName, 'Appointment Cancelled - BarberBook', emailTemplate('Appointment Cancelled', $content));
}
