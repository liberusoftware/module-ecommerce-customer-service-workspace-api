<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http;

/**
 * The abilities an agent's credential carries. Asking another module to refund
 * or cancel is its own ability: a token that may answer a customer must not
 * thereby be able to move their money, or to erase them.
 */
final class Scope
{
    public const READ = 'customer-service:read';

    public const WORK = 'customer-service:work';

    public const ACT = 'customer-service:act';

    public const PRIVACY = 'customer-service:privacy';

    /** @return array<int, string> */
    public static function all(): array
    {
        return [self::READ, self::WORK, self::ACT, self::PRIVACY];
    }
}
