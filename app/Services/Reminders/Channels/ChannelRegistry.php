<?php

namespace App\Services\Reminders\Channels;

use App\Enums\ReminderChannelType;

class ChannelRegistry
{
    public function for(ReminderChannelType $type): ReminderChannel
    {
        return match ($type) {
            ReminderChannelType::Email => app(MailChannel::class),
            ReminderChannelType::WhatsApp => new NotConfiguredChannel('WhatsApp'),
            ReminderChannelType::Sms => new NotConfiguredChannel('SMS'),
        };
    }
}
