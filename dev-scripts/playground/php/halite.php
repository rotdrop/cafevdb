#!/usr/bin/php
<?php

include_once __DIR__ . '/console-setup.php';

include_once __DIR__ . '/../../../vendor/autoload.php';

// use OCP\Security\ICrypto;
// $crypto = \OC::$server->query(ICrypto::class);

use \ParagonIE\Halite;
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

$decrypted = Halite\Asymmetric\Crypto::unseal($encrypted, $privateKey);

echo $encrypted . PHP_EOL;
echo $decrypted->getString() . PHP_EOL;
