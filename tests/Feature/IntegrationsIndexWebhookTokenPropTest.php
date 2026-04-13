<?php

namespace Tests\Feature;

use App\Events\OrderCompleted;
use App\Models\User;
use App\Models\Webhook;
use Tests\TestCase;

class IntegrationsIndexWebhookTokenPropTest extends TestCase
{
    public function test_integrations_page_includes_has_bearer_token_for_webhooks(): void
    {
        $user = User::factory()->create([
            'tenant_id' => 1,
            'role' => User::ROLE_INFOPRODUTOR,
        ]);

        Webhook::create([
            'tenant_id' => 1,
            'name' => 'Com token',
            'url' => 'https://example.com/hook',
            'bearer_token' => 'secret-token-value',
            'events' => [OrderCompleted::class],
            'is_active' => true,
        ]);

        Webhook::create([
            'tenant_id' => 1,
            'name' => 'Sem token',
            'url' => 'https://example.com/hook2',
            'bearer_token' => null,
            'events' => [OrderCompleted::class],
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('integrations.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('webhooks', 2)
                ->where('webhooks.0.has_bearer_token', true)
                ->where('webhooks.1.has_bearer_token', false));
    }
}
