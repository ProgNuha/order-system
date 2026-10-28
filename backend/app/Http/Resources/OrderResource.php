<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'customer_id' => $this->customer_id,
            'status' => $this->status->value,
            'total_amount' => $this->total_amount,
            'paid_at' => $this->paid_at,
            'shipped_at' => $this->shipped_at,
            'completed_at' => $this->completed_at,
            'cancelled_at' => $this->cancelled_at,
            'cancel_reason' => $this->cancel_reason,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'status_history' => $this->statusHistory(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function statusHistory(): array
    {
        $history = [
            ['status' => 'PENDING', 'at' => $this->created_at],
        ];

        if ($this->paid_at) {
            $history[] = ['status' => 'PAID', 'at' => $this->paid_at];
        }

        if ($this->shipped_at) {
            $history[] = ['status' => 'SHIPPED', 'at' => $this->shipped_at];
        }

        if ($this->completed_at) {
            $history[] = ['status' => 'COMPLETED', 'at' => $this->completed_at];
        }

        if ($this->cancelled_at) {
            $history[] = ['status' => 'CANCELLED', 'at' => $this->cancelled_at, 'reason' => $this->cancel_reason];
        }

        return $history;
    }
}
