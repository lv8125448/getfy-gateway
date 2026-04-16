<?php

namespace App\Http\Controllers;

use App\Models\RefundRequest;
use App\Services\RefundRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SellerRefundRequestsController extends Controller
{
    public function __construct(
        protected RefundRequestService $refundRequestService
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $tenantId = (int) $user->tenant_id;
        $status = $request->query('status', 'pending');
        $q = RefundRequest::query()
            ->forTenant($tenantId)
            ->with(['order.product', 'user'])
            ->orderByDesc('id')
            ->when($status !== 'all', fn ($b) => $b->where('status', $status));

        $rows = $q->paginate(20)->withQueryString();

        return Inertia::render('Reembolsos/Index', [
            'requests' => $rows,
            'filter_status' => $status,
            'pageTitle' => 'Reembolsos',
        ]);
    }

    public function approve(Request $request, RefundRequest $refundRequest): RedirectResponse
    {
        $user = $request->user();
        if ((int) $refundRequest->tenant_id !== (int) $user->tenant_id) {
            abort(403);
        }
        try {
            $this->refundRequestService->approve($user, $refundRequest);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', $e->getMessage() ?: 'Não foi possível aprovar.');
        }

        return back()->with('success', 'Reembolso processado.');
    }

    public function reject(Request $request, RefundRequest $refundRequest): RedirectResponse
    {
        $user = $request->user();
        if ((int) $refundRequest->tenant_id !== (int) $user->tenant_id) {
            abort(403);
        }
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);
        try {
            $this->refundRequestService->reject($user, $refundRequest, $validated['reason'] ?? null);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', $e->getMessage() ?: 'Não foi possível recusar.');
        }

        return back()->with('success', 'Solicitação recusada.');
    }
}
