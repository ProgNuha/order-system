<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CancelOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\Order\OrderTransitionService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderTransitionService $transitions,
    ) {}

    public function pay(Order $order): JsonResponse
    {
        $this->authorize('pay', $order);

        return response()->json([
            'success' => true,
            'message' => 'Order ditandai PAID',
            'data' => new OrderResource($this->transitions->markAsPaid($order)),
        ]);
    }

    public function ship(Order $order): JsonResponse
    {
        $this->authorize('ship', $order);

        return response()->json([
            'success' => true,
            'message' => 'Order ditandai SHIPPED',
            'data' => new OrderResource($this->transitions->markAsShipped($order)),
        ]);
    }

    public function complete(Order $order): JsonResponse
    {
        $this->authorize('complete', $order);

        return response()->json([
            'success' => true,
            'message' => 'Order ditandai COMPLETED',
            'data' => new OrderResource($this->transitions->markAsCompleted($order)),
        ]);
    }

    public function cancel(CancelOrderRequest $request, Order $order): JsonResponse
    {
        $order = $this->transitions->cancel($order, $request->validated('cancel_reason'));

        return response()->json([
            'success' => true,
            'message' => 'Order dibatalkan',
            'data' => new OrderResource($order),
        ]);
    }
}
