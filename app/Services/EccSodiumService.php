<?php

namespace App\Services;

use Exception;

class EccSodiumService
{
    /**
     * Enkripsi data menggunakan Public Key (ECC Curve25519)
     */
    public function encrypt($plaintext, $base64PublicKey)
    {
        // Decode base64 menjadi raw bytes
        $publicKeyBytes = base64_decode($base64PublicKey);

        if (strlen($publicKeyBytes) !== SODIUM_CRYPTO_BOX_PUBLICKEYBYTES) {
            throw new Exception("Ukuran Public Key tidak valid untuk Sodium ECC.");
        }

        // sodium_crypto_box_seal membuat kunci sementara secara otomatis (Ephemeral ECDH)
        // dan mengenkripsi data menggunakan Public Key penerima.
        $ciphertext = sodium_crypto_box_seal($plaintext, $publicKeyBytes);

        // Kembalikan dalam bentuk Base64 agar aman disimpan di database MySQL
        return base64_encode($ciphertext);
    }

    /**
     * Dekripsi data menggunakan pasangan Private Key dan Public Key
     */
    public function decrypt($base64Ciphertext, $base64PrivateKey, $base64PublicKey)
    {
        // Decode semua kunci
        $privateKeyBytes = base64_decode($base64PrivateKey);
        $publicKeyBytes = base64_decode($base64PublicKey);
        $ciphertextBytes = base64_decode($base64Ciphertext);

        // Gabungkan private dan public key menjadi format keypair yang dikenali Sodium
        $keypair = sodium_crypto_box_keypair_from_secretkey_and_publickey(
            $privateKeyBytes, 
            $publicKeyBytes
        );

        // Buka segel enkripsi
        $plaintext = sodium_crypto_box_seal_open($ciphertextBytes, $keypair);

        if ($plaintext === false) {
            throw new Exception("Gagal mendekripsi data. Kunci salah atau data rusak.");
        }

        return $plaintext;
    }
}