<?php

namespace App\Helper;

use Exception;

class EncryptHelper
{
    public static function getOpenSslCipherSettings(): array
    {
        $cipherAlgorithm = 'aes-128-cbc';
        $option = 0;
        $iv = b"\eèíÕï\x02ë†\x1";

        return [
            'cipher_algorithm' => $cipherAlgorithm,
            'option' => $option,
            'iv' => $iv
        ];
    }

    /**
     * @throws Exception
     */
    public static function encrypt(string $str): string
    {
        $settings = self::getOpenSslCipherSettings();
        $encrypted = openssl_encrypt(
            $str,
            $settings['cipher_algorithm'],
            strval(config('app.key')),
            $settings['option'],
            $settings['iv'],
        );
        if ($encrypted === false) {
            throw new Exception('Failed to encrypt string');
        }
        return $encrypted;
    }

    public static function decrypt(string $cipher): bool|string
    {
        $settings = self::getOpenSslCipherSettings();
        return openssl_decrypt(
            $cipher,
            $settings['cipher_algorithm'],
            strval(config('app.key')),
            $settings['option'],
            $settings['iv'],
        );
    }
}
