<?php

namespace Tests\Feature\Sms;

use App\Contracts\SmsGateway;
use App\Jobs\SendPasswordResetCodeSms;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BulkSmsBdGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_gateway_posts_to_bulksmsbd_with_normalized_number(): void
    {
        config([
            'services.bulksmsbd.api_key' => 'test-key',
            'services.bulksmsbd.sender_id' => '8809',
        ]);
        Http::fake([
            '*' => Http::response(['response_code' => 202, 'success_message' => 'Accepted']),
        ]);

        $sent = app(SmsGateway::class)->send('01712345678', 'Your code is 123456');

        $this->assertTrue($sent);
        Http::assertSent(function ($request): bool {
            return $request['api_key'] === 'test-key'
                && $request['senderid'] === '8809'
                && $request['number'] === '8801712345678'
                && str_contains($request['message'], '123456');
        });
    }

    public function test_gateway_returns_false_when_credentials_missing(): void
    {
        config(['services.bulksmsbd.api_key' => null, 'services.bulksmsbd.sender_id' => null]);
        Http::fake();

        $this->assertFalse(app(SmsGateway::class)->send('01712345678', 'hi'));
        Http::assertNothingSent();
    }

    public function test_gateway_returns_false_on_rejection(): void
    {
        config(['services.bulksmsbd.api_key' => 'k', 'services.bulksmsbd.sender_id' => '8809']);
        Http::fake(['*' => Http::response(['response_code' => 1001, 'error_message' => 'Invalid'])]);

        $this->assertFalse(app(SmsGateway::class)->send('01712345678', 'hi'));
    }

    public function test_sms_job_retries_when_gateway_refuses(): void
    {
        $this->expectException(\RuntimeException::class);

        $sms = $this->mock(SmsGateway::class);
        $sms->shouldReceive('send')->once()->andReturnFalse();

        $user = User::factory()->create(['phone' => '01712345678']);

        (new SendPasswordResetCodeSms($user, '123456', 15))->handle($sms);
    }
}
