<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use App\Services\EccSodiumService;

class EccEncrypt implements CastsAttributes
{
    /**
     * Mengubah data dari database (Ciphertext) menjadi data asli (Plaintext)
     */
    public function get($model, string $key, $value, array $attributes)
    {
        // Jika data kosong atau null, langsung kembalikan
        if (is_null($value) || $value === '') {
            return $value;
        }

        try {
            $ecc = app(EccSodiumService::class);
            
            // Mengambil key dari env
            $privateKey = env('ECC_PRIVATE_KEY');
            $publicKey = env('ECC_PUBLIC_KEY');

            // Jika key tidak ada, jangan paksa dekripsi agar tidak error
            if (!$privateKey || !$publicKey) {
                return $value;
            }

            return $ecc->decrypt($value, $privateKey, $publicKey);
        } catch (\Exception $e) {
            // Jika gagal dekripsi (misal data bukan format ECC), kembalikan data aslinya
            return $value;
        }
    }

    /**
     * Mengubah data asli (Plaintext) menjadi data rahasia (Ciphertext) sebelum simpan ke DB
     */
    public function set($model, string $key, $value, array $attributes)
    {
        // Jika data kosong, jangan dienkripsi
        if (is_null($value) || $value === '') {
            return $value;
        }

        try {
            $ecc = app(EccSodiumService::class);
            $publicKey = env('ECC_PUBLIC_KEY');

            if (!$publicKey) {
                return $value;
            }

            return $ecc->encrypt($value, $publicKey);
        } catch (\Exception $e) {
            return $value;
        }
    }
}