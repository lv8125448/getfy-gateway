<?php

namespace App\Http\Controllers;

use App\Services\SalesAchievementsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConquistasController extends Controller
{
    public function __construct(
        protected SalesAchievementsService $salesAchievementsService
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        if (! $user || ! $user->canAccessPanel()) {
            abort(403);
        }

        $progress = $this->salesAchievementsService->getProgressForTenant($user->tenant_id);

        return Inertia::render('Conquistas/Index', [
            'progress' => $progress,
            'username' => $user->username,
        ]);
    }

    public function share(Request $request, string $slug): Response
    {
        $validSlugs = $this->salesAchievementsService->getValidSlugs();
        if (! in_array($slug, $validSlugs, true)) {
            abort(404);
        }

        $achievement = $this->salesAchievementsService->getAchievementBySlug($slug);
        if (! $achievement) {
            abort(404);
        }

        $displayUsername = $request->query('u');
        if ($displayUsername !== null) {
            $displayUsername = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $displayUsername);
        }

        return Inertia::render('Conquistas/Share', [
            'achievement' => $achievement,
            'displayUsername' => $displayUsername ? '@' . $displayUsername : null,
            'shareUrl' => url()->current() . ($displayUsername ? '?u=' . rawurlencode($displayUsername) : ''),
        ]);
    }
}
