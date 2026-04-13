<?php

namespace App\Http\Controllers;

use App\Mail\CoproductionInvitationMail;
use App\Models\Product;
use App\Models\ProductCoproducer;
use App\Models\User;
use App\Services\PlatformTransactionalMailService;
use App\Services\TeamAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductCoproductionController extends Controller
{
    public function __construct(
        protected PlatformTransactionalMailService $mailService
    ) {}

    public function store(Request $request, Product $produto): RedirectResponse
    {
        $this->authorizeProduct($produto);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'commission_percent' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'commission_on_direct_sales' => ['boolean'],
            'commission_on_affiliate_sales' => ['boolean'],
            'duration_preset' => ['required', 'string', Rule::in(array_merge([ProductCoproducer::DURATION_ETERNAL], ProductCoproducer::DURATION_DAYS))],
        ]);

        $email = ProductCoproducer::normalizeEmail($validated['email']);
        $owner = auth()->user()->kycSubjectUser();
        $ownerEmail = ProductCoproducer::normalizeEmail((string) $owner->email);

        if ($email === $ownerEmail) {
            return back()->withErrors(['email' => 'Informe o e-mail de outro infoprodutor, não o seu.']);
        }

        $inviterUser = auth()->user();
        $inviterId = (int) $inviterUser->id;

        $existingUser = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($existingUser !== null && ! $existingUser->isInfoprodutor()) {
            return back()->withErrors(['email' => 'Este e-mail pertence a um usuário que não é infoprodutor.']);
        }

        if (ProductCoproducer::query()
            ->where('product_id', $produto->id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereIn('status', [ProductCoproducer::STATUS_PENDING, ProductCoproducer::STATUS_ACTIVE])
            ->exists()) {
            return back()->withErrors(['email' => 'Já existe convite ou co-produção ativa para este e-mail.']);
        }

        $sumPct = (float) ProductCoproducer::query()
            ->where('product_id', $produto->id)
            ->whereIn('status', [ProductCoproducer::STATUS_PENDING, ProductCoproducer::STATUS_ACTIVE])
            ->sum('commission_percent');

        if (round($sumPct + (float) $validated['commission_percent'], 2) > 100.01) {
            return back()->withErrors(['commission_percent' => 'A soma das comissões dos co-produtores não pode exceder 100%.']);
        }

        if ($produto->affiliate_enabled && (float) $produto->affiliate_commission_percent > 0 && $request->boolean('commission_on_affiliate_sales', false)) {
            $sumCoproAffiliate = (float) ProductCoproducer::query()
                ->where('product_id', $produto->id)
                ->whereIn('status', [ProductCoproducer::STATUS_PENDING, ProductCoproducer::STATUS_ACTIVE])
                ->where('commission_on_affiliate_sales', true)
                ->sum('commission_percent');
            if (round($sumCoproAffiliate + (float) $validated['commission_percent'] + (float) $produto->affiliate_commission_percent, 2) > 100.01) {
                return back()->withErrors(['commission_percent' => 'A soma das comissões em vendas por afiliado (co-produtores + afiliado) não pode exceder 100%.']);
            }
        }

        $token = Str::random(48);

        $invitation = ProductCoproducer::query()->create([
            'product_id' => $produto->id,
            'inviter_user_id' => $inviterId,
            'co_producer_user_id' => null,
            'email' => $email,
            'status' => ProductCoproducer::STATUS_PENDING,
            'token' => $token,
            'commission_percent' => $validated['commission_percent'],
            'commission_on_direct_sales' => $request->boolean('commission_on_direct_sales', true),
            'commission_on_affiliate_sales' => $request->boolean('commission_on_affiliate_sales', false),
            'duration_preset' => $validated['duration_preset'],
        ]);

        $branding = [
            'app_name' => config('getfy.app_name', 'Getfy'),
            'theme_primary' => config('getfy.theme_primary', '#00cc00'),
        ];

        $acceptUrl = url('/coproducao/convite/'.$token);
        $registerUrl = url('/cadastro?coproducer_invite='.$token);

        $this->mailService->send(
            new CoproductionInvitationMail(
                $invitation,
                $produto,
                $inviterUser,
                $branding,
                $acceptUrl,
                $registerUrl
            ),
            $email
        );

        return back()->with('success', 'Convite enviado para '.$email.'.');
    }

    public function destroy(Product $produto, ProductCoproducer $coproducer): RedirectResponse
    {
        $this->authorizeProduct($produto);

        if ($coproducer->product_id !== $produto->id) {
            abort(404);
        }

        if (in_array($coproducer->status, [ProductCoproducer::STATUS_REVOKED, ProductCoproducer::STATUS_DECLINED], true)) {
            return back()->with('success', 'Co-produção já estava encerrada.');
        }

        $coproducer->update(['status' => ProductCoproducer::STATUS_REVOKED]);

        return back()->with('success', 'Co-produção revogada.');
    }

    private function authorizeProduct(Product $produto): void
    {
        $tenantId = auth()->user()->tenant_id;
        if ($produto->tenant_id !== $tenantId) {
            abort(403);
        }

        if (auth()->user()->isTeam()) {
            $allowed = app(TeamAccessService::class)->allowedProductIdsFor(auth()->user());
            if (! in_array($produto->id, $allowed, true)) {
                abort(403);
            }
        }
    }
}
