<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Service;

use Psr\Log\LoggerInterface;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Service\IMAP\IMAPMessage;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

/**
 * Some support functions for dealing with SentEmail entities.
 */
class SentEmailsService
{
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected EntityManager $entityManager,
    protected IMAPService $imapService,
    protected LoggerInterface $logger,
  ) {
  }
  // phpcs:enable

  /**
   * Try to reconstruct a missing SentEmail entity from the given
   * IMAP-message. As of now any follow-up headers are just ignored, so this
   * generates only a single entry with references to other IMAP messages.
   *
   * @param IMAPMessage $imapMessage
   *
   * @param bool $persist Whether to persist the constructed entities. Default \true.
   *
   * @param bool $flush Whether to flush the persisted entities to the
   * database. Ignored if $persist === \false, defaults to \false.
   *
   * @return Entities\SentEmail
   */
  public function sentEmailFromMessage(
    IMAPMessage $imapMessage,
    bool $persist = true,
    bool $flush = false,
  ): Entities\SentEmail {
    $bulkRecipients = [];
    /** @var Address $address */
    foreach ($imapMessage->getTo()->iterate() as $address) {
      $bulkRecipients[] = $address->getLabel() . ' <' . $address->getEmail() . '>';
    }
    foreach ($imapMessage->getBCC()->iterate() as $address) {
      $bulkRecipients[] = $address->getLabel() . ' <' . $address->getEmail() . '>';
    }
    $carbonCopy = [];
    foreach ($imapMessage->getCC()->iterate() as $address) {
      $carbonCopy[] = $address->getLabel() . ' <' . $address->getEmail() . '>';
    }

    /** @var Entities\SentEmail $sentEmail */
    $sentEmail = (new Entities\SentEmail);
    $sentEmail
      ->setSubject($imapMessage->getSubject())
      ->setBulkRecipients(implode(';', $bulkRecipients))
      ->setCc(implode(';', $carbonCopy))
      ->setMessageId($imapMessage->getMessageId())
      ->setHtmlBody($imapMessage->htmlMessage)
      ->setBulkRecipientsHash(hash('md5', $sentEmail->getBulkRecipients()))
      ->setSubjectHash(hash('md5', $sentEmail->getSubject()))
      ->setHtmlBodyHash(hash('md5', $sentEmail->getHtmlBody()))
      ;
    $this->logInfo(
      'REFERENCES WHICH ARE IGNORED '
      . $imapMessage->getRawReferences()
      . ' InReplyTO: ' . $imapMessage->getRawInReplyTo());

    if ($persist) {
      $this->persist($sentEmail);
      if ($flush) {
        $this->flush(useTransaction: true);
      }
    }

    return $sentEmail;
  }

  /**
   * Try to reconstruct a missing SentEmail entity from the given
   * IMAP-message. As of now any follow-up headers are just ignored, so this
   * generates only a single entry with references to other IMAP messagesx
   *
   * @param string $messageId Given message id.
   *
   * @param bool $persist Whether to persist the constructed entities. Default \true.
   *
   * @param bool $flush Whether to flush the persisted entities to the
   * database. Ignored if $persist === \false, defaults to \false.
   *
   * @return Entities\SentEmail
   */
  public function sentEmailFromMessageId(
    string $messageId,
    bool $persist = true,
    bool $flush = false,
  ): ?Entities\SentEmail {
    $sentEmail = $this->getDatabaseRepository(Entities\SentEmail::class)->find($messageId);
    if ($sentEmail !== null) {
      return $sentEmail;
    }
    $imapMessage = $this->imapService->searchMessageId($messageId);
    if ($imapMessage === null) {
      return null;
    }
    return $this->sentEmailFromMessage($imapMessage, $persist, $flush);
  }
}
