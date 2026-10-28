<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class InsufficientStockException extends Exception
{
    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('Stok tidak mencukupi');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'errors' => $this->errors,
        ], 422);
    }
}
