<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\RunScheduleFallback;
use App\Models\Order;
use App\Models\TeamRole;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TeamAccessTest extends TestCase
{
    public function test_team_user_without_permission_cannot_access_vendas(): void
    {
        $this->withoutMiddleware(EnsureInstalled::class);

        $owner = User::factory()->create([
            'role' => User::ROLE_INFOPRODUTOR,
            'tenant_id' => 1,
        ]);

        $role = TeamRole::create([
            'tenant_id' => 1,
            'name' => 'Sem vendas',
            'permissions' => [
                'dashboard.view' => true,
                'vendas.view' => false,
                'produtos.view' => false,
                'relatorios.view' => false,
                'integracoes.view' => false,
                'email_marketing.view' => false,
                'api_pagamentos.view' => false,
                'configuracoes.view' => false,
                'equipe.manage' => false,
            ],
        ]);

        $team = User::factory()->create([
            'role' => User::ROLE_TEAM,
            'tenant_id' => $owner->tenant_id,
            'team_role_id' => $role->id,
        ]);

        $this->actingAs($team)->get('/vendas')->assertStatus(403);
    }

    public function test_team_user_sees_only_allowed_products_in_vendas(): void
    {
        $this->withoutMiddleware(EnsureInstalled::class);

        $owner = User::factory()->create([
            'role' => User::ROLE_INFOPRODUTOR,
            'tenant_id' => 1,
        ]);

        $productA = $this->createTestProduct(['tenant_id' => 1, 'name' => 'Produto A']);
        $productB = $this->createTestProduct(['tenant_id' => 1, 'name' => 'Produto B']);

        $role = TeamRole::create([
            'tenant_id' => 1,
            'name' => 'Vendas A',
            'permissions' => [
                'dashboard.view' => false,
                'vendas.view' => true,
                'produtos.view' => false,
                'relatorios.view' => false,
                'integracoes.view' => false,
                'email_marketing.view' => false,
                'api_pagamentos.view' => false,
                'configuracoes.view' => false,
                'equipe.manage' => false,
            ],
        ]);
        $role->products()->sync([$productA->id]);

        $team = User::factory()->create([
            'role' => User::ROLE_TEAM,
            'tenant_id' => $owner->tenant_id,
            'team_role_id' => $role->id,
        ]);

        Order::create([
            'tenant_id' => 1,
            'user_id' => $team->id,
            'product_id' => $productA->id,
            'status' => 'completed',
            'amount' => 10,
            'email' => 'buyer-a@example.com',
            'gateway' => 'manual',
        ]);

        Order::create([
            'tenant_id' => 1,
            'user_id' => $team->id,
            'product_id' => $productB->id,
            'status' => 'completed',
            'amount' => 20,
            'email' => 'buyer-b@example.com',
            'gateway' => 'manual',
        ]);

        $this->actingAs($team)
            ->get('/vendas')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('vendas.data', 1)
                ->where('vendas.data.0.product_id', $productA->id));
    }

    public function test_team_user_cannot_edit_product_outside_allowed_list(): void
    {
        $this->withoutMiddleware(EnsureInstalled::class);

        $owner = User::factory()->create([
            'role' => User::ROLE_INFOPRODUTOR,
            'tenant_id' => 1,
        ]);

        $productA = $this->createTestProduct(['tenant_id' => 1, 'name' => 'Produto A']);
        $productB = $this->createTestProduct(['tenant_id' => 1, 'name' => 'Produto B']);

        $role = TeamRole::create([
            'tenant_id' => 1,
            'name' => 'Produtos A',
            'permissions' => [
                'dashboard.view' => false,
                'vendas.view' => false,
                'produtos.view' => true,
                'relatorios.view' => false,
                'integracoes.view' => false,
                'email_marketing.view' => false,
                'api_pagamentos.view' => false,
                'configuracoes.view' => false,
                'equipe.manage' => false,
            ],
        ]);
        $role->products()->sync([$productA->id]);

        $team = User::factory()->create([
            'role' => User::ROLE_TEAM,
            'tenant_id' => $owner->tenant_id,
            'team_role_id' => $role->id,
        ]);

        $this->actingAs($team)->get("/produtos/{$productB->id}/edit")->assertStatus(403);
    }

    public function test_creating_team_member_can_send_access_email(): void
    {
        $this->withoutMiddleware(EnsureInstalled::class);

        Mail::fake();

        $owner = User::factory()->create([
            'role' => User::ROLE_INFOPRODUTOR,
            'tenant_id' => 1,
        ]);

        $role = TeamRole::create([
            'tenant_id' => 1,
            'name' => 'Equipe',
            'permissions' => ['equipe.manage' => true],
        ]);

        $payload = [
            'name' => 'Membro',
            'email' => 'membro@example.com',
            'password' => 'Senha12345',
            'password_confirmation' => 'Senha12345',
            'team_role_id' => $role->id,
            'send_access_email' => true,
        ];

        $this->actingAs($owner)
            ->post('/usuarios/equipe/membros', $payload)
            ->assertRedirect('/usuarios/equipe');

        $this->assertDatabaseHas('users', [
            'email' => 'membro@example.com',
            'role' => User::ROLE_TEAM,
            'tenant_id' => 1,
            'team_role_id' => $role->id,
        ]);

        Mail::assertSent(\App\Mail\TeamMemberAccessMail::class, function ($m) {
            return $m->hasTo('membro@example.com');
        });
    }

    public function test_only_infoprodutor_can_clear_team_audit_logs(): void
    {
        $this->withoutMiddleware([EnsureInstalled::class, RunScheduleFallback::class]);

        $owner = User::factory()->create([
            'role' => User::ROLE_INFOPRODUTOR,
            'tenant_id' => 1,
        ]);

        $role = TeamRole::create([
            'tenant_id' => 1,
            'name' => 'Com equipe',
            'permissions' => [
                'dashboard.view' => true,
                'vendas.view' => false,
                'produtos.view' => false,
                'relatorios.view' => false,
                'integracoes.view' => false,
                'email_marketing.view' => false,
                'api_pagamentos.view' => false,
                'configuracoes.view' => false,
                'equipe.manage' => true,
            ],
        ]);

        $teamMember = User::factory()->create([
            'role' => User::ROLE_TEAM,
            'tenant_id' => 1,
            'team_role_id' => $role->id,
        ]);

        \App\Models\TeamAuditLog::create([
            'tenant_id' => 1,
            'actor_user_id' => $owner->id,
            'action' => 'team.member.created',
        ]);

        $this->actingAs($teamMember)
            ->post('/usuarios/equipe/logs/clear')
            ->assertStatus(403);

        $this->assertDatabaseCount('team_audit_logs', 1);

        $this->actingAs($owner)
            ->post('/usuarios/equipe/logs/clear')
            ->assertRedirect('/usuarios/equipe');
    }
}

