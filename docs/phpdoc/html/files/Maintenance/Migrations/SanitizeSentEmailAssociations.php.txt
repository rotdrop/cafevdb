<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2024 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Maintenance\Migrations;

use Throwable;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Exceptions;

/**
 * Use real associatons for the sent email connectivity and sanitize the
 * database to make this really happen.
 */
class SanitizeSentEmailAssociations extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      "ALTER TABLE CompositePayments
  ADD COLUMN IF NOT EXISTS
    pre_notification_message_id
    VARCHAR(256) DEFAULT NULL COLLATE `ascii_bin`",
      "ALTER TABLE CompositePayments
  ADD CONSTRAINT
    FK_65D9920C9B6CD002 FOREIGN KEY IF NOT EXISTS
      (pre_notification_message_id) REFERENCES SentEmails (message_id)",
      "CREATE UNIQUE INDEX IF NOT EXISTS
  UNIQ_65D9920C9B6CD002 ON CompositePayments (pre_notification_message_id)",
      "ALTER TABLE SentEmails
  ADD COLUMN IF NOT EXISTS
    sepa_bulk_transaction_id INT DEFAULT NULL",
      "ALTER TABLE SentEmails
  ADD CONSTRAINT FK_80F49BA0ED6D4895 FOREIGN KEY IF NOT EXISTS
    (sepa_bulk_transaction_id) REFERENCES SepaBulkTransactions (id)",
      "CREATE INDEX IF NOT EXISTS
  IDX_80F49BA0ED6D4895 ON SentEmails (sepa_bulk_transaction_id)",
    ],
    self::TRANSACTIONAL => [
    ],
  ];

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Sanitize the sent-email connectivity.');
  }

  /** {@inheritdoc} */
  public function execute():bool
  {
    parent::execute();
    $sentEmailsRepository = $this->getDatabaseRepository(Entities\SentEmail::class);
    $transactions = $this->getDatabaseRepository(Entities\SepaBulkTransaction::class)->findAll();

    $this->entityManager->beginTransaction();
    try {
      /** @var Entities\SepaBulkTransaction $transaction */
      foreach ($transactions as $transaction) {
        /** @var Entities\CompositePayment $payment */
        foreach ($transaction->getPayments() as $payment) {
          $notificationMessageId = $payment->getNotificationMessageId();
          if (empty($notificationMessageId)) {
            continue;
          }
          /** @var Entities\SentEmail $sentEmail */
          $sentEmail = $sentEmailsRepository->find([ 'messageId' => $notificationMessageId, ]);
          if (!empty($sentEmail)) {
            $payment->setPreNotificationEmail($sentEmail);
            $transaction->addPreNotificationEmail($sentEmail);
            $sentEmail->setSepaBulkTransaction($transaction);
          }
        }
      }

      $this->flush();
      $this->entityManager->commit();

    } catch (Throwable $t) {
      try {
        $this->entityManager->rollback();
      } catch (Throwable $t2) {
        $t = new Exceptions\DatabaseMigrationException($this->l->t('Rollback of Migration "%s" failed.', $this->description()), $t->getCode(), $t);
      }
      throw new Exceptions\DatabaseMigrationException($this->l->t('Transactional part of Migration "%s" failed.', $this->description()), $t->getCode(), $t);
    }
    return true;
  }
}
