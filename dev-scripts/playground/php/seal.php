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

// openssl_seal() seemingly does not work with gcm-ciphers as the
// authentication tag is somehow not communicated.

interface IKeyCryptor
{
  public function encrypt(string $data):string;
  public function decrypt(string $data):string;
};

class OpenSSLAsymCryptor implements IKeyCryptor
{
  private $userId;

  private $privKey = null;

  private $pubKey = null;

  public function __construct(string $userId, $privKey = null, string $password = null)
  {
    $this->userId = $userId;
    if (!empty($privKey)) {
      $this->setPrivateKey($privKey, $password);
    }
  }

  public function setPrivateKey($privKey, $password = null)
  {
    $this->privKey =openssl_pkey_get_private($privKey, $password);
    $details = openssl_pkey_get_details($this->privKey);
    $this->setPublicKey($details['key']);
  }

  public function setPublicKey($pubKey)
  {
    $this->pubKey = $pubKey;
  }

  public function encrypt(string $data):string
  {
    openssl_public_encrypt($decryptedData, $encryptedData, $this->pubKey);
    return $encryptedData;
  }

  public function decrypt(string $data):string
  {
    openssl_private_decrypt($encryptedData, $decryptedData, $this->privKey);
    return $decryptedData;
  }
};

function sealData(string $data, array $keyEncryption):?string
{
  $sealKey = \random_bytes(64);
  $encryptedData = $crypto->encrypt($data, $sealKey);

  /** @var IKeyCryptor $sealCryptor */
  foreach ($keyEncryption as $userId => $sealCryptor) {
    if (is_callable($sealCryptor)) {
      $sealData[] = $userId . ':' . base64_encode($sealCryptor->encrypt($sealKey));
    }
  }
  $sealedData = sprintf('%08d', strlen($encryptedData));
  $sealedData .= $encryptedData . ';';
  $sealedData .= implode(';', $sealData);
  return $sealedData;
}

function unsealData(string $data, string $userId, IKeyCryptor $keyCryptor):?string
{
  $length = (int)substr($data, 0, 8);
  $encryptedData = substr($data, 9, $length);
  $keyData = explode(';', substr($data, 10 + $length));
  foreach ($keyData as $seal) {
    list($keyUser, $sealedKey) = explode(':', $seal);
    if ($user == $keyUser)  {
      $key = $keyCryptor->decrypt($sealedKey);
      return $crypto->decrypt($encryptedData, $key);
    }
  }
  return null;
}
