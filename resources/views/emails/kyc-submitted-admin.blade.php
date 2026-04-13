@extends('emails.layouts.branded')

@section('content')
    <p style="margin:0 0 16px 0;font-size:18px;font-weight:600;color:#18181b;">Novo pedido de verificação (KYC)</p>
    <p style="margin:0 0 16px 0;">Um infoprodutor enviou documentos para análise.</p>
    <table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;margin:0 0 24px 0;background-color:#fafafa;border-radius:8px;border:1px solid #e4e4e7;">
        <tr>
            <td style="padding:16px 20px;">
                <p style="margin:0 0 8px 0;font-size:13px;text-transform:uppercase;letter-spacing:0.05em;color:#71717a;">Nome</p>
                <p style="margin:0 0 16px 0;font-weight:600;color:#18181b;">{{ $merchantName }}</p>
                <p style="margin:0 0 8px 0;font-size:13px;text-transform:uppercase;letter-spacing:0.05em;color:#71717a;">E-mail</p>
                <p style="margin:0;font-weight:600;color:#18181b;">{{ $merchantEmail }}</p>
            </td>
        </tr>
    </table>
    <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 auto;">
        <tr>
            <td style="border-radius:8px;background-color:{{ $branding['theme_primary'] }};">
                <a href="{{ $reviewUrl }}" style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;">Abrir verificação na plataforma</a>
            </td>
        </tr>
    </table>
    <p style="margin:24px 0 0 0;font-size:13px;color:#71717a;">Se o botão não funcionar, copie e cole este link no navegador:<br><span style="word-break:break-all;color:#52525b;">{{ $reviewUrl }}</span></p>
@endsection
