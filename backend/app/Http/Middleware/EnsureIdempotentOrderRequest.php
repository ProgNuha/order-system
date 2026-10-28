<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotentOrderRequest
{
    private const RECORD_TTL_SECONDS = 60 * 60 * 24; // 24 jam — masa berlaku key

    private const LOCK_TTL_SECONDS = 30; // asumsi: request wajar selesai < 30 detik

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! $key) {
            return $next($request); // header opsional, request diproses normal
        }

        if (! Str::isUuid($key)) {
            return response()->json([
                'success' => false,
                'message' => 'Idempotency-Key harus berupa UUID yang valid.',
            ], 422);
        }

        $recordCacheKey = "idempotency:order:{$key}";
        $lockCacheKey = "{$recordCacheKey}:lock";
        $fingerprint = hash('sha256', $request->getContent());

        $record = Cache::get($recordCacheKey);

        if ($record) {
            if ($record['fingerprint'] !== $fingerprint) {
                return response()->json([
                    'success' => false,
                    'message' => 'Idempotency-Key ini sudah dipakai dengan payload yang berbeda.',
                ], 409);
            }

            return response()->json($record['body'], $record['status']);
        }

        // Klaim lock atomik (SETNX via Redis) — cegah request lain dengan key
        // yang sama ikut diproses bersamaan selagi yang pertama masih jalan.
        if (! Cache::add($lockCacheKey, true, self::LOCK_TTL_SECONDS)) {
            return response()->json([
                'success' => false,
                'message' => 'Request dengan Idempotency-Key ini sedang diproses, coba lagi sebentar.',
            ], 409);
        }

        $response = $next($request);

        // Simpen SEMUA hasil (sukses maupun gagal) — retry dengan key+payload
        // sama harus dapet response yang identik, bukan diproses ulang.
        Cache::put($recordCacheKey, [
            'fingerprint' => $fingerprint,
            'status' => $response->getStatusCode(),
            'body' => json_decode($response->getContent(), true),
        ], self::RECORD_TTL_SECONDS);

        Cache::forget($lockCacheKey);

        return $response;
    }
}
