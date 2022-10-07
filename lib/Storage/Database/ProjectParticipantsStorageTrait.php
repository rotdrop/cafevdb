<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2020, 2021, 2022, Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Storage\Database;

use OCP\IL10N;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Common\Util;

trait ProjectParticipantsStorageTrait
{
  /**
   * @var IL10N
   * Personal localization settings based on user preferences.
   */
  protected $l;

  /**
   * Get the name of the per-participant document folder
   *
   * @return string Translated name of the documents sub-folder.
   */
  protected function getDocumentsFolderName():string
  {
    // TRANSLATORS: folder-name
    return $this->l->t('Documents');
  }

  /**
   * @return string The name of the sub-folder containing supporting
   * documents.
   */
  protected function getSupportingDocumentsFolderName():string
  {
    // TRANSLATORS: folder-name
    return $this->l->t('SupportingDocuments');
  }

  /**
   * Get the name of the sub-folder containing supporting documents for
   * bank-transactions.
   *
   * @return string
   */
  protected function getBankTransactionsFolderName():string
  {
    // TRANSLATORS: folder-name
    return $this->l->t('BankTransactions');
  }

  /**
   * Get the name of the sub-folder containing supporting documents for
   * bank-transactions.
   *
   * @return string
   */
  protected function getReceivablesFolderName():string
  {
    // TRANSLATORS: folder-name
    return $this->l->t('Receivables');
  }

  /**
   * PME-legacy.
   *
   * @param int $compositePaymentId
   *
   * @param string $userIdSlug
   *
   * @return string
   */
  protected function getLegacyPaymentRecordFileName(int $compositePaymentId, string $userIdSlug):string
  {
    // TRANSLATORS: file-name
    return $this->l->t('PaymentRecord-%1$s-%2$d', [
      Util::dashesToCamelCase($userIdSlug, true, '_-.'),
      $compositePaymentId,
    ]);
  }

  /**
   * Generate a file-name for the given composite payment.
   *
   * @param Entities\CompositePayment $compositePayment
   *
   * @return string
   */
  protected function getPaymentRecordFileName(Entities\CompositePayment $compositePayment):string
  {
    $userIdSlug = $compositePayment->getMusician()->getUserIdSlug();
    return $this->getLegacyPaymentRecordFileName($compositePayment->getId(), $userIdSlug);
  }

  /**
   * Get the name of the sub-folder holding hard-copies of debit-mandates
   *
   * @return string Translated name of the debit-mandates folder.
   */
  protected function getDebitMandatesFolderName():string
  {
    // TRANSLATORS: folder-name
    return $this->l->t('DebitMandates');
  }

  /**
   * PME-legacy.
   */
  protected function getLegacyDebitMandateFileName(string $debitMandateReference):string
  {
    return $debitMandateReference;
  }

  /**
   * Generate a file-name for a hard-copy of the given debit-mandate.
   *
   * @param Entities\SepaDebitMandate $debitMandate
   *
   * @return string
   */
  protected function getDebitMandateFileName(Entities\SepaDebitMandate $debitMandate):string
  {
    return $this->getLegacyDebitMandateFileName($debitMandate->getMandateReference());
  }
}
