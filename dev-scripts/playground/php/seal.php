#!/usr/bin/php
<?php

include_once __DIR__ . '/console-setup.php';

include_once __DIR__ . '/../../../vendor/autoload.php';

// use OCP\Security\ICrypto;
// $crypto = \OC::$server->query(ICrypto::class);

// Play around with sealing to have multi-reader encryption.

$keys = [];
$pubKeys = [];
$privKeys = [];
for ($i = 0; $i < 4; $i++) {
  $keys[] = $key = openssl_pkey_new();
  $details = openssl_pkey_get_details($key);
  $pubKeys[] = $details['key'];
  $privKeys[] = openssl_pkey_get_private($key);
}

$data = 'Hello World! Blub';

$cipherAlgo = 'aes-256-gcm'; // 'AES-256-CTR';
$iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($cipherAlgo));
$ivBefore = $iv;

$result = openssl_seal(
  $data,
  $sealedData,
  $sealedKeys,
  $pubKeys,
  $cipherAlgo,
  $iv);


echo 'DATA  : ' . strlen($data) . ' ' . $data . PHP_EOL;
echo "CIPHER: $cipherAlgo" . PHP_EOL;
echo "IV-LEN: " . openssl_cipher_iv_length($cipherAlgo) . PHP_EOL;
echo "IV    : " . bin2hex($iv) . PHP_EOL;
echo "IV PRE: " . bin2hex($iv) . PHP_EOL;
echo 'RESULT: ' . strlen($sealedData) . ' ' . bin2hex($sealedData) . PHP_EOL;
echo 'COUNT : ' . count($sealedKeys) . PHP_EOL;

echo PHP_EOL;

// Try decrypt
foreach ($keys as $i => $key) {
  // try decrypt the keys
  openssl_private_decrypt($sealedKeys[$i], $unsealedKey, $key);
  echo "UNSEAL:  " . bin2hex($unsealedKey) . PHP_EOL;

  echo "DECRYPT: " . openssl_decrypt($sealedData, $cipherAlgo, $unsealedKey, OPENSSL_RAW_DATA, $iv) . PHP_EOL;
  openssl_open($sealedData, $decrypted, $sealedKeys[$i], $key, $cipherAlgo, $iv);
  echo "DECRYPT: " . $decrypted . PHP_EOL;
}

use OCP\Security\ICrypto;
$crypto = \OC::$server->query(ICrypto::class);

// Add a HMAC to the
function sealData()
{
}
