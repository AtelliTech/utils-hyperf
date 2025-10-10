<?php

declare(strict_types=1);

namespace AtelliTech\Hyperf\Utils\Core\Validation;

use InvalidArgumentException;

class ValidationException extends InvalidArgumentException
{
    /**
     * Constructor.
     *
     * @param array<string, mixed> $errors
     */
    public function __construct(array $errors)
    {
        $message = 'Validation failed: ' . json_encode($errors, JSON_UNESCAPED_UNICODE);
        parent::__construct($message);
    }

    /**
     * Get validation errors.
     *
     * @return array<string, mixed>
     */
    public function getErrors(): array
    {
        return json_decode($this->getMessage(), true) ?? [];
    }
}
