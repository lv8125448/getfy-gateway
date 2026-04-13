<?php

namespace App\Services;

use App\Models\MemberCertificateIssued;
use App\Models\MemberComment;
use App\Models\MemberAchievementUnlock;
use App\Models\Product;
use App\Models\User;

class GamificationService
{
    public const TRIGGER_FIRST_LESSON = 'first_lesson';
    public const TRIGGER_LESSONS_COUNT = 'lessons_count';
    public const TRIGGER_COMPLETION_PERCENT = 'completion_percent';
    public const TRIGGER_COURSE_COMPLETE = 'course_complete';
    public const TRIGGER_FIRST_COMMENT = 'first_comment';
    public const TRIGGER_CERTIFICATE_EARNED = 'certificate_earned';

    public function __construct(
        protected MemberProgressService $progressService
    ) {}

    /**
     * Check all achievements and unlock any that are newly satisfied. Returns payload for modal.
     *
     * @return array<int, array{id: string, title: string, description: string, image_url: string}>
     */
    public function checkAndUnlock(Product $product, User $user): array
    {
        $config = $product->member_area_config;
        $gamification = $config['gamification'] ?? [];
        if (empty($gamification['enabled']) || empty($gamification['achievements'])) {
            return [];
        }

        $unlockedIds = MemberAchievementUnlock::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->pluck('achievement_id')
            ->flip()
            ->all();

        $newlyUnlocked = [];
        $completedCount = $this->progressService->completedLessonsCount($product, $user);
        $completionPercent = $this->progressService->completionPercent($product, $user);
        $hasApprovedComment = MemberComment::forProduct($product->id)
            ->where('user_id', $user->id)
            ->status(MemberComment::STATUS_APPROVED)
            ->exists();
        $hasCertificate = MemberCertificateIssued::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->exists();

        foreach ($gamification['achievements'] as $achievement) {
            $id = $achievement['id'] ?? null;
            if (! $id || isset($unlockedIds[$id])) {
                continue;
            }

            if (! $this->evaluateTrigger($achievement, $completedCount, $completionPercent, $hasApprovedComment, $hasCertificate)) {
                continue;
            }

            MemberAchievementUnlock::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'achievement_id' => $id,
                'unlocked_at' => now(),
            ]);
            $unlockedIds[$id] = true;

            $imageUrl = $this->achievementImageUrl($achievement['image'] ?? '', $product);
            $newlyUnlocked[] = [
                'id' => $id,
                'title' => $achievement['title'] ?? 'Conquista',
                'description' => $achievement['description'] ?? '',
                'image_url' => $imageUrl,
            ];
        }

        return $newlyUnlocked;
    }

    /**
     * List all achievements for the product with user unlock status and requirement text for dropdown.
     *
     * @return array<int, array{id: string, title: string, description: string, image_url: string, unlocked: bool, unlocked_at: string|null, requirement_text: string}>
     */
    public function getAchievementsForUser(Product $product, User $user): array
    {
        $config = $product->member_area_config;
        $gamification = $config['gamification'] ?? [];
        if (empty($gamification['enabled']) || empty($gamification['achievements'])) {
            return [];
        }

        $unlocks = MemberAchievementUnlock::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->get()
            ->keyBy('achievement_id');

        $completedCount = $this->progressService->completedLessonsCount($product, $user);
        $completionPercent = $this->progressService->completionPercent($product, $user);
        $hasApprovedComment = MemberComment::forProduct($product->id)
            ->where('user_id', $user->id)
            ->status(MemberComment::STATUS_APPROVED)
            ->exists();
        $hasCertificate = MemberCertificateIssued::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->exists();

        $result = [];
        foreach ($gamification['achievements'] as $achievement) {
            $id = $achievement['id'] ?? null;
            if (! $id) {
                continue;
            }

            $unlock = $unlocks->get($id);
            $unlocked = $unlock !== null;
            $requirementText = $this->getRequirementText($achievement, $completedCount, $completionPercent, $hasApprovedComment, $hasCertificate);

            $result[] = [
                'id' => $id,
                'title' => $achievement['title'] ?? 'Conquista',
                'description' => $achievement['description'] ?? '',
                'image_url' => $this->achievementImageUrl($achievement['image'] ?? '', $product),
                'unlocked' => $unlocked,
                'unlocked_at' => $unlock?->unlocked_at?->toIso8601String(),
                'requirement_text' => $requirementText,
            ];
        }

        return $result;
    }

    private function evaluateTrigger(
        array $achievement,
        int $completedCount,
        int $completionPercent,
        bool $hasApprovedComment,
        bool $hasCertificate
    ): bool {
        $trigger = $achievement['trigger'] ?? '';
        $triggerConfig = $achievement['trigger_config'] ?? [];

        return match ($trigger) {
            self::TRIGGER_FIRST_LESSON => $completedCount >= 1,
            self::TRIGGER_LESSONS_COUNT => $completedCount >= (int) ($triggerConfig['count'] ?? 0),
            self::TRIGGER_COMPLETION_PERCENT => $completionPercent >= (int) ($triggerConfig['percent'] ?? 0),
            self::TRIGGER_COURSE_COMPLETE => $completionPercent >= 100,
            self::TRIGGER_FIRST_COMMENT => $hasApprovedComment,
            self::TRIGGER_CERTIFICATE_EARNED => $hasCertificate,
            default => false,
        };
    }

    private function getRequirementText(
        array $achievement,
        int $completedCount,
        int $completionPercent,
        bool $hasApprovedComment,
        bool $hasCertificate
    ): string {
        $trigger = $achievement['trigger'] ?? '';
        $triggerConfig = $achievement['trigger_config'] ?? [];

        $labels = [
            self::TRIGGER_FIRST_LESSON => 'Complete a primeira aula',
            self::TRIGGER_LESSONS_COUNT => 'Complete ' . (int) ($triggerConfig['count'] ?? 0) . ' aulas',
            self::TRIGGER_COMPLETION_PERCENT => 'Complete ' . (int) ($triggerConfig['percent'] ?? 0) . '% do curso',
            self::TRIGGER_COURSE_COMPLETE => 'Complete 100% do curso',
            self::TRIGGER_FIRST_COMMENT => 'Publique seu primeiro comentário aprovado',
            self::TRIGGER_CERTIFICATE_EARNED => 'Emita o certificado do curso',
        ];

        return $labels[$trigger] ?? 'Desbloqueie esta conquista';
    }

    private function achievementImageUrl(string $image, Product $product): string
    {
        if ($image === '') {
            return '';
        }
        if (str_starts_with($image, 'http')) {
            return $image;
        }
        if (str_starts_with($image, '/')) {
            return rtrim(config('app.url'), '/') . $image;
        }
        if (str_contains($image, 'member-area-gamification/') || str_contains($image, 'gamification')) {
            return (new \App\Services\StorageService($product->tenant_id))->url($image);
        }
        return rtrim(config('app.url'), '/') . '/' . ltrim($image, '/');
    }
}
