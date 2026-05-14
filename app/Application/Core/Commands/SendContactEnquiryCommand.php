<?php

namespace App\Application\Core\Commands;

final class SendContactEnquiryCommand
{
    public function __construct(
        public readonly string  $name,
        public readonly string  $email,
        public readonly ?string $phone,
        public readonly ?string $service,
        public readonly string  $message,
    ) {}
}
