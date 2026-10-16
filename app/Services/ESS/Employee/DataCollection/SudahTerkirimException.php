<?php

namespace App\Services\ESS\Employee\DataCollection;

use RuntimeException;

class SudahTerkirimException extends RuntimeException
{
    public function __construct(string $message = 'Data sudah pernah dikirim dan terkunci.')
    {
        parent::__construct($message);
    }
}
