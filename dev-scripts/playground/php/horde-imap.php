<?php

namespace OCA\CAFVDB;

include_once __DIR__ . '/console-setup.php';

include_once __DIR__ . '/../../../vendor/autoload.php';

use DoesNotExistException;

use Horde_Imap_Client_Socket;
use Horde_Imap_Client;
use Horde_Imap_Client_Search_Query;
use Horde_Imap_Client_Fetch_Query;
use Horde_Imap_Client_Data_Fetch;
use Horde_Mime_Headers;
use Horde_Imap_Client_Data_Envelope;
use Horde_Mime_Part;
use Horde_Imap_Client_Ids;

function decodeSubject(Horde_Imap_Client_Data_Envelope $envelope): string {
  // Try a soft conversion first (some installations, eg: Alpine linux,
  // have issues with the '//IGNORE' option)
  $subject = $envelope->subject;
  $utf8 = iconv('UTF-8', 'UTF-8', $subject);
  if ($utf8 !== false) {
    return $utf8;
  }
  return iconv("UTF-8", "UTF-8//IGNORE", $subject);
}

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

$messageId = '<35deadc0b36a4392a1d3e8de4bbfe558@owncloud.cafev.de>';
$messageId = '<97029068da1b4326537cd110849c0085@owncloud.cafev.de>';
$messageId = '<7Ii6SaDqzjBPbTN7SjN6gJJXzu1JciQ8idgsrbGFEgo@orgacloud.cafev.de>';
$messageId = '<GXXUziZBakPddAdeLCnAhz7J2gODrUTvfFAxQovg4Q@orgacloud.cafev.de>';
// $messageId = '<28a6ab10218b97e6eac23c086a282a80@owncloud.cafev.de>';
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

    $query = new Horde_Imap_Client_Fetch_Query();
    $query->envelope();
    $query->structure();
    $query->headerText([
      'peek' => true,
    ]);

    $searchParams = [ 'ids' => $result['match'], ];
    $fetch = $client->fetch($mbox, $query, $searchParams)->first();

    $headersStream = $fetch->getHeaderText('0', Horde_Imap_Client_Data_Fetch::HEADER_STREAM);
    $raw_headers = stream_get_contents($headersStream);
    rewind($headersStream);

    echo $raw_headers . PHP_EOL;

    $parsedHeaders = Horde_Mime_Headers::parseHeaders($headersStream);
    fclose($headersStream);

    echo 'in-reply-to: ' . $parsedHeaders->getHeader('in-reply-to') . PHP_EOL;
    echo 'references: ' . $parsedHeaders->getHeader('references') . PHP_EOL;

    $envelope = $fetch->getEnvelope();
    echo 'subject: ' . decodeSubject($envelope) . PHP_EOL;

    $structure = $fetch->getStructure();

    /** @var Horde_Mime_Part $part */
    $partNo = 0; // invalid, starts at 1
    foreach ($structure->getParts() as $part) {
      ++$partNo;
      if ($part->getType() === 'text/html') {
        echo 'FOUND HTML MESSAGE' . PHP_EOL;
        // we need to fetch the data with a separate query ...
        $fetch_query = new Horde_Imap_Client_Fetch_Query();

        $fetch_query->bodyPart($partNo, [
          'peek' => true
        ]);
        $fetch_query->bodyPartSize($partNo);
        $fetch_query->mimeHeader($partNo, [
          'peek' => true
        ]);
        $headers = $client->fetch($mbox, $fetch_query, $searchParams);


        /* @var $bodyFetch Horde_Imap_Client_Data_Fetch */
        $bodyFetch = $headers->first();
        if (is_null($bodyFetch)) {
          throw new DoesNotExistException("Mail body for this mail could not be loaded");
        }

        $mimeHeaders = $fetch->getMimeHeader($partNo, Horde_Imap_Client_Data_Fetch::HEADER_PARSE);
        if ($enc = $mimeHeaders->getValue('content-transfer-encoding')) {
          $part->setTransferEncoding($enc);
        }
        $data = $bodyFetch->getBodyPart($partNo);
        $part->setContents($data);

        $data = $part->getContents();
        if ($data === null) {
          echo 'MAYBE FETCH FIRST ...' . PHP_EOL;
        } else {
          echo 'PRINT DATA WHICH IS NOT NULL' . PHP_EOL;
          echo $data . PHP_EOL;
        }
      }
    }
  }
}
