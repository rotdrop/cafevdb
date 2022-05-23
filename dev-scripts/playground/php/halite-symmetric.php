<?php

include_once __DIR__ . '/console-setup.php';

include_once __DIR__ . '/../../../vendor/autoload.php';

// use OCP\Security\ICrypto;
// $crypto = \OC::$server->query(ICrypto::class);

use ParagonIE\Halite;
use ParagonIE\HiddenString\HiddenString;

$password = 'foo!bar';
$salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
$encryptionKey = Halite\KeyFactory::deriveEncryptionKey(new HiddenString($password), $salt);

$data = 'FooBlah This is just some random data string';

$encrypted = Halite\Symmetric\Crypto::encrypt(new HiddenString($data), $encryptionKey);

echo $encrypted . PHP_EOL;

use OCP\Security\ICrypto;

$crypto = \OC::$server->get(ICrypto::class);


$encrypted = $crypto->encrypt($data, $password);
echo $encrypted . PHP_EOL;
echo (int)SODIUM_CRYPTO_STREAM_KEYBYTES . PHP_EOL;
echo (int)SODIUM_CRYPTO_PWHASH_SALTBYTES . PHP_EOL;
