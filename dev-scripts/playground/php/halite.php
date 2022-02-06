#!/usr/bin/php
<?php

include_once __DIR__ . '/console-setup.php';

include_once __DIR__ . '/../../../vendor/autoload.php';

// use OCP\Security\ICrypto;
// $crypto = \OC::$server->query(ICrypto::class);

use ParagonIE\Halite;
use ParagonIE\HiddenString\HiddenString;

// $seal_keypair = Halite\KeyFactory::generateEncryptionKeyPair();

// $seal_secret = $seal_keypair->getSecretKey();
// $seal_public = $seal_keypair->getPublicKey();

$password = 'My Password';

$salt = random_bytes(16); // Do this once, then make it a constant
$keyPair = Halite\KeyFactory::deriveEncryptionKeyPair(
  new HiddenString($password),
  $salt,
  Halite\KeyFactory::INTERACTIVE
);
$privateKey = $keyPair->getSecretKey();
$publicKey = $keyPair->getPublicKey();

$string = 'Hello World!';

$encrypted = Halite\Asymmetric\Crypto::seal(new HiddenString($string), $publicKey);

// simulate reconstruction of keys, using same salt and password:
$keyPair = Halite\KeyFactory::deriveEncryptionKeyPair(
  new HiddenString($password),
  $salt,
  Halite\KeyFactory::INTERACTIVE
);
$privateKey = $keyPair->getSecretKey();
$publicKey = $keyPair->getPublicKey();

echo bin2hex($privateKey->getRawKeyMaterial()) . PHP_EOL;
echo bin2hex($publicKey->getRawKeyMaterial()) . PHP_EOL;


$decrypted = Halite\Asymmetric\Crypto::unseal($encrypted, $privateKey);

echo $encrypted . PHP_EOL;
echo $decrypted->getString() . PHP_EOL;
echo SODIUM_CRYPTO_PWHASH_SALTBYTES . PHP_EOL;
echo PHP_EOL;

$ossl = openssl_pkey_new([
  'private_key_type' => OPENSSL_KEYTYPE_EC,
  'private_key_bits' => 512,
  'curve_name' => 'prime256v1',
]);

/* Extract the private key from $res to $privKey */
openssl_pkey_export($ossl, $privKey, $password);

/* Extract the public key from $res to $pubKey */
$pubKey = openssl_pkey_get_details($ossl)['key'];

print_r($privKey);
print_r($pubKey);

$privKey = openssl_pkey_get_private($privKey, $password);
$privKey = openssl_pkey_get_private($privKey, $password);
echo get_class($privKey) . PHP_EOL;
print_r(openssl_pkey_get_details($privKey)['key']);

$decryptedData = $string;
openssl_public_encrypt($decryptedData, $encryptedData, $pubKey);

openssl_private_decrypt($encryptedData, $decryptedData, $privKey);

echo $decryptedData . PHP_EOL;
