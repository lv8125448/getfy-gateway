<?php

namespace App\Http\Controllers;

use App\Models\CheckoutSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutTrackingController extends Controller
{
    public function track(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_token' => ['required', 'string', 'max:64'],
            'step' => ['required', 'string', 'in:form_started,form_filled'],
            'email' => ['nullable', 'email'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $session = CheckoutSession::where('session_token', $validated['session_token'])->first();

        if (! $session) {
            return response()->json(['success' => false, 'message' => 'Sessão não encontrada.'], 404);
        }

        $step = $validated['step'];
        if ($step === CheckoutSession::STEP_FORM_FILLED && $session->step === CheckoutSession::STEP_CONVERTED) {
            return response()->json(['success' => true]);
        }

        if (in_array($session->step, [CheckoutSession::STEP_CONVERTED], true)) {
            return response()->json(['success' => true]);
        }

        $updates = ['step' => $step];
        if (! empty($validated['email'])) {
            $updates['email'] = $validated['email'];
        }
        if (array_key_exists('name', $validated)) {
            $updates['name'] = $validated['name'];
        }

        $session->update($updates);

        return response()->json(['success' => true]);
    }
}
