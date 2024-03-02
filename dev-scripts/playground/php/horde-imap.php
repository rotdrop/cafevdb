<?php

namespace OCA\CAFVDB;

include_once __DIR__ . '/console-setup.php';

include_once __DIR__ . '/../../../vendor/autoload.php';

use Horde_Imap_Client_Socket;
use Horde_Imap_Client;
use Horde_Imap_Client_Search_Query;

$params = [
  'username' => 'XXXX',
  'password' => 'XXXX',
  'hostspec' => 'XXXX',
  'port' => 143,
  'secure' => 'tls',
  'timeout' => 20,
  'context' => [
    'ssl' => [
      'verify_peer' => true,
      'verify_peer_name' => true,
    ],
  ],
];

$client = new \Horde_Imap_Client_Socket($params);
$client->login();

$mboxList = $client->listMailboxes('*', Horde_Imap_Client::MBOX_ALL, [ 'flat' => true ]);

$messageId = '<28a6ab10218b97e6eac23c086a282a80@owncloud.cafev.de>';
$query = new Horde_Imap_Client_Search_Query();
$query->headerText('message-id', $messageId);

foreach ($mboxList as $mbox) {
  try {
    $result = $client->search($mbox, $query);
  } catch (\Throwable $t) {
    // don't care
    continue;
  }
  if ($result['count'] > 0) {
    print_r($result);
  }
}
