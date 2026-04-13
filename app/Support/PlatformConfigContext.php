<?php

namespace App\Support;

/**
 * Configurações globais da plataforma (tenant_id null) vs painel do vendedor.
 */
class PlatformConfigContext
{
    public static function isPlatformSettingsRequest(): bool
    {
        $path = request()->path();

        return str_starts_with($path, 'plataforma/');
    }

    /**
     * Settings / gateways / e-mail marketing globais usam tenant_id null no painel da plataforma.
     */
    public static function settingsTenantId(): ?int
    {
        if (self::isPlatformSettingsRequest()) {
            return null;
        }

        return auth()->user()?->tenant_id;
    }
}
