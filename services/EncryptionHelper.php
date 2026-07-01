<?php

namespace App\Addons\apichan\services;

class EncryptionHelper
{
    private const CIPHER = 'AES-256-CBC';
    private const KEY_LENGTH = 32;

    private static function deriveKey(): string
    {
        $appKey = $_ENV['APP_KEY'] ?? getenv('APP_KEY') ?? '';
        if (empty($appKey)) {
            // Fallback: derive from hostname + a fixed salt
            $appKey = gethostname() . '_apichan_fallback_key_v1';
        }

        return hash('sha256', $appKey, true);
    }

    public static function encrypt(string $plaintext): string
    {
        $key = self::deriveKey();
        $iv = random_bytes(openssl_cipher_iv_length(self::CIPHER));
        $encrypted = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);

        return base64_encode($iv . $encrypted);
    }

    public static function decrypt(string $ciphertext): string
    {
        $key = self::deriveKey();
        $raw = base64_decode($ciphertext, true);
        if ($raw === false) {
            return '';
        }

        $ivLen = openssl_cipher_iv_length(self::CIPHER);
        $iv = substr($raw, 0, $ivLen);
        $encrypted = substr($raw, $ivLen);

        return openssl_decrypt($encrypted, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv) ?: '';
    }
}
