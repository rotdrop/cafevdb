<?php

// include_once __DIR__ . '/../../../vendor/autoload.php';

include_once __DIR__ . '/console-setup.php';

use OCP\Security\ICrypto;

$crypto = \OC::$server->query(ICrypto::class);

echo "HELLO" . PHP_EOL;

/**
 * @param string $message The message to authenticate
 * @param string $password Password to use (defaults to `secret` in config.php)
 * @return string Calculated HMAC
 * @since 8.0.0
 */
// public function calculateHMAC(string $message, string $password = ''): string;
/**
 * Encrypts a value and adds an HMAC (Encrypt-Then-MAC)
 * @param string $plaintext
 * @param string $password Password to encrypt, if not specified the secret from config.php will be taken
 * @return string Authenticated ciphertext
 * @since 8.0.0
 */
// public function encrypt(string $plaintext, string $password = ''): string;
/**
 * Decrypts a value and verifies the HMAC (Encrypt-Then-Mac)
 * @param string $authenticatedCiphertext
 * @param string $password Password to encrypt, if not specified the secret from config.php will be taken
 * @return string plaintext
 * @throws \Exception If the HMAC does not match
 * @throws \Exception If the decryption failed
 * @since 8.0.0
 */
// public function decrypt(string $authenticatedCiphertext, string $password = ''): string;
