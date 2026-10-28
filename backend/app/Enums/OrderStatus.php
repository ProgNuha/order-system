<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'PENDING';
    case PAID = 'PAID';
    case SHIPPED = 'SHIPPED';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';
}
