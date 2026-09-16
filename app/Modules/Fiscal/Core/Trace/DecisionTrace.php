<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Trace;

final class DecisionTrace
{
    /** @var list<TraceEvent> */
    private array $events = [];

    /** @param array<string, mixed> $payload */
    public function add(string $type, array $payload = []): TraceEvent
    {
        $event = new TraceEvent(count($this->events) + 1, $type, $payload);
        $this->events[] = $event;

        return $event;
    }

    /** @return list<TraceEvent> */
    public function events(): array
    {
        return $this->events;
    }

    /** @return list<array<string, mixed>> */
    public function toArray(): array
    {
        return array_map(fn (TraceEvent $event): array => $event->toArray(), $this->events);
    }
}
