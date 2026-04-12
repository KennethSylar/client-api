<?php

namespace App\Controllers;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Contact
 *
 * Handles the public contact form submission via PHPMailer / SMTP.
 * Route: POST /contact
 */
class Contact extends BaseController
{
    public function send(): \CodeIgniter\HTTP\ResponseInterface
    {
        $body = $this->jsonBody();

        $name    = trim($body['name']    ?? '');
        $email   = trim($body['email']   ?? '');
        $phone   = trim($body['phone']   ?? '');
        $service = trim($body['service'] ?? '');
        $message = trim($body['message'] ?? '');

        if (empty($name) || empty($email) || empty($message)) {
            return $this->error('Name, email, and message are required.', 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('Invalid email address.', 400);
        }

        // ── SMTP config from .env ────────────────────────────────────
        $smtpHost   = env('email.SMTPHost',   'smtp.gmail.com');
        $smtpPort   = (int) env('email.SMTPPort',   587);
        $smtpUser   = env('email.SMTPUser',   '');
        $smtpPass   = env('email.SMTPPass',   '');
        $smtpCrypto = env('email.SMTPCrypto', 'tls');  // 'tls' or 'ssl'
        $fromEmail  = env('email.fromEmail',  $smtpUser);
        $fromName   = env('email.fromName',   'JNV Training and Development');
        $toEmail    = env('jnv.contactToEmail', $fromEmail);

        if (empty($smtpHost) || empty($smtpUser) || empty($smtpPass)) {
            log_message('error', 'Contact form: SMTP not configured in .env');
            return $this->error('Email delivery is not configured on this server.', 503);
        }

        // ── PHPMailer ────────────────────────────────────────────────
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = $smtpHost;
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtpUser;
            $mail->Password   = $smtpPass;
            $mail->SMTPSecure = $smtpCrypto === 'ssl'
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $smtpPort;
            $mail->CharSet    = PHPMailer::CHARSET_UTF8;
            $mail->Encoding   = PHPMailer::ENCODING_BASE64;

            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($toEmail);
            $mail->addReplyTo($email, $name);

            $mail->Subject = 'Enquiry: ' . ($service ?: 'General') . ' — ' . $name;

            // Plain-text alternative (improves deliverability / spam score)
            $mail->AltBody = implode("\n", [
                'New website enquiry from jnv.co.za',
                str_repeat('─', 40),
                'Name:    ' . $name,
                'Email:   ' . $email,
                'Phone:   ' . ($phone ?: 'Not provided'),
                'Service: ' . ($service ?: 'Not specified'),
                str_repeat('─', 40),
                $message,
            ]);

            $mail->isHTML(true);
            $mail->Body = $this->buildHtml($name, $email, $phone, $service, $message);

            $mail->addCustomHeader('X-Mailer',   'JNV-Website/1.0');
            $mail->addCustomHeader('X-Priority', '3');
            $mail->addCustomHeader('Importance', 'Normal');

            $mail->send();

            return $this->ok();

        } catch (PHPMailerException $e) {
            log_message('error', 'Contact form PHPMailer error: ' . $mail->ErrorInfo);
            return $this->error('Failed to send message. Please try again later.', 500);
        }
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function eNl(string $s): string
    {
        return nl2br($this->e($s));
    }

    // ----------------------------------------------------------------
    // HTML email template
    // Fully inlined for Outlook / Gmail / Apple Mail compatibility.
    // ----------------------------------------------------------------

    private function buildHtml(
        string $name,
        string $email,
        string $phone,
        string $service,
        string $message
    ): string {
        $year        = date('Y');
        $submittedAt = date('l, d F Y \a\t H:i');

        $phoneTd = $phone
            ? '<a href="tel:' . $this->e(preg_replace('/\s+/', '', $phone)) . '" style="color:#1a6b3c;text-decoration:none;">' . $this->e($phone) . '</a>'
            : '<span style="color:#9ca3af;">Not provided</span>';

        $serviceBadge = $service
            ? '<span style="display:inline-block;background-color:#dcfce7;color:#166534;font-size:12px;font-weight:600;padding:3px 10px;border-radius:20px;">' . $this->e($service) . '</span>'
            : '<span style="color:#9ca3af;">Not specified</span>';

        $replySubject = rawurlencode('Re: Your JNV Enquiry');
        $serviceLabel = $this->e($service ?: 'General');
        $eName        = $this->e($name);
        $eEmail       = $this->e($email);
        $eMessage     = $this->eNl($message);

        return <<<HTML
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="x-apple-disable-message-reformatting">
  <!--[if !mso]><!-->
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <!--<![endif]-->
  <title>New Website Enquiry — JNV</title>
  <!--[if mso]>
  <noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
  <![endif]-->
  <style>
    body,table,td,a{-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%}
    table,td{mso-table-lspace:0pt;mso-table-rspace:0pt}
    img{-ms-interpolation-mode:bicubic;border:0;height:auto;line-height:100%;outline:none;text-decoration:none}
    body{margin:0!important;padding:0!important;background-color:#f4f4f5}
    a[x-apple-data-detectors]{color:inherit!important;text-decoration:none!important}
  </style>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f5;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;">

<!--[if mso|IE]><table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f4f4f5;"><tr><td><![endif]-->

<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f4f4f5;margin:0;padding:0;">
  <tr>
    <td align="center" style="padding:32px 16px;">

      <!-- Card -->
      <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="600"
        style="max-width:600px;width:100%;background-color:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

        <!-- Green header -->
        <tr>
          <td style="background-color:#1a6b3c;padding:28px 36px;">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
              <tr>
                <td>
                  <p style="margin:0;font-size:11px;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;color:#a8d5b5;">
                    JNV Training &amp; Development
                  </p>
                  <h1 style="margin:6px 0 0;font-size:22px;font-weight:700;color:#ffffff;line-height:1.3;">
                    New Website Enquiry
                  </h1>
                </td>
                <td align="right" valign="middle">
                  <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                    <tr>
                      <td style="background-color:rgba(255,255,255,0.15);border-radius:8px;padding:8px 14px;">
                        <p style="margin:0;font-size:11px;color:#d1f0dc;font-weight:600;">$serviceLabel</p>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style="padding:32px 36px 8px;">

            <!-- Details grid -->
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%"
              style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-bottom:24px;">
              <tr style="background-color:#f9fafb;">
                <td width="120" style="padding:12px 16px;font-size:11px;font-weight:700;letter-spacing:0.8px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #e5e7eb;white-space:nowrap;">Full Name</td>
                <td style="padding:12px 16px;font-size:14px;font-weight:600;color:#111827;border-bottom:1px solid #e5e7eb;">$eName</td>
              </tr>
              <tr>
                <td width="120" style="padding:12px 16px;font-size:11px;font-weight:700;letter-spacing:0.8px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #e5e7eb;white-space:nowrap;background-color:#f9fafb;">Email</td>
                <td style="padding:12px 16px;font-size:14px;color:#111827;border-bottom:1px solid #e5e7eb;">
                  <a href="mailto:$eEmail" style="color:#1a6b3c;text-decoration:none;font-weight:500;">$eEmail</a>
                </td>
              </tr>
              <tr style="background-color:#f9fafb;">
                <td width="120" style="padding:12px 16px;font-size:11px;font-weight:700;letter-spacing:0.8px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #e5e7eb;white-space:nowrap;">Phone</td>
                <td style="padding:12px 16px;font-size:14px;color:#111827;border-bottom:1px solid #e5e7eb;">$phoneTd</td>
              </tr>
              <tr>
                <td width="120" style="padding:12px 16px;font-size:11px;font-weight:700;letter-spacing:0.8px;text-transform:uppercase;color:#6b7280;white-space:nowrap;background-color:#f9fafb;">Service</td>
                <td style="padding:12px 16px;font-size:14px;color:#111827;">$serviceBadge</td>
              </tr>
            </table>

            <!-- Message -->
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;">
              <tr>
                <td style="padding-bottom:8px;">
                  <p style="margin:0;font-size:11px;font-weight:700;letter-spacing:0.8px;text-transform:uppercase;color:#6b7280;">Message</p>
                </td>
              </tr>
              <tr>
                <td style="background-color:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:16px 18px;">
                  <p style="margin:0;font-size:14px;color:#374151;line-height:1.7;">$eMessage</p>
                </td>
              </tr>
            </table>

            <!-- Reply button -->
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:32px;">
              <tr>
                <td align="center">
                  <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                    <tr>
                      <td style="border-radius:8px;background-color:#1a6b3c;">
                        <a href="mailto:$eEmail?subject=$replySubject"
                          style="display:inline-block;padding:12px 28px;font-size:14px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:8px;">
                          &#9993;&nbsp; Reply to $eName
                        </a>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>

          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="background-color:#f9fafb;border-top:1px solid #e5e7eb;padding:20px 36px;">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
              <tr>
                <td>
                  <p style="margin:0;font-size:11px;color:#9ca3af;line-height:1.6;">
                    Submitted via jnv.co.za &bull; $submittedAt
                  </p>
                  <p style="margin:4px 0 0;font-size:11px;color:#9ca3af;">
                    Sent from the JNV website contact form. Reply directly to reach the enquirer.
                  </p>
                </td>
                <td align="right" valign="middle">
                  <p style="margin:0;font-size:11px;font-weight:700;color:#1a6b3c;letter-spacing:1px;">JNV</p>
                </td>
              </tr>
            </table>
          </td>
        </tr>

      </table>
      <!-- /Card -->

      <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="600" style="max-width:600px;width:100%;">
        <tr>
          <td align="center" style="padding:16px 0 0;">
            <p style="margin:0;font-size:11px;color:#9ca3af;">
              &copy; $year JNV Training and Development. All rights reserved.
            </p>
          </td>
        </tr>
      </table>

    </td>
  </tr>
</table>

<!--[if mso|IE]></td></tr></table><![endif]-->

</body>
</html>
HTML;
    }
}
