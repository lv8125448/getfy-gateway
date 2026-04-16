<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: system-ui, sans-serif; line-height: 1.5; color: #18181b;">
    <p>Olá <?php echo e($customerName); ?>,</p>
    <?php if($approved): ?>
        <p>Sua solicitação de reembolso para o pedido <strong>#<?php echo e($orderRef); ?></strong> (<?php echo e($productName); ?>) foi <strong>aprovada</strong>.</p>
        <?php if($note): ?>
            <p style="font-size:14px;color:#52525b;"><?php echo e($note); ?></p>
        <?php endif; ?>
    <?php else: ?>
        <p>Sua solicitação de reembolso para o pedido <strong>#<?php echo e($orderRef); ?></strong> (<?php echo e($productName); ?>) foi <strong>recusada</strong>.</p>
        <?php if($reason): ?>
            <p><strong>Motivo:</strong> <?php echo e($reason); ?></p>
        <?php endif; ?>
    <?php endif; ?>
    <p style="font-size:12px;color:#71717a;">Este é um e-mail automático da plataforma.</p>
</body>
</html>
<?php /**PATH C:\laragon\www\getfy-gateway\resources\views/emails/refund-decision-customer.blade.php ENDPATH**/ ?>