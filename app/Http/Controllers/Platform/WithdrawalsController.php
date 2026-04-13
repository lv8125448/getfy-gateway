<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class WithdrawalsController extends Controller
{
    private const WITHDRAWAL_STATUS_OPTIONS = ['all', 'pending', 'paid', 'rejected'];

    public function index(Request $request): Response
    {
        $withdrawalStatus = $request->query('withdrawal_status', 'all');
        if (! in_array($withdrawalStatus, self::WITHDRAWAL_STATUS_OPTIONS, true)) {
            $withdrawalStatus = 'all';
        }

        $withdrawalsPaginator = new LengthAwarePaginator([], 0, 40, 1, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        if (Schema::hasTable('withdrawals')) {
            $wq = Withdrawal::query()
                ->with(['tenantOwner:id,name,email'])
                ->orderByDesc('created_at');
            if ($withdrawalStatus !== 'all') {
                $wq->where('status', $withdrawalStatus);
            }
            $withdrawalsPaginator = $wq->paginate(40)->withQueryString()->through(function (Withdrawal $w) {
                return [
                    'id' => $w->id,
                    'tenant_id' => $w->tenant_id,
                    'infoprodutor_name' => $w->tenantOwner?->name ?? '—',
                    'infoprodutor_email' => $w->tenantOwner?->email,
                    'amount' => (float) $w->amount,
                    'fee_amount' => (float) ($w->fee_amount ?? 0),
                    'net_amount' => (float) ($w->net_amount ?? 0),
                    'bucket' => $w->bucket ?? 'pix',
                    'status' => (string) $w->status,
                    'notes' => $w->notes,
                    'created_at' => $w->created_at?->toIso8601String(),
                    'payout_manual' => (bool) $w->payout_manual,
                    'payout_provider' => $w->payout_provider,
                    'payout_external_id' => $w->payout_external_id,
                    'payout_last_error' => is_array($w->payout_meta) ? ($w->payout_meta['last_error'] ?? null) : null,
                    'payout_last_attempt_at' => is_array($w->payout_meta) ? ($w->payout_meta['last_attempt_at'] ?? null) : null,
                ];
            });
        }

        return Inertia::render('Platform/Withdrawals/Index', [
            'withdrawals' => $withdrawalsPaginator,
            'filters' => [
                'withdrawal_status' => $withdrawalStatus,
            ],
        ]);
    }
}
