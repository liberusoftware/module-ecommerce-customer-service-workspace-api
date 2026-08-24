<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Present;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Scope;
use Liberu\Ecommerce\CustomerServiceWorkspace\Queries\FindConversation;
use Liberu\Ecommerce\CustomerServiceWorkspace\Queries\MeasureService;

/** Every figure is a subtraction over recorded timestamps, and a null is never a zero. */
final class MeasurementController extends AgentController
{
    protected array $scopes = [
        'show' => Scope::READ,
        'summary' => Scope::READ,
    ];

    public function show(string $reference, FindConversation $find, MeasureService $measure): JsonResponse
    {
        return new JsonResponse([
            'data' => Present::measurement($measure($this->tenantId(), $find($this->tenantId(), $reference))),
        ]);
    }

    public function summary(MeasureService $measure): JsonResponse
    {
        return new JsonResponse(['data' => Present::summary($measure->across($this->tenantId()))]);
    }
}
