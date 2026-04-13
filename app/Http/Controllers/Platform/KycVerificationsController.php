<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\KycDocument;
use App\Models\User;
use App\Services\PlatformAuditService;
use App\Services\PlatformEmailNotifications;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KycVerificationsController extends Controller
{
    public function index(Request $request): Response
    {
        $filter = (string) $request->query('status', 'pending_review');

        $q = User::query()
            ->where('role', User::ROLE_INFOPRODUTOR)
            ->whereNotNull('tenant_id');

        if ($filter === 'pending_review') {
            $q->where('kyc_status', User::KYC_PENDING_REVIEW);
        } elseif ($filter === 'rejected') {
            $q->where('kyc_status', User::KYC_REJECTED);
        } elseif ($filter === 'not_submitted') {
            $q->where('kyc_status', User::KYC_NOT_SUBMITTED);
        }
        // 'all' = sem filtro extra

        $paginator = $q->orderByDesc('updated_at')->paginate(25)->withQueryString();
        $paginator->setCollection(
            $paginator->getCollection()->map(function (User $u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'person_type' => $u->person_type,
                    'kyc_status' => $u->kyc_status,
                    'updated_at' => $u->updated_at?->toIso8601String(),
                ];
            })
        );

        return Inertia::render('Platform/Kyc/Index', [
            'users' => $paginator,
            'filter' => $filter,
        ]);
    }

    public function show(User $user): Response
    {
        abort_unless($user->role === User::ROLE_INFOPRODUTOR, 404);

        $documents = $user->kycDocuments()->orderBy('kind')->get()->map(function (KycDocument $d) {
            return [
                'id' => $d->id,
                'public_token' => $d->public_token,
                'kind' => $d->kind,
                'mime' => $d->original_mime,
                'size_bytes' => $d->size_bytes,
                'download_url' => route('plataforma.kyc.document', ['document' => $d]),
            ];
        });

        return Inertia::render('Platform/Kyc/Show', [
            'merchant' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'person_type' => $user->person_type,
                'document' => $user->document,
                'company_name' => $user->company_name,
                'legal_representative_cpf' => $user->legal_representative_cpf,
                'birth_date' => $user->birth_date?->format('Y-m-d'),
                'address_zip' => $user->address_zip,
                'address_street' => $user->address_street,
                'address_number' => $user->address_number,
                'address_complement' => $user->address_complement,
                'address_neighborhood' => $user->address_neighborhood,
                'address_city' => $user->address_city,
                'address_state' => $user->address_state,
                'monthly_revenue_range' => $user->monthly_revenue_range,
                'kyc_status' => $user->kyc_status,
                'kyc_rejection_reason' => $user->kyc_rejection_reason,
                'kyc_reviewed_at' => $user->kyc_reviewed_at?->toIso8601String(),
            ],
            'documents' => $documents,
        ]);
    }

    public function approve(Request $request, User $user, PlatformEmailNotifications $platformEmailNotifications): RedirectResponse
    {
        abort_unless($user->role === User::ROLE_INFOPRODUTOR, 404);
        abort_unless($user->kyc_status === User::KYC_PENDING_REVIEW, 422);

        $user->forceFill([
            'kyc_status' => User::KYC_APPROVED,
            'kyc_rejection_reason' => null,
            'kyc_reviewed_at' => now(),
            'kyc_reviewed_by' => $request->user()?->id,
        ])->save();

        PlatformAuditService::log('platform.kyc.approved', ['merchant_id' => $user->id], $request);

        $platformEmailNotifications->kycApproved($user->fresh());

        return redirect()->route('plataforma.kyc.show', ['user' => $user->id])->with('success', 'Verificação aprovada.');
    }

    public function reject(Request $request, User $user, PlatformEmailNotifications $platformEmailNotifications): RedirectResponse
    {
        abort_unless($user->role === User::ROLE_INFOPRODUTOR, 404);
        abort_unless($user->kyc_status === User::KYC_PENDING_REVIEW, 422);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $user->forceFill([
            'kyc_status' => User::KYC_REJECTED,
            'kyc_rejection_reason' => $validated['reason'],
            'kyc_reviewed_at' => now(),
            'kyc_reviewed_by' => $request->user()?->id,
        ])->save();

        PlatformAuditService::log('platform.kyc.rejected', ['merchant_id' => $user->id, 'reason' => $validated['reason']], $request);

        $platformEmailNotifications->kycRejected($user->fresh(), $validated['reason']);

        return redirect()->route('plataforma.kyc.show', ['user' => $user->id])->with('success', 'Verificação rejeitada. O infoprodutor pode reenviar documentos.');
    }

    public function downloadDocument(Request $request, KycDocument $document): StreamedResponse|\Symfony\Component\HttpFoundation\Response
    {
        abort_unless($request->user()?->canAccessPlatformPanel(), 403);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($document->disk_path), 404);

        return $disk->response($document->disk_path, 'document', [
            'Content-Type' => $document->original_mime ?? 'application/octet-stream',
        ]);
    }
}
