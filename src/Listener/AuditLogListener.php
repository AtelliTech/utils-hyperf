<?php

declare(strict_types=1);

namespace AtelliTech\Hyperf\Utils\Listener;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

class AuditLogListener implements ListenerInterface
{
    #[Inject()]
    private LoggerFactory $loggerFactory;

    #[Inject()]
    private ConfigInterface $config;

    private LoggerInterface $audit;

    /**
     * Events to listen.
     */
    public function listen(): array
    {
        return $this->config->get('audit_log.events', []);
    }

    /**
     * Handle the event.
     */
    public function process(object $event): void
    {
        $this->audit = $this->loggerFactory->get('log', 'audit');

        if ($event instanceof AuditLogEventInterface) {
            $this->audit($event);
        }
    }

    /**
     * Audit the given event.
     */
    protected function audit(AuditLogEventInterface $event): void
    {
        $changes = [];

        foreach ($event->getNewValues() as $key => $newValue) {
            $originalValue = $event->getOriginalValues()[$key] ?? null;
            if ($newValue !== $originalValue) {
                $changes[] = [
                    'attribute' => $key,
                    'old' => $originalValue,
                    'new' => $newValue,
                ];
            }
        }

        if (empty($changes)) {
            return;
        }

        $this->storeAudit($event, $changes, $event->getEventName());
    }

    /**
     * Store the audit log.
     *
     * @param array<int, array<string, mixed>> $changedAttributes
     */
    protected function storeAudit(AuditLogEventInterface $event, array $changedAttributes, string $eventName): void
    {
        $payload = [
            'event' => $eventName,
            'table_name' => $event->getTableName(),
            'table_id' => $event->getTableId(),
            'created_by' => $event->getUserId(),
            'created_at' => time(),
            'attributes' => $changedAttributes,
        ];

        $payload = json_encode($payload, JSON_UNESCAPED_UNICODE) . PHP_EOL;

        $this->audit->info($payload);
    }
}
