<?php

namespace App\Services;

use App\Models\EmailCampaign;
use App\Models\Order;
use Illuminate\Support\Collection;

class EmailCampaignRecipientsService
{
    /**
     * Get recipients for a tenant and filter config.
     * Returns collection of { email, user_id, name } with unique emails (valid only).
     *
     * @param  array{all_customers?: bool, product_ids?: array<int|string>}  $filterConfig
     * @return Collection<int, array{email: string, user_id: int|null, name: string}>
     */
    public function getRecipients(?int $tenantId, array $filterConfig): Collection
    {
        $query = Order::query()
            ->where('status', 'completed')
            ->whereNotNull('email')
            ->where('email', '!=', '');
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        if (! empty($filterConfig['product_ids'])) {
            $query->whereIn('product_id', $filterConfig['product_ids']);
        }

        $orders = $query->with('user:id,name,email')->get();
        $byEmail = [];
        foreach ($orders as $order) {
            $email = trim((string) $order->email);
            if ($email === '' || ! str_contains($email, '@')) {
                continue;
            }
            if (isset($byEmail[$email])) {
                continue;
            }
            $name = $order->user?->name ?? '';
            if ($name === '' && $order->user_id) {
                $name = '';
            }
            $byEmail[$email] = [
                'email' => $email,
                'user_id' => $order->user_id,
                'name' => $name ?: $email,
            ];
        }

        return collect(array_values($byEmail));
    }

    /**
     * Get next batch of recipients for a campaign (not yet in email_campaign_sends), max 30.
     *
     * @return Collection<int, array{email: string, user_id: int|null, name: string}>
     */
    public function getNextRecipientsForCampaign(EmailCampaign $campaign, int $limit = 30): Collection
    {
        $filterConfig = $campaign->filter_config ?? [];
        $tenantId = $campaign->tenant_id;
        $all = $this->getRecipients($tenantId, $filterConfig);

        $sentEmails = $campaign->emailCampaignSends()->pluck('email')->flip();
        $pending = $all->filter(fn ($r) => ! $sentEmails->has($r['email']));

        return $pending->take($limit)->values();
    }
}
