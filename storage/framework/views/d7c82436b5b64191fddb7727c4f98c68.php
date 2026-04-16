<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: system-ui, sans-serif; line-height: 1.5; color: #18181b;">
    <p>Olá,</p>
    <p>Você recebeu uma <strong>solicitação de reembolso</strong> para o pedido <strong>#<?php echo e($orderRef); ?></strong> — produto: <?php echo e($productName); ?>.</p>
    <p><strong>Motivo informado pelo cliente:</strong><br><?php echo e($reason); ?></p>
    <p><a href="<?php echo e($manageUrl); ?>" style="display:inline-block;margin-top:12px;padding:12px 20px;background:#0ea5e9;color:#fff;text-decoration:none;border-radius:8px;">Ver solicitações</a></p>
    <p style="font-size:12px;color:#71717a;">Este é um e-mail automático da plataforma.</p>
</body>
</html>
<?php /**PATH C:\laragon\www\getfy-gateway\resources\views/emails/refund-request-seller.blade.php ENDPATH**/ ?>