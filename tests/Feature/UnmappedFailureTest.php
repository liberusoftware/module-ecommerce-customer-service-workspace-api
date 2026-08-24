<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Throttle;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Tests\Fixtures\ProbeController;

it('lets a throwable nobody classified bubble rather than dressing it as a 4xx', function (): void {
    Route::middleware(Throttle::for(Throttle::AGENT))
        ->get(api('probe/explodes'), [ProbeController::class, 'explodes']);

    $this->withoutExceptionHandling();

    $this->actingAs(agent())->getJson(api('probe/explodes'));
})->throws(LogicException::class, 'something nobody planned for');
