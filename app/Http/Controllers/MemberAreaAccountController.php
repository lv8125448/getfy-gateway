<?php

namespace App\Http\Controllers;

use App\Services\StorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class MemberAreaAccountController extends Controller
{
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);

        $user->name = $validated['name'];

        if ($request->hasFile('avatar')) {
            $storage = app(StorageService::class);
            if ($user->avatar && $storage->exists($user->avatar)) {
                $storage->delete($user->avatar);
            }
            $user->avatar = $storage->putFile('avatars', $request->file('avatar'));
        }

        $user->save();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Perfil atualizado.',
                'avatar_url' => $user->avatar ? app(StorageService::class)->url($user->avatar) : null,
            ]);
        }

        return redirect()->back()->with('success', 'Perfil atualizado.');
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ], [
            'current_password.required' => 'Informe a senha atual.',
            'password.required' => 'O campo nova senha é obrigatório.',
            'password.confirmed' => 'A confirmação da senha não confere.',
            'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'A senha atual está incorreta.',
                    'errors' => ['current_password' => ['A senha atual está incorreta.']],
                ], 422);
            }
            return redirect()->back()->withErrors(['current_password' => 'A senha atual está incorreta.']);
        }

        $user->password = Hash::make($validated['password']);
        $user->save();

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Senha alterada.']);
        }

        return redirect()->back()->with('success', 'Senha alterada.');
    }
}
