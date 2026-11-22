<?php

namespace App\Exceptions;

use Exception;

class PasswordResetException extends Exception
{
    public function __construct(string $message, private int $status = 422)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }
}