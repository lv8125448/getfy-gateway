<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\SalesAchievement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SalesAchievementsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Platform/Conquistas/Index', [
            'achievements' => SalesAchievement::query()
                ->orderBy('sort_order')
                ->orderBy('threshold')
                ->get()
                ->map(fn (SalesAchievement $a) => [
                    'id' => $a->id,
                    'slug' => $a->slug,
                    'name' => $a->name,
                    'threshold' => (float) $a->threshold,
                    'image' => $a->image,
                    'sort_order' => (int) $a->sort_order,
                    'is_active' => (bool) $a->is_active,
                ])
                ->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9-]+$/', 'unique:sales_achievements,slug'],
            'name' => ['required', 'string', 'max:180'],
            'threshold' => ['required', 'numeric', 'min:0'],
            'image' => ['nullable', 'string', 'max:2048'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $achievement = SalesAchievement::query()->create([
            'slug' => $validated['slug'],
            'name' => $validated['name'],
            'threshold' => (float) $validated['threshold'],
            'image' => $validated['image'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return response()->json(['ok' => true, 'achievement' => $achievement]);
    }

    public function update(Request $request, SalesAchievement $salesAchievement): JsonResponse
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9-]+$/', Rule::unique('sales_achievements', 'slug')->ignore($salesAchievement->id)],
            'name' => ['required', 'string', 'max:180'],
            'threshold' => ['required', 'numeric', 'min:0'],
            'image' => ['nullable', 'string', 'max:2048'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $salesAchievement->update([
            'slug' => $validated['slug'],
            'name' => $validated['name'],
            'threshold' => (float) $validated['threshold'],
            'image' => $validated['image'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return response()->json(['ok' => true]);
    }

    public function destroy(SalesAchievement $salesAchievement): JsonResponse
    {
        $salesAchievement->delete();

        return response()->json(['ok' => true]);
    }

    public function uploadImage(Request $request, SalesAchievement $salesAchievement): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:4096', 'mimes:jpg,jpeg,png,webp,gif,svg'],
        ]);

        $path = $request->file('file')->store('conquistas', 'public');
        $url = Storage::disk('public')->url($path);
        if (! str_starts_with($url, 'http')) {
            $url = rtrim((string) config('app.url'), '/').'/'.ltrim($url, '/');
        }
        $salesAchievement->update(['image' => $url]);

        return response()->json(['ok' => true, 'url' => $url]);
    }
}
