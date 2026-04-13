<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class UsersController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Users/Create');
    }

    public function index(): Response
    {
        $users = User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_INFOPRODUTOR])
            ->orderByRaw("role = ? DESC", [User::ROLE_ADMIN])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'avatar', 'role', 'created_at'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'avatar_url' => $u->avatar ? app(\App\Services\StorageService::class)->url($u->avatar) : null,
                'role' => $u->role,
                'is_master' => $u->role === User::ROLE_ADMIN,
                'created_at' => $u->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Users/Index', [
            'users' => $users,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'email.unique' => 'Este e-mail já está em uso.',
            'password.confirmed' => 'A confirmação da senha não confere.',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => User::ROLE_INFOPRODUTOR,
        ]);

        $user->update(['tenant_id' => $user->id]);

        return redirect()->route('usuarios.index')->with('success', 'Infoprodutor cadastrado com sucesso.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->role === User::ROLE_ADMIN) {
            abort(403, 'A conta Master (admin) não pode ser excluída.');
        }

        if ($user->role !== User::ROLE_INFOPRODUTOR) {
            abort(403, 'Apenas infoprodutores podem ser excluídos por esta ação.');
        }

        $user->delete();

        return redirect()->route('usuarios.index')->with('success', 'Usuário excluído.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ], [
            'email.unique' => 'Este e-mail já está em uso.',
            'password.confirmed' => 'A confirmação da senha não confere.',
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        $user->save();

        return redirect()->route('usuarios.index')->with('success', 'Usuário atualizado.');
    }
}
