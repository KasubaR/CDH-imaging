<?php

namespace App\Services\Backup;

use RuntimeException;

/**
 * Encrypts/decrypts backup files with AES-256-CBC + a random IV prepended to the ciphertext.
 * The passphrase is config('cdh.backup.encryption_key') (BACKUP_ENCRYPTION_KEY).
 */
class BackupEncryption
{
    private const CIPHER = 'aes-256-cbc';

    public function requireKey(): string
    {
        $key = config('cdh.backup.encryption_key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('BACKUP_ENCRYPTION_KEY is not configured.');
        }

        return $key;
    }

    public function encryptFile(string $plaintextPath, string $ciphertextPath): void
    {
        $key = $this->requireKey();
        $plaintext = file_get_contents($plaintextPath);

        if ($plaintext === false) {
            throw new RuntimeException("Unable to read plaintext backup at {$plaintextPath}.");
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER);

        if ($ivLength === false) {
            throw new RuntimeException('Unable to determine cipher IV length.');
        }

        $iv = random_bytes($ivLength);
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $this->deriveKey($key), OPENSSL_RAW_DATA, $iv);

        if ($ciphertext === false) {
            throw new RuntimeException('Unable to encrypt backup file.');
        }

        if (file_put_contents($ciphertextPath, $iv.$ciphertext) === false) {
            throw new RuntimeException("Unable to write encrypted backup at {$ciphertextPath}.");
        }
    }

    public function decryptFile(string $ciphertextPath, string $plaintextPath): void
    {
        $key = $this->requireKey();
        $payload = file_get_contents($ciphertextPath);

        if ($payload === false) {
            throw new RuntimeException("Unable to read encrypted backup at {$ciphertextPath}.");
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER);

        if ($ivLength === false || strlen($payload) <= $ivLength) {
            throw new RuntimeException('Encrypted backup payload is invalid.');
        }

        $iv = substr($payload, 0, $ivLength);
        $ciphertext = substr($payload, $ivLength);
        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $this->deriveKey($key), OPENSSL_RAW_DATA, $iv);

        if ($plaintext === false) {
            throw new RuntimeException('Unable to decrypt backup file.');
        }

        if (file_put_contents($plaintextPath, $plaintext) === false) {
            throw new RuntimeException("Unable to write decrypted backup at {$plaintextPath}.");
        }
    }

    private function deriveKey(string $passphrase): string
    {
        return hash('sha256', $passphrase, true);
    }
}
