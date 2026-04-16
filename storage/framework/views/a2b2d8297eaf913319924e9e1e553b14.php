<?php $__env->startSection('content'); ?>
    <p style="margin:0 0 16px 0;font-size:18px;font-weight:600;color:#18181b;">Bem-vindo(a) à <?php echo e($branding['app_name']); ?></p>
    <p style="margin:0 0 16px 0;">Olá, <?php echo e($recipientName); ?>,</p>
    <p style="margin:0 0 16px 0;">Sua conta foi criada com sucesso. Estamos felizes em ter você com a gente.</p>
    <p style="margin:0 0 24px 0;">Para liberar o <strong>Financeiro</strong> e demais recursos que exigem identificação, complete a <strong>verificação de identidade (KYC)</strong> no painel quando puder.</p>
    <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 auto 12px auto;">
        <tr>
            <td style="border-radius:8px;background-color:<?php echo e($branding['theme_primary']); ?>;">
                <a href="<?php echo e($dashboardUrl); ?>" style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;">Abrir o painel</a>
            </td>
        </tr>
    </table>
    <table role="presentation" cellspacing="0" cellpadding="0" style="margin:12px auto 0 auto;">
        <tr>
            <td style="border-radius:8px;border:2px solid <?php echo e($branding['theme_primary']); ?>;">
                <a href="<?php echo e($kycUrl); ?>" style="display:inline-block;padding:12px 26px;font-size:15px;font-weight:600;color:<?php echo e($branding['theme_primary']); ?>;text-decoration:none;">Ir para verificação (KYC)</a>
            </td>
        </tr>
    </table>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('emails.layouts.branded', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\getfy-gateway\resources\views/emails/welcome-infoprodutor.blade.php ENDPATH**/ ?>