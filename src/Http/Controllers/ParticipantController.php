<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;

/**
 * A customer arrives with the claim the module issued them, and with no
 * credential. Their merchant comes from a request attribute the host set when it
 * resolved the storefront host — never from the body, and never defaulted.
 *
 * The claim travels in a header. Not in the path: the host put its only secret
 * there and it landed in every access log and every Referer.
 */
abstract class ParticipantController extends Controller
{
    public const CLAIM_HEADER = 'X-Participant-Claim';

    protected function admit(string $method): ?JsonResponse
    {
        $attribute = Config::get('customer-service-workspace-api.participant.tenant_attribute', 'tenant_id');
        $merchant = Request::instance()->attributes->get(is_string($attribute) ? $attribute : 'tenant_id');

        if (! is_scalar($merchant) || (string) $merchant === '') {
            return $this->refuse(503, 'no_merchant_resolved', 'This deployment resolved no merchant for this request, so nothing was read and nothing was written.');
        }

        $this->holdTenant((string) $merchant);

        return null;
    }

    /**
     * A missing claim and a wrong claim are the same refusal, because the domain
     * answers both with one 404. There is nothing to check here.
     */
    protected function claim(): string
    {
        return (string) Request::header(self::CLAIM_HEADER, '');
    }

    /**
     * The host names the person the way it names the merchant. This package
     * mints no participant reference: a third opaque value nobody could use
     * would be the wave's shaping fault repeated one identifier along.
     */
    protected function participantRef(): ?string
    {
        $attribute = Config::get('customer-service-workspace-api.participant.ref_attribute', 'participant_ref');
        $reference = Request::instance()->attributes->get(is_string($attribute) ? $attribute : 'participant_ref');

        return is_scalar($reference) && (string) $reference !== '' ? (string) $reference : null;
    }
}
