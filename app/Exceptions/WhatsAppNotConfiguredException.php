<?php

namespace App\Exceptions;

use RuntimeException;

class WhatsAppNotConfiguredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('WhatsApp provider is not configured.');
    }
}
