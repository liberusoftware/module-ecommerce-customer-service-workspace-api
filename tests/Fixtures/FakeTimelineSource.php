<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Api\Tests\Fixtures;

use Illuminate\Support\Carbon;
use Liberu\Ecommerce\CustomerServiceWorkspace\Contracts\TimelineSource;
use Liberu\Ecommerce\CustomerServiceWorkspace\Data\TimelineEntry;
use RuntimeException;

final class FakeTimelineSource implements TimelineSource
{
    public function __construct(public string $name = 'orders', public bool $throws = false) {}

    /** @return array<int, TimelineEntry> */
    public function entriesFor(string $tenantId, string $subjectKind, string $subjectRef): array
    {
        if ($this->throws) {
            throw new RuntimeException('that module is not answering');
        }

        return [new TimelineEntry($this->name, 'placed', Carbon::now()->subDay(), $subjectRef, ['total' => 1999])];
    }
}
