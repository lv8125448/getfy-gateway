<?php

namespace App\Http\Controllers;

use App\Mail\CampaignMail;
use App\Models\EmailCampaign;
use App\Models\Product;
use App\Services\EmailCampaignRecipientsService;
use App\Services\PlatformAuditService;
use App\Services\TenantMailConfigService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailMarketingController extends Controller
{
    public function __construct(
        protected TenantMailConfigService $mailConfig,
        protected EmailCampaignRecipientsService $recipientsService
    ) {}

    public function index(): Response
    {
        $tenantId = null;
        $emailConfigured = $this->mailConfig->isEmailConfigured($tenantId);
        $cloudMode = config('getfy.cloud_mode', false);

        $campaigns = EmailCampaign::forTenant(null)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (EmailCampaign $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'subject' => $c->subject,
                'status' => $c->status,
                'total_recipients' => $c->total_recipients,
                'sent_count' => $c->sent_count,
                'sent_at' => $c->sent_at?->toIso8601String(),
                'created_at' => $c->created_at->toIso8601String(),
            ])
            ->values()
            ->all();

        $cronInstructions = 'Adicione ao crontab do servidor (uma vez por minuto):' . "\n"
            . '* * * * * cd ' . base_path() . ' && php artisan schedule:run >> /dev/null 2>&1' . "\n\n"
            . 'Para o envio em massa funcionar, o worker de fila deve estar rodando:' . "\n"
            . 'php artisan queue:work';

        $scheduleHeartbeat = Cache::get('schedule_heartbeat');
        $scheduleOk = self::isHeartbeatRecent($scheduleHeartbeat, 5);
        if (! $scheduleOk) {
            self::runScheduleFallbackIfDue();
            $scheduleOk = self::isHeartbeatRecent(Cache::get('schedule_heartbeat'), 5);
        }
        $queueOk = self::isHeartbeatRecent(Cache::get('queue_heartbeat'), 5);

        $cronSecret = config('getfy.cron_secret');
        $appUrl = rtrim(config('app.url'), '/');
        $cronUrl = $cronSecret
            ? $appUrl . '/cron?token=' . urlencode($cronSecret)
            : null;

        return Inertia::render('EmailMarketing/Index', [
            'campaigns' => $campaigns,
            'email_configured' => $emailConfigured,
            'cloud_mode' => $cloudMode,
            'cron_instructions' => $cronInstructions,
            'app_url' => $appUrl,
            'base_path' => base_path(),
            'cron_url' => $cronUrl,
            'schedule_ok' => $scheduleOk,
            'queue_ok' => $queueOk,
        ]);
    }

    public function create(): Response
    {
        $tenantId = null;
        $emailConfigured = $this->mailConfig->isEmailConfigured($tenantId);
        $products = Product::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn (Product $p) => ['id' => $p->id, 'name' => $p->name])->values()->all();

        return Inertia::render('EmailMarketing/Create', [
            'email_configured' => $emailConfigured,
            'products' => $products,
            'default_body_html' => self::defaultBodyHtml(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body_html' => ['required', 'string'],
            'filter_config' => ['nullable', 'array'],
            'filter_config.all_customers' => ['nullable', 'boolean'],
            'filter_config.product_ids' => ['nullable', 'array'],
            'filter_config.product_ids.*' => ['nullable'],
        ]);

        $filterConfig = $validated['filter_config'] ?? [];
        if (empty($filterConfig['all_customers']) && empty($filterConfig['product_ids'])) {
            $filterConfig['all_customers'] = true;
        }

        EmailCampaign::create([
            'tenant_id' => null,
            'name' => $validated['name'],
            'subject' => $validated['subject'],
            'body_html' => $validated['body_html'],
            'filter_config' => $filterConfig,
            'status' => EmailCampaign::STATUS_DRAFT,
        ]);

        return redirect()->route('plataforma.email-marketing.index')->with('success', 'Campanha criada. Você pode disparar quando quiser.');
    }

    public function edit(EmailCampaign $campaign): Response|RedirectResponse
    {
        $tenantId = null;
        if ($campaign->tenant_id !== null) {
            abort(404);
        }
        if (! $campaign->isDraft()) {
            return redirect()->route('plataforma.email-marketing.index')->with('info', 'Apenas campanhas em rascunho podem ser editadas.');
        }

        $emailConfigured = $this->mailConfig->isEmailConfigured($tenantId);
        $products = Product::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn (Product $p) => ['id' => $p->id, 'name' => $p->name])->values()->all();

        return Inertia::render('EmailMarketing/Edit', [
            'campaign' => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'subject' => $campaign->subject,
                'body_html' => $campaign->body_html,
                'filter_config' => $campaign->filter_config ?? [],
            ],
            'email_configured' => $emailConfigured,
            'products' => $products,
            'default_body_html' => self::defaultBodyHtml(),
        ]);
    }

    public function update(Request $request, EmailCampaign $campaign): RedirectResponse
    {
        if ($campaign->tenant_id !== null || ! $campaign->isDraft()) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body_html' => ['required', 'string'],
            'filter_config' => ['nullable', 'array'],
            'filter_config.all_customers' => ['nullable', 'boolean'],
            'filter_config.product_ids' => ['nullable', 'array'],
            'filter_config.product_ids.*' => ['nullable'],
        ]);

        $filterConfig = $validated['filter_config'] ?? [];
        if (empty($filterConfig['all_customers']) && empty($filterConfig['product_ids'])) {
            $filterConfig['all_customers'] = true;
        }

        $campaign->update([
            'name' => $validated['name'],
            'subject' => $validated['subject'],
            'body_html' => $validated['body_html'],
            'filter_config' => $filterConfig,
        ]);

        return redirect()->route('plataforma.email-marketing.index')->with('success', 'Campanha atualizada.');
    }

    public function previewRecipients(Request $request, EmailCampaign $campaign): JsonResponse
    {
        if ($campaign->tenant_id !== null) {
            abort(404);
        }

        $filterConfig = $campaign->filter_config ?? [];
        if (empty($filterConfig)) {
            $filterConfig = ['all_customers' => true];
        }

        return $this->previewRecipientsResponse(null, $filterConfig);
    }

    /**
     * Preview recipients by filter config (no campaign required). Used on create form.
     */
    public function previewRecipientsByFilter(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'filter_config' => ['nullable', 'array'],
            'filter_config.all_customers' => ['nullable', 'boolean'],
            'filter_config.product_ids' => ['nullable', 'array'],
            'filter_config.product_ids.*' => ['nullable'],
        ]);
        $filterConfig = $validated['filter_config'] ?? [];
        if (empty($filterConfig) || (empty($filterConfig['all_customers']) && empty($filterConfig['product_ids']))) {
            $filterConfig = ['all_customers' => true];
        }

        return $this->previewRecipientsResponse(null, $filterConfig);
    }

    private function previewRecipientsResponse(?int $tenantId, array $filterConfig): JsonResponse
    {
        $recipients = $this->recipientsService->getRecipients($tenantId, $filterConfig);
        $count = $recipients->count();
        $sample = $recipients->take(10)->values()->all();

        return response()->json([
            'count' => $count,
            'sample' => $sample,
        ]);
    }

    public function send(EmailCampaign $campaign): RedirectResponse
    {
        if ($campaign->tenant_id !== null) {
            abort(404);
        }
        if (! $campaign->isDraft()) {
            return redirect()->route('plataforma.email-marketing.index')->with('error', 'Apenas campanhas em rascunho podem ser disparadas.');
        }
        if (! $this->mailConfig->isEmailConfigured(null)) {
            return redirect()->route('plataforma.email-marketing.index')->with('error', 'Configure o e-mail em Configurações > E-mail antes de disparar campanhas.');
        }

        $filterConfig = $campaign->filter_config ?? ['all_customers' => true];
        $total = $this->recipientsService->getRecipients(null, $filterConfig)->count();
        if ($total === 0) {
            return redirect()->route('plataforma.email-marketing.index')->with('error', 'Nenhum destinatário encontrado para o filtro desta campanha.');
        }

        $campaign->update([
            'status' => EmailCampaign::STATUS_SENDING,
            'total_recipients' => $total,
            'sent_count' => 0,
        ]);

        PlatformAuditService::log('platform.email_campaign.send', [
            'campaign_id' => $campaign->id,
            'total_recipients' => $total,
        ]);

        return redirect()->route('plataforma.email-marketing.index')->with('success', 'Campanha iniciada. Os e-mails serão enviados em lotes de 30 por minuto.');
    }

    /**
     * Fallback: quando o cron não está rodando, executa o schedule ao visitar esta página.
     * Usa throttle (55s) para não rodar em toda requisição.
     */
    private static function runScheduleFallbackIfDue(): void
    {
        $lastRun = Cache::get('schedule_fallback_last_run');
        if ($lastRun && Carbon::parse($lastRun)->gte(now()->subSeconds(55))) {
            return;
        }
        Cache::put('schedule_fallback_last_run', now()->toIso8601String(), now()->addMinutes(5));
        Artisan::call('schedule:run');
    }

    /**
     * Verifica se o valor do heartbeat (ISO8601) está dentro dos últimos N minutos.
     */
    private static function isHeartbeatRecent(?string $value, int $minutes = 5): bool
    {
        if ($value === null || $value === '') {
            return false;
        }
        try {
            $at = Carbon::parse($value);

            return $at->gte(now()->subMinutes($minutes));
        } catch (\Throwable) {
            return false;
        }
    }

    public static function defaultBodyHtml(): string
    {
        return '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;margin:0 auto;font-family:\'Segoe UI\',Tahoma,sans-serif;background:#f8fafc;padding:32px 24px;">
<tr><td style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.08);">
<table width="100%" cellpadding="0" cellspacing="0">
<tr><td style="padding:32px 32px 24px;text-align:center;border-bottom:1px solid #e2e8f0;"><h1 style="margin:0;font-size:22px;font-weight:600;color:#0f172a;">Olá, {nome}!</h1></td></tr>
<tr><td style="padding:28px 32px;"><p style="margin:0 0 16px;font-size:16px;line-height:1.6;color:#334155;">Conteúdo do seu e-mail aqui. Use os placeholders <strong>{nome}</strong> e <strong>{email}</strong> para personalizar.</p><p style="margin:0;font-size:16px;line-height:1.6;color:#334155;">Qualquer dúvida, responda este e-mail.</p></td></tr>
<tr><td style="padding:20px 32px;background:#f1f5f9;border-radius:0 0 12px 12px;"><p style="margin:0;font-size:13px;color:#64748b;">Você está recebendo este e-mail porque é um de nossos clientes.</p></td></tr>
</table></td></tr></table>';
    }
}
