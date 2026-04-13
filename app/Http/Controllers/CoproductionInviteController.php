<?php

namespace App\Http\Controllers;

use App\Models\ProductCoproducer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CoproductionInviteController extends Controller
{
    public function show(string $token): Response
    {
        $invitation = ProductCoproducer::query()
            ->where('token', $token)
            ->with(['product:id,name,tenant_id', 'inviter:id,name,email'])
            ->first();

        if ($invitation === null) {
            abort(404);
        }

        if (in_array($invitation->status, [ProductCoproducer::STATUS_REVOKED, ProductCoproducer::STATUS_DECLINED], true)) {
            return Inertia::render('Coproduction/Invite', [
                'invalid' => true,
                'message' => 'Este convite não está mais disponível.',
            ]);
        }

        if ($invitation->status === ProductCoproducer::STATUS_EXPIRED) {
            return Inertia::render('Coproduction/Invite', [
                'invalid' => true,
                'message' => 'Este convite expirou.',
            ]);
        }

        $user = auth()->user();
        $emailMatch = $user instanceof User
            && ProductCoproducer::normalizeEmail((string) $user->email) === $invitation->email;

        return Inertia::render('Coproduction/Invite', [
            'invalid' => false,
            'token' => $token,
            'invitation' => [
                'status' => $invitation->status,
                'email' => $invitation->email,
                'commission_percent' => (float) $invitation->commission_percent,
                'commission_on_direct_sales' => $invitation->commission_on_direct_sales,
                'commission_on_affiliate_sales' => $invitation->commission_on_affiliate_sales,
                'duration_preset' => $invitation->duration_preset,
                'product_name' => $invitation->product?->name ?? 'Produto',
                'inviter_name' => $invitation->inviter?->name ?? '',
            ],
            'can_accept' => $invitation->status === ProductCoproducer::STATUS_PENDING
                && $user instanceof User
                && $emailMatch
                && $user->isInfoprodutor(),
            'auth_email' => $user?->email,
            'login_url' => url('/login').'?redirect='.urlencode(url('/coproducao/convite/'.$token)),
            'register_url' => url('/cadastro?coproducer_invite='.$token),
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = ProductCoproducer::query()
            ->where('token', $token)
            ->with('product')
            ->firstOrFail();

        if ($invitation->status !== ProductCoproducer::STATUS_PENDING) {
            return redirect()->route('coproduction.invite.show', ['token' => $token])
                ->with('error', 'Este convite já foi respondido ou não está mais pendente.');
        }

        $user = $request->user();
        if (! $user instanceof User || ! $user->isInfoprodutor()) {
            return redirect()->route('login', ['redirect' => url('/coproducao/convite/'.$token)])
                ->with('error', 'Faça login com uma conta de infoprodutor para aceitar.');
        }

        if (ProductCoproducer::normalizeEmail((string) $user->email) !== $invitation->email) {
            return back()->withErrors(['email' => 'Entre com a conta cujo e-mail é '.$invitation->email.'.']);
        }

        $productTenantId = (int) ($invitation->product?->tenant_id ?? 0);
        if ($productTenantId === (int) $user->tenant_id) {
            return back()->withErrors(['email' => 'Você não pode aceitar co-produção do próprio produto.']);
        }

        if (ProductCoproducer::query()
            ->where('product_id', $invitation->product_id)
            ->where('co_producer_user_id', $user->id)
            ->where('status', ProductCoproducer::STATUS_ACTIVE)
            ->where('id', '!=', $invitation->id)
            ->exists()) {
            return back()->withErrors(['email' => 'Você já é co-produtor ativo deste produto.']);
        }

        $invitation->applyAcceptance($user);

        return redirect()->route('dashboard')->with('success', 'Co-produção aceita. Suas comissões serão creditadas na carteira conforme as vendas.');
    }
}
