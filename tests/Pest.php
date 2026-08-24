<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Liberu\Ecommerce\CustomerServiceWorkspace\Actions\AssignAgent;
use Liberu\Ecommerce\CustomerServiceWorkspace\Actions\OpenConversation;
use Liberu\Ecommerce\CustomerServiceWorkspace\Actions\PostMessage;
use Liberu\Ecommerce\CustomerServiceWorkspace\Actions\ResolveConversation;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Controllers\ParticipantController;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Scope;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Tests\Fixtures\ApiActor;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Tests\Fixtures\FakeActionGateway;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Tests\Fixtures\FakeTimelineSource;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Tests\TestCase;
use Liberu\Ecommerce\CustomerServiceWorkspace\Data\ConversationOpened;
use Liberu\Ecommerce\CustomerServiceWorkspace\Enums\Author;
use Liberu\Ecommerce\CustomerServiceWorkspace\Models\Conversation;

uses(TestCase::class)->in('Feature');
uses(TestCase::class)->in('Unit');

/*
 * Nothing is bound by default and no test inherits a binding. A suite that
 * leaked one would prove the opposite of what half of it claims: that a source
 * nobody answered for is named rather than counted as having answered nothing.
 */
$unbind = function (): void {
    Config::set('customer-service-workspace.seams.timeline', ['orders' => null, 'payments' => null, 'shipments' => null, 'returns' => null]);
    Config::set('customer-service-workspace.seams.actions', null);
    Config::set('customer-service-workspace.retention.resolved_after_days', null);
};

uses()->beforeEach($unbind)->in('Feature');
uses()->beforeEach($unbind)->in('Unit');

/** @param  array<int, string>  $abilities */
function actor(array $abilities = [Scope::READ], ?string $merchant = 'merchant-a'): ApiActor
{
    return ApiActor::query()->create(['team_id' => $merchant, 'abilities' => $abilities]);
}

function agent(?string $merchant = 'merchant-a'): ApiActor
{
    return actor(Scope::all(), $merchant);
}

function api(string $path = ''): string
{
    return rtrim('/api/customer-service/'.ltrim($path, '/'), '/');
}

/** The headers a host's channel middleware stands behind: a merchant, and the person it named. */
function storefront(?string $participant = 'person-1', ?string $merchant = 'merchant-a'): array
{
    return array_filter([
        'X-Test-Merchant' => $merchant,
        'X-Test-Participant' => $participant,
    ], is_string(...));
}

/** @return array<string, string> */
function claimed(string $claim, ?string $merchant = 'merchant-a'): array
{
    return storefront(null, $merchant) + [ParticipantController::CLAIM_HEADER => $claim];
}

function openConversation(string $merchant = 'merchant-a', string $participant = 'person-1'): ConversationOpened
{
    return (new OpenConversation())($merchant, 'chat', $participant, 'A Customer', 'customer@example.test');
}

function conversationOf(ConversationOpened $opened): Conversation
{
    return Conversation::query()->findOrFail($opened->id);
}

function takenBy(ApiActor $agent, ConversationOpened $opened, string $merchant = 'merchant-a'): Conversation
{
    $conversation = conversationOf($opened);
    (new AssignAgent())($merchant, $conversation, (string) $agent->getKey());

    return $conversation;
}

function settled(ApiActor $agent, ConversationOpened $opened, string $merchant = 'merchant-a'): Conversation
{
    $conversation = takenBy($agent, $opened, $merchant);
    (new PostMessage())($merchant, $conversation, Author::Agent, (string) $agent->getKey(), 'Sorted.');
    (new ResolveConversation())($merchant, $conversation);

    return $conversation;
}

function bindTimeline(FakeTimelineSource ...$sources): void
{
    $bound = ['orders' => null, 'payments' => null, 'shipments' => null, 'returns' => null];

    foreach ($sources as $source) {
        $bound[$source->name] = $source;
    }

    Config::set('customer-service-workspace.seams.timeline', $bound);
}

function bindGateway(?FakeActionGateway $gateway = null): FakeActionGateway
{
    $gateway ??= new FakeActionGateway();
    Config::set('customer-service-workspace.seams.actions', $gateway);

    return $gateway;
}

/** @return array<int, string> every PHP file under src/, absolute. */
function sourceFiles(): array
{
    $paths = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__).'/src')) as $file) {
        if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
            $paths[] = $file->getPathname();
        }
    }

    sort($paths);

    return $paths;
}

/** @return array<int, string> the concrete controllers: the three bases decide who is admitted, not what is served. */
function concreteControllers(): array
{
    $bases = array_map(
        static fn (string $name): string => dirname(__DIR__).'/src/Http/Controllers/'.$name,
        ['Controller.php', 'AgentController.php', 'ParticipantController.php'],
    );

    return array_values(array_diff((array) glob(dirname(__DIR__).'/src/Http/Controllers/*.php'), $bases));
}
