<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiApplication;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentStatusController extends Controller
{
    public function show(Request $request, string $order): JsonResponse
    {
        $app = $request->attributes->get('api_application');
        if (! $app instanceof ApiApplication) {
            abort(500, 'API application not resolved');
        }

        $orderModel = Order::where('id', $order)
            ->where('api_application_id', $app->id)
            ->first();

        if (! $orderModel) {
            return response()->json(['message' => 'Pedido não encontrado.'], 404);
        }

        return response()->json([
            'order_id' => $orderModel->id,
            'status' => $orderModel->status,
            'amount' => (float) $orderModel->amount,
            'email' => $orderModel->email,
            'gateway' => $orderModel->gateway,
            'gateway_id' => $orderModel->gateway_id,
            'metadata' => $orderModel->metadata ?? [],
            'created_at' => $orderModel->created_at?->toIso8601String(),
            'updated_at' => $orderModel->updated_at?->toIso8601String(),
        ]);
    }
}
