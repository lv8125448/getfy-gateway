@extends('emails.layouts.branded')

@section('content')
    <p style="margin:0 0 16px 0;font-size:18px;font-weight:600;color:#18181b;">Verificação aprovada</p>
    <p style="margin:0 0 16px 0;">Olá, {{ $recipientName }},</p>
    <p style="margin:0 0 16px 0;">Sua verificação de identidade (KYC) foi <strong style="color:#15803d;">aprovada</strong> pela equipe da plataforma. Você já pode utilizar os recursos que dependem dessa etapa, conforme as regras do seu painel.</p>
    <table role="presentation" cellspacing="0" cellpadding="0" style="margin:24px auto 0 auto;">
        <tr>
            <td style="border-radius:8px;background-color:{{ $branding['theme_primary'] }};">
                <a href="{{ $dashboardUrl }}" style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;">Acessar o painel</a>
            </td>
        </tr>
    </table>
@endsection
