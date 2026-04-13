<?php

namespace App\Http\Controllers;

use App\Models\MemberNotification;
use App\Models\MemberPushSubscription;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberAreaNotificationsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $product = $this->getProduct($request);
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Não autorizado.'], 401);
        }

        $perPage = min((int) $request->input('per_page', 20), 50);
        $notifications = MemberNotification::forUser($user->id)
            ->forProduct($product->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $unreadCount = MemberNotification::forUser($user->id)
            ->forProduct($product->id)
            ->unread()
            ->count();

        $pushSubscribed = MemberPushSubscription::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->whereNotNull('endpoint')
            ->where('endpoint', '!=', '')
            ->whereNotNull('keys->auth')
            ->where('keys->auth', '!=', '')
            ->whereNotNull('keys->p256dh')
            ->where('keys->p256dh', '!=', '')
            ->exists();

        return response()->json([
            'data' => $notifications->items(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
            'unread_count' => $unreadCount,
            'push_subscribed' => $pushSubscribed,
        ]);
    }

    public function markRead(Request $request, MemberNotification $notification): JsonResponse
    {
        $product = $this->getProduct($request);
        $user = $request->user();
        if (! $user || $notification->user_id !== $user->id || $notification->product_id !== $product->id) {
            return response()->json(['message' => 'Acesso negado.'], 403);
        }

        $notification->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $product = $this->getProduct($request);
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Não autorizado.'], 401);
        }

        MemberNotification::forUser($user->id)
            ->forProduct($product->id)
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function clearAll(Request $request): JsonResponse
    {
        $product = $this->getProduct($request);
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Não autorizado.'], 401);
        }

        $deleted = MemberNotification::forUser($user->id)
            ->forProduct($product->id)
            ->delete();

        return response()->json(['success' => true, 'deleted' => $deleted]);
    }

    private function getProduct(Request $request): Product
    {
        $product = $request->route('product');
        if (! $product instanceof Product) {
            abort(404, 'Área de membros não encontrada.');
        }
        return $product;
    }
}
