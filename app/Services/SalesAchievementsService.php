<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SalesAchievement;
use Illuminate\Support\Facades\Schema;

class SalesAchievementsService
{
    public function getValidSalesTotal(?int $tenantId): float
    {
        return (float) Order::forTenant($tenantId)
            ->where('status', 'completed')
            ->where(function ($q) {
                $q->where('approved_manually', false)
                    ->orWhereNull('approved_manually');
            })
            ->whereNotNull('gateway')
            ->where('gateway', '!=', 'manual')
            ->sum('amount');
    }

    /**
     * @return array{total_valid_sales: float, current_achievement: array|null, next_achievement: array|null, progress_percent: float, achievements: array}
     */
    public function getProgressForTenant(?int $tenantId): array
    {
        $total = $this->getValidSalesTotal($tenantId);
        $achievements = $this->getAchievementsCatalog();

        $current = null;
        $next = null;
        $progressPercent = 0.0;

        $result = [];
        foreach ($achievements as $a) {
            $unlocked = $total >= $a['threshold'];
            $result[] = [
                'threshold' => $a['threshold'],
                'slug' => $a['slug'],
                'name' => $a['name'],
                'image' => $a['image'],
                'unlocked' => $unlocked,
            ];

            if ($unlocked) {
                $current = $a;
            } elseif ($next === null) {
                $next = $a;
            }
        }

        if ($next !== null) {
            $prevThreshold = $current['threshold'] ?? 0;
            $range = $next['threshold'] - $prevThreshold;
            $progress = $total - $prevThreshold;
            $progressPercent = $range > 0 ? min(100, max(0, ($progress / $range) * 100)) : 0;
        } elseif ($current !== null) {
            $progressPercent = 100;
            $next = null;
        }

        return [
            'total_valid_sales' => $total,
            'current_achievement' => $current,
            'next_achievement' => $next,
            'progress_percent' => round($progressPercent, 1),
            'achievements' => $result,
        ];
    }

    public function getAchievementBySlug(string $slug): ?array
    {
        $achievements = $this->getAchievementsCatalog();
        foreach ($achievements as $a) {
            if (($a['slug'] ?? '') === $slug) {
                return $a;
            }
        }
        return null;
    }

    public function getValidSlugs(): array
    {
        $achievements = $this->getAchievementsCatalog();
        return array_column($achievements, 'slug');
    }

    /**
     * @return array<int, array{threshold: float, slug: string, name: string, image: string|null}>
     */
    private function getAchievementsCatalog(): array
    {
        if (Schema::hasTable('sales_achievements')) {
            $rows = SalesAchievement::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('threshold')
                ->get();

            if ($rows->isNotEmpty()) {
                return $rows->map(fn (SalesAchievement $row) => [
                    'threshold' => (float) $row->threshold,
                    'slug' => (string) $row->slug,
                    'name' => (string) $row->name,
                    'image' => $row->image ? (string) $row->image : null,
                ])->values()->all();
            }
        }

        $fallback = config('conquistas.achievements', []);

        return array_values(array_map(fn (array $a) => [
            'threshold' => (float) ($a['threshold'] ?? 0),
            'slug' => (string) ($a['slug'] ?? ''),
            'name' => (string) ($a['name'] ?? ''),
            'image' => ! empty($a['image']) ? (string) $a['image'] : null,
        ], $fallback));
    }
}
