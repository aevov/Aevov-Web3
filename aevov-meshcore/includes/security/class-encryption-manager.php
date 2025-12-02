<?php
/**
 * Encryption Manager
 *
 * Manages end-to-end encryption for mesh network communications.
 *
 * @package AevovMeshcore
 */

namespace Aevov\Meshcore\Security;

/**
 * Encryption Manager Class
 */
class EncryptionManager
{
    /**
     * Encryption algorithm
     *
     * @var string
     */
    private string $algorithm = 'aes-256-gcm';

    /**
     * Encrypt data
     *
     * @param string $data Data to encrypt
     * @param string $key Encryption key
     * @return array Encrypted data with IV and tag
     */
    public function encrypt(string $data, string $key): array
    {
        $iv = random_bytes(openssl_cipher_iv_length($this->algorithm));
        $tag = '';

        $encrypted = openssl_encrypt(
            $data,
            $this->algorithm,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return [
            'data' => base64_encode($encrypted),
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag)
        ];
    }

    /**
     * Decrypt data
     *
     * @param array $encrypted_data Encrypted data with IV and tag
     * @param string $key Encryption key
     * @return string|null Decrypted data or null on failure
     */
    public function decrypt(array $encrypted_data, string $key): ?string
    {
        if (!isset($encrypted_data['data'], $encrypted_data['iv'], $encrypted_data['tag'])) {
            return null;
        }

        $decrypted = openssl_decrypt(
            base64_decode($encrypted_data['data']),
            $this->algorithm,
            $key,
            OPENSSL_RAW_DATA,
            base64_decode($encrypted_data['iv']),
            base64_decode($encrypted_data['tag'])
        );

        return $decrypted !== false ? $decrypted : null;
    }

    /**
     * Generate encryption key
     *
     * @return string Key
     */
    public function generate_key(): string
    {
        return random_bytes(32); // 256 bits
    }

    /**
     * Derive key from password
     *
     * @param string $password Password
     * @param string $salt Salt
     * @return string Key
     */
    public function derive_key(string $password, string $salt): string
    {
        return hash_pbkdf2('sha256', $password, $salt, 100000, 32, true);
    }
}
