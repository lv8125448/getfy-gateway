@extends('emails.layouts.branded')

@section('content')
    <p style="margin:0 0 16px 0;font-size:18px;font-weight:600;color:#18181b;">Convite de co-produção</p>
    <p style="margin:0 0 16px 0;">Olá,</p>
    <p style="margin:0 0 16px 0;"><strong>{{ $inviterName }}</strong> convidou você para co-produzir o produto <strong>{{ $productName }}</strong> na plataforma {{ $branding['app_name'] }}.</p>
    <p style="margin:0 0 16px 0;">Comissão acordada: <strong>{{ number_format($commissionPercent, 2, ',', '.') }}%</strong> sobre as vendas elegíveis (conforme definido no convite).</p>
    <p style="margin:0 0 24px 0;">Este convite foi enviado para <strong>{{ $recipientEmail }}</strong>. Para aceitar, use o mesmo e-mail na sua conta.</p>
    <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 auto 12px auto;">
        <tr>
            <td style="border-radius:8px;background-color:{{ $branding['theme_primary'] }};">
                <a href="{{ $acceptUrl }}" style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;">Ver convite e aceitar</a>
            </td>
        </tr>
    </table>
    <p style="margin:16px 0 0 0;font-size:14px;color:#64748b;">Ainda não tem cadastro? <a href="{{ $registerUrl }}" style="color:{{ $branding['theme_primary'] }};">Criar conta como infoprodutor</a></p>
@endsection
