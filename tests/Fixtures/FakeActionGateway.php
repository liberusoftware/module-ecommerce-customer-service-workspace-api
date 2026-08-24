<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Api\Tests\Fixtures;

use Liberu\Ecommerce\CustomerServiceWorkspace\Contracts\ActionGateway;
use Liberu\Ecommerce\CustomerServiceWorkspace\Data\ActionReceipt;
use RuntimeException;

final class FakeActionGateway implements ActionGateway
{
    public function __construct(public bool $accepted = true, public bool $throws = false) {}

    /** @param  array<string, mixed>  $payload */
    public function submit(string $tenantId, string $kind, string $targetRef, array $payload): ActionReceipt
    {
        if ($this->throws) {
            throw new RuntimeException('the owning module is not answering');
        }

        return new ActionReceipt($this->accepted, $this->accepted ? 'remote-1' : null, $this->accepted ? null : 'that order has shipped');
    }
}
