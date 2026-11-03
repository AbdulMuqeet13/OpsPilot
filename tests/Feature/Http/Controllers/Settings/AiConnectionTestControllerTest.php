<?php

use App\Enums\AiFailureReason;
use App\Enums\AiFeature;
use App\Models\AiUsageLog;
use App\Models\User;
use App\Services\AI\Testing\FakeAiClient;

use function Pest\Laravel\actingAs;

it('streams the reply as server-sent events and logs the call', function () {
    FakeAiClient::install()->queueText('Connected and ready.');
    $admin = User::factory()->create();

    $response = actingAs($admin)->post(route('ai-connection-test.store'));

    $response->assertOk()->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');
    $content = $response->streamedContent();
    $log = AiUsageLog::sole();

    expect($content)
        ->toContain("event: delta\ndata: {\"text\":\"Connected \"}")
        ->toContain("event: done\ndata: {\"text\":\"Connected and ready.\",\"usageLogId\":{$log->id}}")
        ->and($log->feature)->toBe(AiFeature::ConnectionTest)
        ->and($log->user_id)->toBe($admin->id);
});

it('sends a friendly error event when the provider fails mid-request', function () {
    FakeAiClient::install()->queueFailure(AiFailureReason::RateLimited);

    $content = actingAs(User::factory()->create())
        ->post(route('ai-connection-test.store'))
        ->streamedContent();

    expect($content)->toContain("event: error\ndata: {\"message\":\"The AI service is busy right now. Please try again in a minute.\"}");
});

it('returns 503 with the budget message once the demo cap is reached', function () {
    config(['demo.enabled' => true, 'demo.daily_ai_spend_cap_usd' => 0.50]);
    $fake = FakeAiClient::install();
    AiUsageLog::factory()->costing(0.50)->create();

    actingAs(User::factory()->create())
        ->postJson(route('ai-connection-test.store'))
        ->assertStatus(503)
        ->assertJson(['message' => AiFailureReason::SpendCapReached->userMessage()]);

    $fake->assertNothingSent();
});

it('forbids agents', function () {
    $fake = FakeAiClient::install();

    actingAs(User::factory()->agent()->create())
        ->post(route('ai-connection-test.store'))
        ->assertForbidden();

    $fake->assertNothingSent();
});

it('rate limits each user in demo mode', function () {
    config(['demo.enabled' => true, 'demo.ai_rate_limits.per_user_per_minute' => 2]);
    FakeAiClient::install()->queueText('one', 'two');
    $admin = User::factory()->create();

    actingAs($admin)->post(route('ai-connection-test.store'))->assertOk();
    actingAs($admin)->post(route('ai-connection-test.store'))->assertOk();

    actingAs($admin)
        ->postJson(route('ai-connection-test.store'))
        ->assertTooManyRequests()
        ->assertJson(['message' => AiFailureReason::UsageLimitReached->userMessage()]);
});

it('rate limits each IP address across users in demo mode', function () {
    config(['demo.enabled' => true, 'demo.ai_rate_limits.per_ip_per_hour' => 1]);
    FakeAiClient::install()->queueText('one');

    actingAs(User::factory()->create())->post(route('ai-connection-test.store'))->assertOk();

    actingAs(User::factory()->create())
        ->postJson(route('ai-connection-test.store'))
        ->assertTooManyRequests();
});
