<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PanelSwitchController extends Controller
{
    public function switch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'to' => ['required', 'string', 'in:customer,seller'],
        ]);
        $user = $request->user();
        $to = $validated['to'];

        if ($to === 'customer') {
            if (! $user->canAccessCustomerPanel()) {
                abort(403);
            }
            $request->session()->put('panel_context', 'customer');

            return redirect('/painel-cliente');
        }

        if (! $user->canSwitchToSellerPanel() && ! $user->needsOnboardingAsSeller()) {
            abort(403);
        }
        if ($user->needsOnboardingAsSeller()) {
            return redirect()->route('cadastro.infoprodutor');
        }

        $request->session()->put('panel_context', 'seller');

        return redirect('/dashboard');
    }
}
