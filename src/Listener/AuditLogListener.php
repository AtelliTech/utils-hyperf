<?php

declare(strict_types=1);

namespace AtelliTech\Hyperf\Utils\Listener;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Logger\LoggerFactory;
use JsonException;
use Psr\Log\LoggerInterface;
use Throwable;

class AuditLogListener implements ListenerInterface
{
    private LoggerInterface $auditLogger;

    public function __construct(
        private readonly LoggerFactory $loggerFactory,
        private readonly ConfigInterface $config,
    ) {
        $this->auditLogger = $this->loggerFactory->get('log', 'audit');
    }

    /**
     * Events to listen.
     *
     * @return array<int, class-string>
     */
    public function listen(): array
    {
        $events = $this->config->get('audit_log.events', []);

        if (! is_array($events)) {
            return [];
        }

        $classes = [];

        foreach ($events as $event) {
            if (! is_string($event)) {
                continue;
            }

            if ($event === '') {
                continue;
            }

            if (! class_exists($event)) {
                continue;
            }

            /** @var class-string $event */
            $classes[] = $event;
        }

        return $classes;
    }

    public function process(object $event): void
    {
        if (! $event instanceof AuditLogEventInterface) {
            return;
        }

        try {
            $changes = $this->resolveChanges($event);

            if ($changes === []) {
                return;
            }

            $this->storeAudit($event, $changes);
        } catch (Throwable $e) {
            $this->auditLogger->error('Failed to write audit log.', [
                'exception' => $e,
                'event_class' => $event::class,
            ]);
        }
    }

    /**
     * @return array<int, array{
     *     attribute: string,
     *     old: mixed,
     *     new: mixed
     * }>
     */
    protected function resolveChanges(AuditLogEventInterface $event): array
    {
        $changes = [];

        $originalValues = $event->getOriginalValues();
        $newValues = $event->getNewValues();

        foreach ($newValues as $key => $newValue) {
            if (! is_string($key) && ! is_int($key)) {
                continue;
            }

            $attribute = (string) $key;
            $originalValue = $originalValues[$key] ?? null;

            if ($newValue === $originalValue) {
                continue;
            }

            $changes[] = [
                'attribute' => $attribute,
                'old' => $originalValue,
                'new' => $newValue,
            ];
        }

        return $changes;
    }

    /**
     * @param array<int, array{
     *     attribute: string,
     *     old: mixed,
     *     new: mixed
     * }> $changedAttributes
     *
     * @throws JsonException
     */
    protected function storeAudit(AuditLogEventInterface $event, array $changedAttributes): void
    {
        $payload = [
            'event' => $event->getEventName(),
            'table_name' => $event->getTableName(),
            'table_id' => $event->getTableId(),
            'created_by' => $event->getUserId(),
            'created_at' => time(),
            'attributes' => $changedAttributes,
        ];

        $message = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        $this->auditLogger->info($message);
    }
}
