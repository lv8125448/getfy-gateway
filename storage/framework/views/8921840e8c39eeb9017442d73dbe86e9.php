<?php $__env->startSection('content'); ?>
    <p style="margin:0 0 16px 0;font-size:18px;font-weight:600;color:#18181b;">Convite de co-produção</p>
    <p style="margin:0 0 16px 0;">Olá,</p>
    <p style="margin:0 0 16px 0;"><strong><?php echo e($inviterName); ?></strong> convidou você para co-produzir o produto <strong><?php echo e($productName); ?></strong> na plataforma <?php echo e($branding['app_name']); ?>.</p>
    <p style="margin:0 0 16px 0;">Comissão acordada: <strong><?php echo e(number_format($commissionPercent, 2, ',', '.')); ?>%</strong> sobre as vendas elegíveis (conforme definido no convite).</p>
    <p style="margin:0 0 24px 0;">Este convite foi enviado para <strong><?php echo e($recipientEmail); ?></strong>. Para aceitar, use o mesmo e-mail na sua conta.</p>
    <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 auto 12px auto;">
        <tr>
            <td style="border-radius:8px;background-color:<?php echo e($branding['theme_primary']); ?>;">
                <a href="<?php echo e($acceptUrl); ?>" style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;">Ver convite e aceitar</a>
            </td>
        </tr>
    </table>
    <p style="margin:16px 0 0 0;font-size:14px;color:#64748b;">Ainda não tem cadastro? <a href="<?php echo e($registerUrl); ?>" style="color:<?php echo e($branding['theme_primary']); ?>;">Criar conta como infoprodutor</a></p>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('emails.layouts.branded', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\getfy-gateway\resources\views/emails/coproduction-invitation.blade.php ENDPATH**/ ?>