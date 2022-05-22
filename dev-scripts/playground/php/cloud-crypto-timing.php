<?php

include_once __DIR__ . '/console-setup.php';

include_once __DIR__ . '/../../../vendor/autoload.php';

use OCP\Security\ICrypto;

/** @var ICrypto $crypto */
$crypto = \OC::$server->query(ICrypto::class);

$password = 'foo!bar';
$password = random_bytes(32);

$data = file_get_contents("php://stdin");
echo 'DATA OF SIZE ' . strlen($data) . PHP_EOL;

$timeStart = microtime(true);
$encrypted = $crypto->encrypt($data, $password);
$timeEnd = microtime(true);
echo 'ENCRYPTION SECONDS ' . ($timeEnd - $timeStart) . PHP_EOL;
$decrypted = $crypto->decrypt($encrypted, $password);
echo 'DECRYPTION SECONDS ' . (microtime(true) - $timeEnd) . PHP_EOL;
echo "CORRECT " . (int)($decrypted == $data) . PHP_EOL;
