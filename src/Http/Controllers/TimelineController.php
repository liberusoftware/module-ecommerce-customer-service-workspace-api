<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Present;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Scope;
use Liberu\Ecommerce\CustomerServiceWorkspace\Queries\AssembleTimeline;

/**
 * Assembled at the moment of reading. A source nobody bound is named in
 * `skipped` and the answer says it is incomplete, so a short timeline cannot be
 * read as a customer who did nothing.
 */
final class TimelineController extends AgentController
{
    protected array $scopes = ['show' => Scope::READ];

    public function show(string $subjectKind, string $subjectRef, AssembleTimeline $assemble): JsonResponse
    {
        return new JsonResponse(['data' => Present::timeline($assemble($this->tenantId(), $subjectKind, $subjectRef))]);
    }
}
