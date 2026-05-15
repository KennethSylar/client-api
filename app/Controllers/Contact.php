<?php

namespace App\Controllers;

use App\Application\Core\Commands\SendContactEnquiryCommand;

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

        try {
            service('sendContactEnquiryHandler')->handle(new SendContactEnquiryCommand(
                name:    $name,
                email:   $email,
                phone:   $phone,
                service: $service,
                message: $message,
            ));
        } catch (\Exception $e) {
            log_message('error', 'Contact enquiry failed: ' . $e->getMessage());
            return $this->error('Failed to send message. Please try again later.', 500);
        }

        return $this->ok();
    }
}
