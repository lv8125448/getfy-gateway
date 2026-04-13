<?php

namespace Tests\Feature;

use App\Jobs\ReconcileSpacepagWithdrawalJob;
use App\Models\GatewayCredential;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\WithdrawalAutoPayoutService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SpacepagPayoutFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_webhook_payment_paid_marks_spacepag_withdrawal_paid(): void
    {
        if (! Schema::hasTable('withdrawals')) {
            $this->markTestSkipped('withdrawals table');
        }

        $seller = User::factory()->create(['role' => User::ROLE_INFOPRODUTOR]);
        $seller->forceFill(['tenant_id' => $seller->id])->save();

        $w = Withdrawal::query()->create([
            'tenant_id' => $seller->id,
            'user_id' => $seller->id,
            'amount' => 100,
            'fee_amount' => 0,
            'net_amount' => 100,
            'bucket' => 'pix',
            'status' => 'pending',
            'currency' => 'BRL',
            'payout_provider' => 'spacepag',
            'payout_external_id' => 'tx-webhook-1',
        ]);

        $response = $this->postJson('/webhooks/gateways/spacepag', [
            'event' => 'payment.paid',
            'status' => 'paid',
            'transaction_id' => 'tx-webhook-1',
            'external_id' => 'getfy-withdrawal-'.$w->id,
        ]);

        $response->assertOk()->assertJson(['received' => true]);

        $this->assertSame('paid', $w->fresh()->status);
    }

    public function test_webhook_accepts_numeric_transaction_id_json(): void
    {
        if (! Schema::hasTable('withdrawals')) {
            $this->markTestSkipped('withdrawals table');
        }

        $seller = User::factory()->create(['role' => User::ROLE_INFOPRODUTOR]);
        $seller->forceFill(['tenant_id' => $seller->id])->save();

        $w = Withdrawal::query()->create([
            'tenant_id' => $seller->id,
            'user_id' => $seller->id,
            'amount' => 100,
            'fee_amount' => 0,
            'net_amount' => 100,
            'bucket' => 'pix',
            'status' => 'pending',
            'currency' => 'BRL',
            'payout_provider' => 'spacepag',
            'payout_external_id' => '999888777',
        ]);

        $response = $this->postJson('/webhooks/gateways/spacepag', [
            'event' => 'payment.paid',
            'status' => 'paid',
            'transaction_id' => 999888777,
            'external_id' => 'getfy-withdrawal-'.$w->id,
        ]);

        $response->assertOk()->assertJson(['received' => true]);
        $this->assertSame('paid', $w->fresh()->status);
    }

    public function test_webhook_marks_paid_when_status_paid_with_payment_and_receiver_without_event(): void
    {
        if (! Schema::hasTable('withdrawals')) {
            $this->markTestSkipped('withdrawals table');
        }

        $seller = User::factory()->create(['role' => User::ROLE_INFOPRODUTOR]);
        $seller->forceFill(['tenant_id' => $seller->id])->save();

        $w = Withdrawal::query()->create([
            'tenant_id' => $seller->id,
            'user_id' => $seller->id,
            'amount' => 50,
            'fee_amount' => 0,
            'net_amount' => 50,
            'bucket' => 'pix',
            'status' => 'pending',
            'currency' => 'BRL',
            'payout_provider' => 'spacepag',
            'payout_external_id' => 'tx-no-event-1',
        ]);

        $response = $this->postJson('/webhooks/gateways/spacepag', [
            'status' => 'paid',
            'transaction_id' => 'tx-no-event-1',
            'external_id' => 'getfy-withdrawal-'.$w->id,
            'payment' => ['amount' => 50, 'liquid' => 50],
            'receiver' => ['name' => 'X', 'document' => '123', 'email' => 'a@b.co'],
        ]);

        $response->assertOk()->assertJson(['received' => true]);
        $this->assertSame('paid', $w->fresh()->status);
    }

    public function test_auto_spacepag_persists_pending_and_transaction_id(): void
    {
        if (! Schema::hasTable('withdrawals')) {
            $this->markTestSkipped('withdrawals table');
        }

        GatewayCredential::query()->where('gateway_slug', 'cajupay')->delete();

        $cred = GatewayCredential::query()->firstOrNew([
            'tenant_id' => null,
            'gateway_slug' => 'spacepag',
        ]);
        $cred->is_connected = true;
        $cred->setEncryptedCredentials([
            'public_key' => 'pk_test',
            'secret_key' => 'sk_test',
        ]);
        $cred->save();

        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, '/auth')) {
                return Http::response(['access_token' => 'jwt-test']);
            }
            if (str_contains($url, '/pixout')) {
                return Http::response([
                    'event' => 'order.created',
                    'status' => 'pending',
                    // API pode devolver transaction_id como número no JSON
                    'transaction_id' => 987654321,
                ]);
            }

            return Http::response(['message' => 'unexpected'], 500);
        });

        $seller = User::factory()->create(['role' => User::ROLE_INFOPRODUTOR]);
        $seller->forceFill([
            'tenant_id' => $seller->id,
            'payout_settings' => [
                'spacepag_pix_key' => '12345678909',
                'spacepag_pix_key_type' => 'cpf',
                'receiver_name' => 'Fulano Teste',
                'receiver_document' => '123.456.789-09',
                'receiver_email' => 'fulano@example.com',
            ],
        ])->save();

        $w = Withdrawal::query()->create([
            'tenant_id' => $seller->id,
            'user_id' => $seller->id,
            'amount' => 100,
            'fee_amount' => 0,
            'net_amount' => 100,
            'bucket' => 'pix',
            'status' => 'pending',
            'currency' => 'BRL',
        ]);

        $auto = app(WithdrawalAutoPayoutService::class)->attemptAutoPayout($w->fresh());

        $this->assertTrue($auto['ok'] ?? false);
        $this->assertTrue($auto['pending'] ?? false);

        $fresh = $w->fresh();
        $this->assertSame('spacepag', $fresh->payout_provider);
        $this->assertSame('987654321', $fresh->payout_external_id);
        $this->assertSame('pending', $fresh->status);

        Queue::assertPushed(ReconcileSpacepagWithdrawalJob::class);

        $cred->delete();
    }

    public function test_auto_spacepag_treats_500_with_transaction_id_as_success(): void
    {
        if (! Schema::hasTable('withdrawals')) {
            $this->markTestSkipped('withdrawals table');
        }

        GatewayCredential::query()->where('gateway_slug', 'cajupay')->delete();

        $cred = GatewayCredential::query()->firstOrNew([
            'tenant_id' => null,
            'gateway_slug' => 'spacepag',
        ]);
        $cred->is_connected = true;
        $cred->setEncryptedCredentials([
            'public_key' => 'pk_test',
            'secret_key' => 'sk_test',
        ]);
        $cred->save();

        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, '/auth')) {
                return Http::response(['access_token' => 'jwt-test']);
            }
            if (str_contains($url, '/pixout')) {
                return Http::response([
                    'message' => 'Internal server error',
                    'transaction_id' => 'tx-after-500',
                    'status' => 'pending',
                ], 500);
            }

            return Http::response(['message' => 'unexpected'], 500);
        });

        $seller = User::factory()->create(['role' => User::ROLE_INFOPRODUTOR]);
        $seller->forceFill([
            'tenant_id' => $seller->id,
            'payout_settings' => [
                'spacepag_pix_key' => '12345678909',
                'spacepag_pix_key_type' => 'cpf',
                'receiver_name' => 'Fulano Teste',
                'receiver_document' => '123.456.789-09',
                'receiver_email' => 'fulano@example.com',
            ],
        ])->save();

        $w = Withdrawal::query()->create([
            'tenant_id' => $seller->id,
            'user_id' => $seller->id,
            'amount' => 100,
            'fee_amount' => 0,
            'net_amount' => 100,
            'bucket' => 'pix',
            'status' => 'pending',
            'currency' => 'BRL',
        ]);

        $auto = app(WithdrawalAutoPayoutService::class)->attemptAutoPayout($w->fresh());

        $this->assertTrue($auto['ok'] ?? false);
        $this->assertSame('tx-after-500', $w->fresh()->payout_external_id);

        Queue::assertPushed(ReconcileSpacepagWithdrawalJob::class);

        $cred->delete();
    }
}
