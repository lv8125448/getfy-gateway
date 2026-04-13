@extends('emails.layouts.branded')

@section('content')
    <p style="margin:0 0 16px 0;font-size:18px;font-weight:600;color:#18181b;">Verificação não aprovada</p>
    <p style="margin:0 0 16px 0;">Olá, {{ $recipientName }},</p>
    <p style="margin:0 0 16px 0;">Sua verificação de identidade (KYC) <strong>não foi aprovada</strong> nesta análise. Você pode enviar novos documentos pelo painel, seguindo as orientações abaixo.</p>
    <table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;margin:0 0 24px 0;background-color:#fef2f2;border-radius:8px;border:1px solid #fecaca;">
        <tr>
            <td style="padding:16px 20px;">
                <p style="margin:0 0 8px 0;font-size:13px;text-transform:uppercase;letter-spacing:0.05em;color:#991b1b;">Motivo informado</p>
                <p style="margin:0;color:#7f1d1d;white-space:pre-wrap;">{{ $rejectionReason }}</p>
            </td>
        </tr>
    </table>
    <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 auto;">
        <tr>
            <td style="border-radius:8px;background-color:{{ $branding['theme_primary'] }};">
                <a href="{{ $kycUrl }}" style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;">Reenviar documentos</a>
            </td>
        </tr>
    </table>
@endsection
