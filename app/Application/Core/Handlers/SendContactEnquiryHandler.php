<?php

namespace App\Application\Core\Handlers;

use App\Application\Core\Commands\SendContactEnquiryCommand;
use App\Application\Ports\MailerInterface;
use App\Domain\Core\SettingsRepositoryInterface;

final class SendContactEnquiryHandler
{
    public function __construct(
        private readonly MailerInterface            $mailer,
        private readonly SettingsRepositoryInterface $settings,
    ) {}

    public function handle(SendContactEnquiryCommand $cmd): void
    {
        $settings = $this->settings->getMany(['site_name', 'contact_email']);

        $this->mailer->sendContactEnquiry(
            $cmd->name,
            $cmd->email,
            $cmd->phone,
            $cmd->service,
            $cmd->message,
            $settings,
        );
    }
}
