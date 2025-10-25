<?php

declare(strict_types=1);

namespace AtelliTech\Hyperf\Utils\Core\Validation;

use Exception;

class ValidationException extends Exception
{
    protected int $statusCode = 422; // HTTP 422 Unprocessable Entity

    protected string $errorCode = 'VALIDATION_ERROR';

    /**
     * @var array<string, array<int, string>>
     */
    protected array $errors;

    /**
     * @param array<string, array<int, string>> $errors
     */
    public function __construct(array $errors)
    {
        $this->errors = $errors;

        // Format error messages
        $lines = [];
        foreach ($errors as $field => $messages) {
            $lines[] = sprintf('%s: %s', $field, implode(', ', $messages));
        }
        $message = implode('; ', $lines);

        parent::__construct($message, $this->statusCode);
    }

    /**
     * Get structured validation errors.
     *
     * @return array<string, array<int, string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
