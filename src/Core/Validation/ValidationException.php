<?php

declare(strict_types=1);

namespace AtelliTech\Hyperf\Utils\Core\Validation;

use Exception;

class ValidationException extends Exception
{
    /**
     * HTTP status code hint (optional, handled by ExceptionHandler).
     */
    protected int $statusCode = 400;

    /**
     * Machine-readable error code (optional).
     */
    protected string $errorCode = 'VALIDATION_ERROR';

    /**
     * Constructor.
     *
     * @param array<string, mixed> $errors
     */
    public function __construct(array $errors)
    {
        $message = json_encode($errors, JSON_UNESCAPED_UNICODE);
        if ($message === false) {
            $message = '未知的驗證錯誤!';
        }
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

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
