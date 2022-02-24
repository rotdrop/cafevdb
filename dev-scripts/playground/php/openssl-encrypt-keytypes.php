<?php

$config = [
  'private_key_type' => OPENSSL_KEYTYPE_RSA,
  'private_key_bits' => 4096,
  // 'private_key_type' => OPENSSL_KEYTYPE_EC,
  // 'priqvate_key_bits' => 512,
  // 'curve_name' => 'prime256v1',
];

// Create the private and public key
$res = openssl_pkey_new($config);

// Extract the private key from $res to $privKey
openssl_pkey_export($res, $privKey);

// Extract the public key from $res to $pubKey
$pubKey = openssl_pkey_get_details($res);
$pubKey = $pubKey["key"];

$data = 'plaintext data goes here';

$signingAlgo = OPENSSL_ALGO_SHA512;
openssl_sign($data, $signature, $privKey, $signingAlgo);

echo 'SIG: ' . strlen($signature) . PHP_EOL;

openssl_verify($data, $signature, $pubKey, $signingAlgo);

// Encrypt the data to $encrypted using the public key
openssl_public_encrypt($data, $encrypted, $pubKey);

// Decrypt the data using the private key and store the results in $decrypted
openssl_private_decrypt($encrypted, $decrypted, $privKey);

echo $decrypted;
