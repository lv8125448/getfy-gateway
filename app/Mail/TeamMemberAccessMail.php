<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TeamMemberAccessMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public string $loginUrl
    ) {}

    public function build()
    {
        $subject = 'Acesso liberado — sua conta foi criada';

        $html = '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;margin:0 auto;font-family:\'Segoe UI\',Tahoma,sans-serif;background:#f8fafc;padding:32px 24px;">'
            .'<tr><td style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.08);">'
            .'<table width="100%" cellpadding="0" cellspacing="0">'
            .'<tr><td style="padding:32px 32px 24px;text-align:center;border-bottom:1px solid #e2e8f0;">'
            .'<h1 style="margin:0;font-size:22px;font-weight:600;color:#0f172a;">Olá, '.e($this->name).'!</h1>'
            .'</td></tr>'
            .'<tr><td style="padding:28px 32px;">'
            .'<p style="margin:0 0 16px;font-size:16px;line-height:1.6;color:#334155;">Sua conta de acesso ao painel foi criada.</p>'
            .'<p style="margin:0 0 24px;font-size:16px;line-height:1.6;color:#334155;">Use os dados abaixo para entrar:</p>'
            .'<div style="margin:0 0 24px;padding:18px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:10px;">'
            .'<p style="margin:0 0 10px;font-size:14px;color:#0f172a;"><strong>Link:</strong> <a href="'.e($this->loginUrl).'" style="color:#0ea5e9;word-break:break-all;">'.e($this->loginUrl).'</a></p>'
            .'<p style="margin:0 0 10px;font-size:14px;color:#0f172a;"><strong>E-mail:</strong> '.e($this->email).'</p>'
            .'<p style="margin:0;font-size:15px;color:#0f172a;font-family:Consolas,\'Courier New\',monospace;font-weight:600;letter-spacing:0.02em;word-break:break-all;"><strong>Senha:</strong> '.e($this->password).'</p>'
            .'</div>'
            .'<p style="margin:0;font-size:13px;line-height:1.5;color:#64748b;">Por segurança, recomendamos trocar sua senha após o primeiro acesso.</p>'
            .'</td></tr>'
            .'<tr><td style="padding:20px 32px;background:#f1f5f9;border-radius:0 0 12px 12px;">'
            .'<p style="margin:0;font-size:13px;color:#64748b;">Se você não esperava este e-mail, ignore.</p>'
            .'</td></tr>'
            .'</table></td></tr></table>';

        return $this->subject($subject)->html($html);
    }
}

