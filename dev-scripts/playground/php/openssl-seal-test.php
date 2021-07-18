<?php

const NUM_KEYS = 4;

for ($i = 0; $i < NUM_KEYS; $i++) {
  $keys[] = $key = openssl_pkey_new();
  $details = openssl_pkey_get_details($key);
  $pubKeys[] = $details['key'];
  $privKeys[] = openssl_pkey_get_private($key);
}

$data = 'Hello World! Blub';

$ciphers = [
  'aes-256-ctr',
  'aes-256-gcm',
];

foreach ($ciphers as $cipherAlgo) {

  echo "*** TESTING $cipherAlgo ***" . PHP_EOL . PHP_EOL;
  $iv = \random_bytes(openssl_cipher_iv_length($cipherAlgo));
  $result = openssl_seal($data, $sealedData, $sealedKeys, $pubKeys, $cipherAlgo, $iv);

  echo 'DATA  : ' . strlen($data) . ' ' . $data . PHP_EOL;
  echo "IV-LEN: " . openssl_cipher_iv_length($cipherAlgo) . PHP_EOL;
  echo "IV    : " . bin2hex($iv) . PHP_EOL;
  echo 'RESULT: ' . strlen($sealedData) . ' ' . bin2hex($sealedData) . PHP_EOL;
  echo PHP_EOL;

  // Try decrypt
  foreach ($keys as $i => $key) {
    $decrypted = null; // ;)
    openssl_open($sealedData, $decrypted, $sealedKeys[$i], $key, $cipherAlgo, $iv);
    echo "OPEN:    " . $decrypted . PHP_EOL;

    openssl_private_decrypt($sealedKeys[$i], $unsealedKey, $key);
    echo "UNSEAL:  " . bin2hex($unsealedKey) . PHP_EOL;
    echo "DECRYPT: " . openssl_decrypt($sealedData, $cipherAlgo, $unsealedKey, OPENSSL_RAW_DATA, $iv) . PHP_EOL;
  }

  echo PHP_EOL;
}
