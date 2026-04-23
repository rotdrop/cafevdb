<?php

include_once __DIR__ . '/console-setup.php';

include_once __DIR__ . '/../../../vendor/autoload.php';

use SepaQr\SepaQrData;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

$paymentData = (new SepaQrData())
  ->setName('Zahlungsempfaenger')
  ->setIban('BE123456789123456789')
  ->setPurpose('FOUR')
  ->setRemittanceText('Verwendungszweck')
  ->setInformation('Information')
  ->setAmount(100); // The amount in Euro

print_r($paymentData);

$qrOptions = new QROptions([
    'eccLevel' => QRCode::ECC_M // required by EPC standard
]);

(new QRCode($qrOptions))->render($paymentData, 'payment.png');
