<?php

namespace App\Services;

class EncryptService
{
    private string $ciphering;
    private string $encryptionIv;
    private string $encryptionKey;
    private int $options;

    public function __construct()
    {
        $this->ciphering     = 'AES-128-CTR';
        $this->encryptionIv  = '1234567891011121';
        $this->encryptionKey = 'l8d4re62fy26c7613cc6g8b36416f389b7boe13855aafc8fdd7db78418f89e5k';
        $this->options       = 0;
    }

    /**
     * Encrypt text
     */
    public function encrypt(?string $text): ?string
    {
        if (empty($text)) {
            return null;
        }

        return openssl_encrypt(
            $text,
            $this->ciphering,
            $this->encryptionKey,
            $this->options,
            $this->encryptionIv
        );
    }

    /**
     * Decrypt text
     */
    public function decrypt(?string $encrypted): ?string
    {
        if (empty($encrypted)) {
            return null;
        }

        return openssl_decrypt(
            $encrypted,
            $this->ciphering,
            $this->encryptionKey,
            $this->options,
            $this->encryptionIv
        );
    }
}
