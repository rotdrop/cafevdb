<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2016, 2020, 2021, 2022, 2024, Claus-Justus Heine
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

use OCA\CAFEVDB\AppInfo\Application;
use OCA\CAFEVDB\Service\Registration;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;

use OCA\CAFEVDB\Common\Util;

/**
 * Actually just provide some path names, translated with the app's configured
 * locale.
 */
trait DatabaseStorageNodeNameTrait
{
  /**
   * @var IL10N
   * Personal localization settings based on the app settings.
   */
  protected $appL10n;

  /** @return IL10N */
  protected function getAppL10n():IL10N
  {
    if (empty($this->appL10n)) {
      $this->appL10n = Application::get(Registration::APP_L10N);
    }
    return $this->appL10n;
  }

  /**
   * Get the name of the per-participant document folder
   *
   * @return string Translated name of the documents sub-folder.
   */
  protected function getDocumentsFolderName():string
  {
    // TRANSLATORS: folder-name
    return $this->getAppL10n()->t('Documents');
  }

  /**
   * @return string The name of the sub-folder containing supporting
   * documents.
   */
  protected function getSupportingDocumentsFolderName():string
  {
    // TRANSLATORS: folder-name
    return $this->getAppL10n()->t('SupportingDocuments');
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
    return $this->getAppL10n()->t('BankTransactions');
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
    return $this->getAppL10n()->t('Receivables');
  }

  /**
   * Get the name of the folder containing tax office related stuff.
   *
   * @return string
   */
  protected function getTaxAuthoritiesFolderName():string
  {
    return $this->getAppL10n()->t('TaxAuthorities');
  }

  /**
   * Get the name of the folder containing tax exemption notices.
   *
   * @return string
   */
  protected function getTaxExemptionNoticesFolderName():string
  {
    return $this->getAppL10n()->t('TaxExemptionNotices');
  }

  /**
   * PME-legacy.
   *
   * @param int $compositePaymentId
   *
   * @param string $userIdSlug
   *
   * @param null|string $extension
   *
   * @return string
   */
  protected function getLegacyPaymentRecordFileName(
    int $compositePaymentId,
    string $userIdSlug,
    ?string $extension = null,
  ):string {
    // TRANSLATORS: file-name
    $fileName =  $this->getAppL10n()->t('PaymentRecord-%1$s-%2$d', [
      Util::dashesToCamelCase($userIdSlug, true, '_-.'),
      $compositePaymentId,
    ]);
    if (!empty($extension)) {
      $fileName .= '.' . $extension;
    }
    return $fileName;
  }

  /**
   * Generate a file-name for the given composite payment.
   *
   * @param Entities\CompositePayment $compositePayment
   *
   * @param null|string $extension
   *
   * @return string
   */
  protected function getPaymentRecordFileName(
    Entities\CompositePayment $compositePayment,
    ?string $extension = null,
  ):string {
    $userIdSlug = $compositePayment->getMusician()->getUserIdSlug();
    return $this->getLegacyPaymentRecordFileName($compositePayment->getId(), $userIdSlug, $extension);
  }

  /**
   * Get the name of the sub-folder holding hard-copies of debit-mandates
   *
   * @return string Translated name of the debit-mandates folder.
   */
  protected function getDebitMandatesFolderName():string
  {
    // TRANSLATORS: folder-name
    return $this->getAppL10n()->t('DebitMandates');
  }

  /**
   * PME-legacy.
   *
   * @param string $debitMandateReference
   *
   * @param null|string $extension
   *
   * @return string
   */
  protected function getLegacyDebitMandateFileName(string $debitMandateReference, ?string $extension = null):string
  {
    if (!empty($extension)) {
      $debitMandateReference .= '.' . $extension;
    }
    return $debitMandateReference;
  }

  /**
   * Generate a file-name for a hard-copy of the given debit-mandate.
   *
   * @param Entities\SepaDebitMandate $debitMandate
   *
   * @param null|string $extension
   *
   * @return string
   */
  protected function getDebitMandateFileName(Entities\SepaDebitMandate $debitMandate, ?string $extension = null):string
  {
    return $this->getLegacyDebitMandateFileName($debitMandate->getMandateReference(), $extension);
  }

  /**
   * PME-legacy.
   *
   * @param string|Types\EnumTaxType $taxType
   *
   * @param int $assessmentPeriodStart
   *
   * @param int $assessmentPeriodEnd
   *
   * @param null|string $extension
   *
   * @return string
   */
  protected function getLegacyTaxExemptionNoticeFileName(
    string|Types\EnumTaxType $taxType,
    int $assessmentPeriodStart,
    int $assessmentPeriodEnd,
    ?string $extension = null,
  ):string {
    // TRANSLATORS: file-name
    $fileName =  $this->getAppL10n()->t('TaxExemptionNotice-%1$s-%2$04d-%3$04d', [
      Util::dashesToCamelCase($this->getAppL10n()->t((string)$taxType), true, '_-. '),
      $assessmentPeriodStart,
      $assessmentPeriodEnd,
    ]);
    if (!empty($extension)) {
      $fileName .= '.' . $extension;
    }
    return $fileName;
  }

  /**
   * Generate a file-name for the given tax exemption notice
   *
   * @param Entities\TaxExemptionNotice $taxExemptionNotice
   *
   * @param null|string $extension
   *
   * @return string
   */
  protected function getTaxExemptionNoticeFileName(
    Entities\TaxExemptionNotice $taxExemptionNotice,
    ?string $extension = null,
  ):string {
    return $this->getLegacyTaxExemptionNoticeFileName(
      $taxExemptionNotice->getTaxType(),
      $taxExemptionNotice->getAssessmentPeriodStart(),
      $taxExemptionNotice->getAssessmentPeriodEnd(),
      $extension,
    );
  }
}
