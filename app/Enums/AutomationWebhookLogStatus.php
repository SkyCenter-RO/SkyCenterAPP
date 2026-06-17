<?php

namespace App\Enums;

enum AutomationWebhookLogStatus: string
{
    case RECEIVED = 'received';
    case PROCESSED = 'processed';
    case ERROR = 'error';
}
