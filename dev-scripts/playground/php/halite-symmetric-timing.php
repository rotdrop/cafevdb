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

$data = file_get_contents("php://stdin");

echo 'DATA OF SIZE ' . strlen($data) . PHP_EOL;
$timeStart = microtime(true);
$encrypted = Halite\Symmetric\Crypto::encrypt(new HiddenString($data), $encryptionKey);
$timeEnd = microtime(true);
echo 'ENCRYPTION SECONDS ' . ($timeEnd - $timeStart) . PHP_EOL;
$decrypted = Halite\Symmetric\Crypto::decrypt($encrypted, $encryptionKey);
$decrypted = $decrypted->getString();
echo 'DECRYPTION SECONDS ' . (microtime(true) - $timeEnd) . PHP_EOL;
echo "CORRECT " . (int)($decrypted == $data) . PHP_EOL;
