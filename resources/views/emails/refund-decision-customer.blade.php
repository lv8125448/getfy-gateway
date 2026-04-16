<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: system-ui, sans-serif; line-height: 1.5; color: #18181b;">
    <p>Olá {{ $customerName }},</p>
    @if($approved)
        <p>Sua solicitação de reembolso para o pedido <strong>#{{ $orderRef }}</strong> ({{ $productName }}) foi <strong>aprovada</strong>.</p>
        @if($note)
            <p style="font-size:14px;color:#52525b;">{{ $note }}</p>
        @endif
    @else
        <p>Sua solicitação de reembolso para o pedido <strong>#{{ $orderRef }}</strong> ({{ $productName }}) foi <strong>recusada</strong>.</p>
        @if($reason)
            <p><strong>Motivo:</strong> {{ $reason }}</p>
        @endif
    @endif
    <p style="font-size:12px;color:#71717a;">Este é um e-mail automático da plataforma.</p>
</body>
</html>
