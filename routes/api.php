<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Controllers\ActionRequestController;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Controllers\ConversationController;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Controllers\MeasurementController;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Controllers\NoteController;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Controllers\ParticipantConversationController;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Controllers\PrivacyController;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Controllers\QueueController;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Controllers\TimelineController;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Throttle;

$subject = ['subject_kind' => '[a-z][a-z0-9_-]*'];

// Every route in this package carries a limit. The host throttled four of its
// six chat endpoints and left the two that return a transcript and a customer's
// email open; a list of which routes deserve one is a list that falls behind.
Route::middleware(Throttle::for(Throttle::AGENT))->group(function () use ($subject): void {
    Route::get('queue', [QueueController::class, 'index'])->name('queue.index');

    Route::get('conversations/{reference}', [ConversationController::class, 'show'])->name('conversations.show');
    Route::get('conversations/{reference}/messages', [ConversationController::class, 'messages'])->name('conversations.messages.index');
    Route::post('conversations/{reference}/messages', [ConversationController::class, 'reply'])->name('conversations.messages.store');
    Route::post('conversations/{reference}/read-receipts', [ConversationController::class, 'read'])->name('conversations.read-receipts.store');
    Route::post('conversations/{reference}/assignment', [ConversationController::class, 'assign'])->name('conversations.assignment.store');
    Route::post('conversations/{reference}/resolution', [ConversationController::class, 'resolve'])->name('conversations.resolution.store');
    Route::post('conversations/{reference}/abandonment', [ConversationController::class, 'abandon'])->name('conversations.abandonment.store');
    Route::get('conversations/{reference}/measurement', [MeasurementController::class, 'show'])->name('conversations.measurement.show');
    Route::get('conversations/{reference}/action-requests', [ActionRequestController::class, 'index'])->name('conversations.action-requests.index');
    Route::post('conversations/{reference}/action-requests', [ActionRequestController::class, 'store'])->name('conversations.action-requests.store');

    Route::get('notes/{subject_kind}/{subject_ref}', [NoteController::class, 'index'])->where($subject)->name('notes.index');
    Route::post('notes/{subject_kind}/{subject_ref}', [NoteController::class, 'store'])->where($subject)->name('notes.store');
    Route::get('timelines/{subject_kind}/{subject_ref}', [TimelineController::class, 'show'])->where($subject)->name('timelines.show');

    Route::get('measurement', [MeasurementController::class, 'summary'])->name('measurement.show');

    Route::post('participant-records', [PrivacyController::class, 'export'])->name('participant-records.store');
    Route::post('erasures', [PrivacyController::class, 'forget'])->name('erasures.store');
    Route::post('redactions', [PrivacyController::class, 'redact'])->name('redactions.store');
});

// Opening mints a row in the queue and a secret served once, so it is limited
// harder than reading. The host made a new queued conversation on every page
// load and stranded the one before it.
Route::middleware(Throttle::for(Throttle::OPEN))
    ->post('participant/conversations', [ParticipantConversationController::class, 'open'])
    ->name('participant.conversations.store');

Route::middleware(Throttle::for(Throttle::PARTICIPANT))->group(function (): void {
    Route::get('participant/conversations/{reference}', [ParticipantConversationController::class, 'show'])->name('participant.conversations.show');
    Route::get('participant/conversations/{reference}/messages', [ParticipantConversationController::class, 'messages'])->name('participant.conversations.messages.index');
    Route::post('participant/conversations/{reference}/messages', [ParticipantConversationController::class, 'reply'])->name('participant.conversations.messages.store');
    Route::post('participant/conversations/{reference}/rating', [ParticipantConversationController::class, 'rate'])->name('participant.conversations.rating.store');
});
