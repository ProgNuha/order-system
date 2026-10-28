<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CancelOrderRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\Order\OrderCreationService;
use App\Services\Order\OrderTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderTransitionService $transitions,
        private readonly OrderCreationService $creation,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Order::query();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($customerId = $request->query('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        if ($dateFrom = $request->query('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->query('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $orders = $query->orderByDesc('created_at')->paginate((int) $request->query('per_page', 10));

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => OrderResource::collection($orders->items()),
            'meta' => [
                'page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'total_pages' => $orders->lastPage(),
            ],
        ]);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->creation->create(
            customerId: $request->validated('customer_id'),
            items: $request->validated('items'),
            createdBy: $request->user()->id,
        );

        return response()->json([
            'success' => true,
            'message' => 'Order berhasil dibuat',
            'data' => new OrderResource($order->load('items.product')),
        ], 201);
    }

    public function show(Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => new OrderResource($order->load('items.product')),
        ]);
    }

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
