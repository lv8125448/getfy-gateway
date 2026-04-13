<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\TeamAuditLog;
use App\Models\TeamRole;
use App\Models\User;
use App\Mail\TeamMemberAccessMail;
use App\Services\TenantMailConfigService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class EquipeController extends Controller
{
    private function audit(Request $request, string $action, ?string $targetType = null, $targetId = null, array $metadata = []): void
    {
        $actor = $request->user();
        $tenantId = $actor?->tenant_id;
        if (! $tenantId) {
            return;
        }

        TeamAuditLog::create([
            'tenant_id' => $tenantId,
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId !== null ? (string) $targetId : null,
            'metadata' => $metadata ?: null,
            'ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $tenantId = $user?->tenant_id;
        if (! $tenantId) {
            abort(403, 'Tenant inválido.');
        }

        $products = Product::forTenant($tenantId)->orderBy('name')->get(['id', 'name'])->map(fn (Product $p) => [
            'id' => $p->id,
            'name' => $p->name,
        ])->values()->all();

        $roles = TeamRole::query()
            ->where('tenant_id', $tenantId)
            ->with('products:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (TeamRole $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'permissions' => $r->permissions ?? [],
                'product_ids' => $r->products->pluck('id')->values()->all(),
                'products' => $r->products->map(fn (Product $p) => ['id' => $p->id, 'name' => $p->name])->values()->all(),
            ])->values()->all();

        $members = User::query()
            ->where('tenant_id', $tenantId)
            ->where('role', User::ROLE_TEAM)
            ->with('teamRole:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'team_role_id', 'created_at'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'team_role_id' => $u->team_role_id,
                'team_role_name' => $u->teamRole?->name,
                'created_at' => $u->created_at?->toIso8601String(),
            ])->values()->all();

        $logs = [];
        if ($user && $user->isAdmin()) {
            $logs = TeamAuditLog::query()
                ->where('tenant_id', $tenantId)
                ->with('actor:id,name,email')
                ->latest()
                ->limit(200)
                ->get()
                ->map(fn (TeamAuditLog $l) => [
                    'id' => $l->id,
                    'action' => $l->action,
                    'target_type' => $l->target_type,
                    'target_id' => $l->target_id,
                    'metadata' => $l->metadata ?? [],
                    'ip' => $l->ip,
                    'actor' => $l->actor ? ['id' => $l->actor->id, 'name' => $l->actor->name, 'email' => $l->actor->email] : null,
                    'created_at' => $l->created_at?->toIso8601String(),
                ])->values()->all();
        }

        return Inertia::render('Users/Equipe', [
            'products' => $products,
            'roles' => $roles,
            'members' => $members,
            'logs' => $logs,
        ]);
    }

    public function storeRole(Request $request): RedirectResponse
    {
        $tenantId = $request->user()?->tenant_id;
        if (! $tenantId) {
            abort(403, 'Tenant inválido.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'permissions' => ['nullable', 'array'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['string', 'size:36'],
        ]);

        $role = TeamRole::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'permissions' => $validated['permissions'] ?? [],
        ]);

        $productIds = array_values(array_unique(array_filter($validated['product_ids'] ?? [])));
        if ($productIds) {
            $allowed = Product::forTenant($tenantId)->whereIn('id', $productIds)->pluck('id')->all();
            $role->products()->sync($allowed);
        }

        $this->audit($request, 'team.role.created', TeamRole::class, $role->id, [
            'name' => $role->name,
            'product_ids' => $productIds,
        ]);

        return redirect()->route('usuarios.equipe')->with('success', 'Cargo criado.');
    }

    public function updateRole(Request $request, TeamRole $role): RedirectResponse
    {
        $tenantId = $request->user()?->tenant_id;
        if (! $tenantId || $role->tenant_id !== $tenantId) {
            abort(403, 'Cargo não encontrado.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'permissions' => ['nullable', 'array'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['string', 'size:36'],
        ]);

        $role->update([
            'name' => $validated['name'],
            'permissions' => $validated['permissions'] ?? [],
        ]);

        $productIds = array_values(array_unique(array_filter($validated['product_ids'] ?? [])));
        $allowed = $productIds
            ? Product::forTenant($tenantId)->whereIn('id', $productIds)->pluck('id')->all()
            : [];
        $role->products()->sync($allowed);

        $this->audit($request, 'team.role.updated', TeamRole::class, $role->id, [
            'name' => $role->name,
            'product_ids' => $allowed,
        ]);

        return redirect()->route('usuarios.equipe')->with('success', 'Cargo atualizado.');
    }

    public function destroyRole(Request $request, TeamRole $role): RedirectResponse
    {
        $tenantId = $request->user()?->tenant_id;
        if (! $tenantId || $role->tenant_id !== $tenantId) {
            abort(403, 'Cargo não encontrado.');
        }

        // Remover vínculo dos membros antes (evita confusão de permissão após delete)
        User::query()
            ->where('tenant_id', $tenantId)
            ->where('team_role_id', $role->id)
            ->update(['team_role_id' => null]);

        $this->audit($request, 'team.role.deleted', TeamRole::class, $role->id, [
            'name' => $role->name,
        ]);

        $role->delete();

        return redirect()->route('usuarios.equipe')->with('success', 'Cargo removido.');
    }

    public function storeMember(Request $request): RedirectResponse
    {
        $tenantId = $request->user()?->tenant_id;
        if (! $tenantId) {
            abort(403, 'Tenant inválido.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'team_role_id' => ['required', 'integer', 'exists:team_roles,id'],
            'send_access_email' => ['nullable', 'boolean'],
        ], [
            'email.unique' => 'Este e-mail já está em uso.',
            'password.confirmed' => 'A confirmação da senha não confere.',
        ]);

        $role = TeamRole::query()->where('tenant_id', $tenantId)->where('id', (int) $validated['team_role_id'])->first();
        if (! $role) {
            abort(422, 'Cargo inválido para este tenant.');
        }

        $plainPassword = $validated['password'];
        $member = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($plainPassword),
            'role' => User::ROLE_TEAM,
            'tenant_id' => $tenantId,
            'team_role_id' => $role->id,
        ]);

        $sendAccessEmail = (bool) ($validated['send_access_email'] ?? true);
        if ($sendAccessEmail) {
            try {
                app(TenantMailConfigService::class)->applyMailerConfigForTenant($tenantId, [], null);
                Mail::purge('smtp');
                $loginUrl = rtrim((string) config('app.url'), '/') . '/login';
                Mail::mailer('smtp')->to($member->email)->send(new TeamMemberAccessMail(
                    name: $member->name,
                    email: $member->email,
                    password: $plainPassword,
                    loginUrl: $loginUrl
                ));
            } catch (\Throwable $e) {
                return redirect()->route('usuarios.equipe')->with('error', 'Membro criado, mas não foi possível enviar o e-mail de acesso: ' . $e->getMessage());
            }
        }

        $this->audit($request, 'team.member.created', User::class, $member->id, [
            'email' => $member->email,
            'team_role_id' => $member->team_role_id,
            'send_access_email' => $sendAccessEmail,
        ]);

        return redirect()->route('usuarios.equipe')->with('success', 'Usuário da equipe criado.');
    }

    public function updateMember(Request $request, User $member): RedirectResponse
    {
        $tenantId = $request->user()?->tenant_id;
        if (! $tenantId || $member->tenant_id !== $tenantId || $member->role !== User::ROLE_TEAM) {
            abort(403, 'Usuário não encontrado.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$member->id],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'team_role_id' => ['required', 'integer', 'exists:team_roles,id'],
        ], [
            'email.unique' => 'Este e-mail já está em uso.',
            'password.confirmed' => 'A confirmação da senha não confere.',
        ]);

        $role = TeamRole::query()->where('tenant_id', $tenantId)->where('id', (int) $validated['team_role_id'])->first();
        if (! $role) {
            abort(422, 'Cargo inválido para este tenant.');
        }

        $member->name = $validated['name'];
        $member->email = $validated['email'];
        $member->team_role_id = $role->id;
        if (! empty($validated['password'])) {
            $member->password = Hash::make($validated['password']);
        }
        $member->save();

        $this->audit($request, 'team.member.updated', User::class, $member->id, [
            'email' => $member->email,
            'team_role_id' => $member->team_role_id,
            'password_changed' => ! empty($validated['password']),
        ]);

        return redirect()->route('usuarios.equipe')->with('success', 'Usuário atualizado.');
    }

    public function destroyMember(Request $request, User $member): RedirectResponse
    {
        $tenantId = $request->user()?->tenant_id;
        if (! $tenantId || $member->tenant_id !== $tenantId || $member->role !== User::ROLE_TEAM) {
            abort(403, 'Usuário não encontrado.');
        }

        $this->audit($request, 'team.member.deleted', User::class, $member->id, [
            'email' => $member->email,
            'team_role_id' => $member->team_role_id,
        ]);

        $member->delete();

        return redirect()->route('usuarios.equipe')->with('success', 'Usuário removido.');
    }

    public function clearLogs(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! $user->isInfoprodutor()) {
            abort(403, 'Apenas o titular da conta pode limpar os logs.');
        }

        $tenantId = $user->tenant_id;
        if (! $tenantId) {
            abort(403, 'Tenant inválido.');
        }

        TeamAuditLog::query()->where('tenant_id', $tenantId)->delete();

        $this->audit($request, 'team.logs.cleared', null, null, []);

        return redirect()->route('usuarios.equipe')->with('success', 'Logs limpos.');
    }
}

