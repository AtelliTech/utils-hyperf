<?php

declare(strict_types=1);

namespace AtelliTech\Hyperf\Utils\Listener;

interface AuditLogEventInterface
{
    /**
     * Get the name of the event.
     */
    public function getEventName(): string;

    /**
     * Get the table name associated with the event.
     */
    public function getTableName(): string;

    /**
     * Get the table ID associated with the event.
     */
    public function getTableId(): string;

    /**
     * Get the user ID who triggered the event.
     */
    public function getUserId(): string;

    /**
     * Get the old values before the event.
     *
     * @return array<string, mixed>
     */
    public function getOriginalValues(): array;

    /**
     * Get the new values after the event.
     *
     * @return array<string, mixed>
     */
    public function getNewValues(): array;
}
