<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use App\Services\EccSodiumService;
use Illuminate\Support\Facades\Log;

class EccEncrypt implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        // 1. Jika data kosong, langsung kembalikan
        if (empty($value)) return $value;

        try {
            $ecc = app(EccSodiumService::class);
            $privKey = env('ECC_PRIVATE_KEY');
            $pubKey = env('ECC_PUBLIC_KEY');

            // 2. Jika key di .env belum diset, jangan paksa dekripsi
            if (!$privKey || !$pubKey) return $value;

            // 3. Coba dekripsi
            $decrypted = $ecc->decrypt($value, $privKey, $pubKey);

            // 4. Jika hasil dekripsi null atau error, kembalikan nilai asli (Plaintext)
            return $decrypted ?: $value;

        } catch (\Throwable $e) {
            // Jika terjadi error teknis apapun, jangan crash! 
            // Cukup tampilkan data aslinya saja.
            return $value;
        }
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if (empty($value)) return $value;

        try {
            $ecc = app(EccSodiumService::class);
            $pubKey = env('ECC_PUBLIC_KEY');

            if (!$pubKey) return $value;

            return $ecc->encrypt($value, $pubKey);
        } catch (\Throwable $e) {
            return $value;
        }
    }
}