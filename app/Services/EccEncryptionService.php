<?php

namespace App\Services;

class EccEncryptionService
{
    private $curveName = 'prime256v1'; // Kurva standar NIST P-256
    private $cipherAlgo = 'aes-256-cbc'; // Algoritma simetris untuk teks

    /**
     * Generate sepasang kunci ECC baru (Public & Private)
     */
    public function generateKeyPair()
    {
        $res = openssl_pkey_new([
            "curve_name" => $this->curveName,
            "private_key_type" => OPENSSL_KEYTYPE_EC,
        ]);

        openssl_pkey_export($res, $privKey);
        $pubKey = openssl_pkey_get_details($res)['key'];

        return [
            'private' => $privKey,
            'public' => $pubKey
        ];
    }

    /**
     * Enkripsi Data (ECIES)
     */
    public function encrypt($plaintext, $serverPublicKey)
    {
        // 1. Buat kunci sementara (ephemeral) untuk proses enkripsi ini saja
        $ephemeralKeys = $this->generateKeyPair();
        $ephemeralPriv = openssl_pkey_get_private($ephemeralKeys['private']);
        $serverPub = openssl_pkey_get_public($serverPublicKey);

        // 2. Dapatkan Shared Secret menggunakan Kunci Private Sementara + Kunci Public Server
        $sharedSecret = openssl_pkey_derive($serverPub, $ephemeralPriv);
        
        // 3. Hash secret menjadi kunci AES 256-bit
        $aesKey = hash('sha256', $sharedSecret, true);

        // 4. Enkripsi teks dengan AES
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->cipherAlgo));
        $ciphertext = openssl_encrypt($plaintext, $this->cipherAlgo, $aesKey, 0, $iv);

        // Gabungkan public key sementara, IV, dan ciphertext
        $payload = json_encode([
            'ephemeral_public' => base64_encode($ephemeralKeys['public']),
            'iv' => base64_encode($iv),
            'ciphertext' => base64_encode($ciphertext)
        ]);

        return base64_encode($payload);
    }

    /**
     * Dekripsi Data (ECIES)
     */
    public function decrypt($payloadBase64, $serverPrivateKey)
    {
        $payload = json_decode(base64_decode($payloadBase64), true);

        $ephemeralPub = openssl_pkey_get_public(base64_decode($payload['ephemeral_public']));
        $serverPriv = openssl_pkey_get_private($serverPrivateKey);
        $iv = base64_decode($payload['iv']);
        $ciphertext = base64_decode($payload['ciphertext']);

        // 1. Dapatkan Shared Secret yang SAMA menggunakan Kunci Public Sementara + Kunci Private Server
        $sharedSecret = openssl_pkey_derive($ephemeralPub, $serverPriv);
        
        // 2. Hash secret menjadi kunci AES
        $aesKey = hash('sha256', $sharedSecret, true);

        // 3. Dekripsi teks
        $plaintext = openssl_decrypt($ciphertext, $this->cipherAlgo, $aesKey, 0, $iv);

        return $plaintext;
    }
}