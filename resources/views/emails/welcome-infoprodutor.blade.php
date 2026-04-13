@extends('emails.layouts.branded')

@section('content')
    <p style="margin:0 0 16px 0;font-size:18px;font-weight:600;color:#18181b;">Bem-vindo(a) à {{ $branding['app_name'] }}</p>
    <p style="margin:0 0 16px 0;">Olá, {{ $recipientName }},</p>
    <p style="margin:0 0 16px 0;">Sua conta foi criada com sucesso. Estamos felizes em ter você com a gente.</p>
    <p style="margin:0 0 24px 0;">Para liberar o <strong>Financeiro</strong> e demais recursos que exigem identificação, complete a <strong>verificação de identidade (KYC)</strong> no painel quando puder.</p>
    <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 auto 12px auto;">
        <tr>
            <td style="border-radius:8px;background-color:{{ $branding['theme_primary'] }};">
                <a href="{{ $dashboardUrl }}" style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;">Abrir o painel</a>
            </td>
        </tr>
    </table>
    <table role="presentation" cellspacing="0" cellpadding="0" style="margin:12px auto 0 auto;">
        <tr>
            <td style="border-radius:8px;border:2px solid {{ $branding['theme_primary'] }};">
                <a href="{{ $kycUrl }}" style="display:inline-block;padding:12px 26px;font-size:15px;font-weight:600;color:{{ $branding['theme_primary'] }};text-decoration:none;">Ir para verificação (KYC)</a>
            </td>
        </tr>
    </table>
@endsection
