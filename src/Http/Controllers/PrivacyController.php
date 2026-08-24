<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Liberu\Ecommerce\CustomerServiceWorkspace\Actions\ForgetParticipant;
use Liberu\Ecommerce\CustomerServiceWorkspace\Actions\RedactResolvedBefore;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Present;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Scope;
use Liberu\Ecommerce\CustomerServiceWorkspace\Queries\ExportParticipantRecord;

/**
 * Export and erasure name the same rows, and retention is refused rather than
 * quietly run against nothing when no window is configured.
 */
final class PrivacyController extends AgentController
{
    protected array $scopes = [
        'export' => Scope::PRIVACY,
        'forget' => Scope::PRIVACY,
        'redact' => Scope::PRIVACY,
    ];

    public function export(Request $request, ExportParticipantRecord $export): JsonResponse
    {
        $input = $this->validated($request, ['participant_ref' => ['required', 'string', 'max:255']]);

        return new JsonResponse([
            'data' => Present::participantRecord($export($this->tenantId(), $this->string($input, 'participant_ref'))),
        ]);
    }

    public function forget(Request $request, ForgetParticipant $forget): JsonResponse
    {
        $input = $this->validated($request, ['participant_ref' => ['required', 'string', 'max:255']]);

        return $this->respond($forget($this->tenantId(), $this->string($input, 'participant_ref')));
    }

    /** Whether a window is a window is the domain's rule, so `days` is only typed here. */
    public function redact(Request $request, RedactResolvedBefore $redact): JsonResponse
    {
        $input = $this->validated($request, ['days' => ['nullable', 'integer']]);
        $days = ($input['days'] ?? null) === null ? null : $this->integer($input, 'days');

        return $this->respond($redact($this->tenantId(), $days));
    }
}
