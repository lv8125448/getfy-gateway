<?php

namespace Tests\Feature;

use App\Jobs\ReconcileWooviWithdrawalJob;
use App\Models\GatewayCredential;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\WithdrawalAutoPayoutService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WooviPayoutFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_auto_woovi_persists_pending_and_dispatches_reconcile_job(): void
    {
        if (! Schema::hasTable('withdrawals')) {
            $this->markTestSkipped('withdrawals table');
        }

        GatewayCredential::query()->whereIn('gateway_slug', ['cajupay', 'spacepag'])->delete();

        $cred = GatewayCredential::query()->firstOrNew([
            'tenant_id' => null,
            'gateway_slug' => 'woovi',
        ]);
        $cred->is_connected = true;
        $cred->setEncryptedCredentials([
            'app_id' => 'app-test',
            'from_pix_key' => 'origem@pix.com',
        ]);
        $cred->save();

        Http::fake([
            'https://api.woovi.com/api/v1/transfer' => Http::response([
                'transaction' => [
                    'transactionID' => 'woovi-tx-99',
                    'status' => 'PENDING',
                ],
            ], 200),
        ]);

        $seller = User::factory()->create(['role' => User::ROLE_INFOPRODUTOR]);
        $seller->forceFill([
            'tenant_id' => $seller->id,
            'payout_settings' => [
                'woovi_pix_key' => 'destino@pix.com',
                'woovi_pix_key_type' => 'email',
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
        $this->assertSame('woovi', $fresh->payout_provider);
        $this->assertSame('woovi-tx-99', $fresh->payout_external_id);
        $this->assertSame('pending', $fresh->status);

        Queue::assertPushed(ReconcileWooviWithdrawalJob::class);

        $cred->delete();
    }

    public function test_auto_woovi_transfer_value_includes_admin_fee_payout_brl(): void
    {
        if (! Schema::hasTable('withdrawals')) {
            $this->markTestSkipped('withdrawals table');
        }

        GatewayCredential::query()->whereIn('gateway_slug', ['cajupay', 'spacepag'])->delete();

        $cred = GatewayCredential::query()->firstOrNew([
            'tenant_id' => null,
            'gateway_slug' => 'woovi',
        ]);
        $cred->is_connected = true;
        $cred->setEncryptedCredentials([
            'app_id' => 'app-test',
            'from_pix_key' => 'origem@pix.com',
            'woovi_admin_fee_payout_brl' => '2',
        ]);
        $cred->save();

        Http::fake([
            'https://api.woovi.com/api/v1/transfer' => Http::response([
                'transaction' => [
                    'transactionID' => 'woovi-tx-fee',
                    'status' => 'PENDING',
                ],
            ], 200),
        ]);

        $seller = User::factory()->create(['role' => User::ROLE_INFOPRODUTOR]);
        $seller->forceFill([
            'tenant_id' => $seller->id,
            'payout_settings' => [
                'woovi_pix_key' => 'destino@pix.com',
                'woovi_pix_key_type' => 'email',
            ],
        ])->save();

        $w = Withdrawal::query()->create([
            'tenant_id' => $seller->id,
            'user_id' => $seller->id,
            'amount' => 20,
            'fee_amount' => 4,
            'net_amount' => 16,
            'bucket' => 'pix',
            'status' => 'pending',
            'currency' => 'BRL',
        ]);

        $auto = app(WithdrawalAutoPayoutService::class)->attemptAutoPayout($w->fresh());

        $this->assertTrue($auto['ok'] ?? false);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/api/v1/transfer')) {
                return false;
            }
            $data = $request->data();
            if (! is_array($data)) {
                return false;
            }

            return ($data['value'] ?? null) === 1800;
        });

        $cred->delete();
    }
}
