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

$inputStream = fopen('php://memory', 'w+');
fwrite($inputStream, $data, strlen($data));
rewind($inputStream);
$haliteInput = new Halite\Stream\WeakReadOnlyFile($inputStream);

$outputStream = fopen('php://memory', 'w+');
$haliteOutput = new Halite\Stream\MutableFile($outputStream);

echo 'DATA OF SIZE ' . strlen($data) . PHP_EOL;
$timeStart = microtime(true);
Halite\File::encrypt($haliteInput, $haliteOutput, $encryptionKey);

rewind($outputStream);
$start = base64_encode(fread($outputStream, 10));
echo "START " . $start . PHP_EOL;
rewind($outputStream);

$haliteInput = new Halite\Stream\WeakReadOnlyFile($outputStream);
ftruncate($inputStream, 0);
$haliteOutput = new Halite\Stream\MutableFile($inputStream);

$timeEnd = microtime(true);
echo 'ENCRYPTION SECONDS ' . ($timeEnd - $timeStart) . PHP_EOL;

Halite\File::decrypt($haliteInput, $haliteOutput, $encryptionKey);

// $decrypted = Halite\Symmetric\Crypto::decrypt($encrypted, $encryptionKey);
echo 'DECRYPTION SECONDS ' . (microtime(true) - $timeEnd) . PHP_EOL;

rewind($inputStream);
$decrypted = fread($inputStream, strlen($data));
echo "CORRECT " . (int)($decrypted == $data) . PHP_EOL;
