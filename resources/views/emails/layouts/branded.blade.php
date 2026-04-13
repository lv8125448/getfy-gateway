<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $branding['app_name'] ?? config('app.name') }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f4f4f5;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.06);">
                    <tr>
                        <td style="padding:0;background-color:{{ $branding['theme_primary'] ?? '#4f46e5' }};height:4px;"></td>
                    </tr>
                    <tr>
                        <td style="padding:28px 32px 8px 32px;text-align:center;">
                            @if(!empty($branding['logo_url']))
                                <img src="{{ $branding['logo_url'] }}" alt="{{ $branding['app_name'] }}" width="180" style="max-width:180px;height:auto;display:block;margin:0 auto 16px auto;border:0;">
                            @else
                                <p style="margin:0 0 8px 0;font-size:20px;font-weight:700;color:#18181b;letter-spacing:-0.02em;">{{ $branding['app_name'] }}</p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 32px 32px 32px;color:#3f3f46;font-size:16px;line-height:1.6;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px;background-color:#fafafa;border-top:1px solid #e4e4e7;">
                            <p style="margin:0;font-size:12px;line-height:1.5;color:#71717a;text-align:center;">
                                {{ $branding['app_name'] ?? config('app.name') }}
                            </p>
                            <p style="margin:8px 0 0 0;font-size:11px;line-height:1.4;color:#a1a1aa;text-align:center;">
                                Mensagem automática; não responda diretamente a este e-mail se não for o canal oficial de suporte.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
