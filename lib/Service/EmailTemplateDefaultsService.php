<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine
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

declare(strict_types=1);

namespace OCA\CAFEVDB\Service;

use OCP\L10N\IFactory as IL10NFactory;

/** Generate localized starter templates for payment-related email flows. */
class EmailTemplateDefaultsService
{
  public const BANK_TRANSFER_ANNOUNCEMENT = 'BankTransferAnnouncement';
  public const DEBIT_NOTE_ANNOUNCEMENT = 'DebitNoteAnnouncement';
  public const DONATION_RECEIPT_NOTIFICATION = 'donation-receipt-notification';
  public const INVOICE_NOTIFICATION = 'invoice-notification';

  private const APP_ID = 'cafevdb';

  private const WARNING = 'This is a starting point only, not a ready-to-send email. Please refine it before use.';

  private const PAYMENT_SUBSTITUTIONS = [
    '${MEMBER::BANK_TRANSACTION_AMOUNT}',
    '${MEMBER::BANK_TRANSACTION_PURPOSE}',
    '${MEMBER::BANK_TRANSACTION_PARTS}',
    '${MEMBER::SEPA_MANDATE_REFERENCE}',
    '${MEMBER::SEPA_MANDATE_DATE}',
    '${MEMBER::BANK_ACCOUNT_IBAN}',
    '${MEMBER::BANK_ACCOUNT_BIC}',
    '${MEMBER::BANK_ACCOUNT_BANK}',
    '${MEMBER::BANK_ACCOUNT_OWNER}',
  ];

  private const BULK_TRANSACTION_SUBSTITUTIONS = [
    '${GLOBAL::BANK_TRANSACTION_DUE_DATE}',
    '${GLOBAL::BANK_TRANSACTION_DUE_DAYS}',
    '${GLOBAL::BANK_TRANSACTION_SUBMIT_DATE}',
    '${GLOBAL::BANK_TRANSACTION_SUBMIT_DAYS}',
  ];

  public function __construct(
    private IL10NFactory $l10nFactory,
  ) {
  }

  /**
   * @param null|string $locale The requested locale, or the current default.
   *
   * @return array<string, array{subject: string, contents: string}>
   */
  public function generateDefaultTemplates(?string $locale = null): array
  {
    if ($locale === null) {
      $l = $this->l10nFactory->get(self::APP_ID);
    } else {
      $language = locale_get_primary_language($locale) ?: $locale;
      $l = $this->l10nFactory->get(self::APP_ID, $language, $locale);
    }

    $bulkSubstitutions = array_merge(
      self::PAYMENT_SUBSTITUTIONS,
      self::BULK_TRANSACTION_SUBSTITUTIONS,
    );

    return [
      self::BANK_TRANSFER_ANNOUNCEMENT => $this->template(
        $l->t('Bank transfer announcement template'),
        $l,
        $bulkSubstitutions,
      ),
      self::DEBIT_NOTE_ANNOUNCEMENT => $this->template(
        $l->t('Debit note announcement template'),
        $l,
        $bulkSubstitutions,
      ),
      self::DONATION_RECEIPT_NOTIFICATION => $this->template(
        $l->t('Donation receipt notification template'),
        $l,
        array_merge(self::PAYMENT_SUBSTITUTIONS, [
          '${MEMBER::DONATION_AMOUNT}',
          '${MEMBER::WAIVED_AMOUNT}',
          '${MEMBER::WAIVING_OF_REIMBURSEMENT}',
          '${MEMBER::DONATION_RECEIPT_DATE}',
          '${MEMBER::DONATION_PARTS}',
        ]),
      ),
      self::INVOICE_NOTIFICATION => $this->template(
        $l->t('Invoice notification template'),
        $l,
        array_merge(self::PAYMENT_SUBSTITUTIONS, [
          '${MEMBER::INVOICE_NUMBER}',
          '${MEMBER::INVOICE_DATE}',
          '${MEMBER::INVOICE_DUE_DATE}',
          '${MEMBER::INVOICE_AMOUNT}',
          '${MEMBER::INVOICE_SUBJECT}',
          '${MEMBER::INVOICE_PURPOSE}',
          '${MEMBER::INVOICE_ITEMS}',
          '${GLOBAL::BANK_TRANSACTION_DUE_DATE}',
          '${GLOBAL::BANK_TRANSACTION_DUE_DAYS}',
        ]),
      ),
    ];
  }

  /**
   * @param string $subject
   * @param \OCP\IL10N $l
   * @param list<string> $substitutions
   *
   * @return array{subject: string, contents: string}
   */
  private function template(string $subject, \OCP\IL10N $l, array $substitutions): array
  {
    $contents = [
      '<p><strong>' . $l->t(self::WARNING) . '</strong></p>',
      '<p>${MEMBER::SALUTATION:FORMAL}</p>',
    ];
    foreach ($substitutions as $substitution) {
      $contents[] = '<p>' . $substitution . '</p>';
    }
    $contents[] = '<p>${GLOBAL::SENDER}</p>';

    return [
      'subject' => $subject,
      'contents' => implode("\n", $contents),
    ];
  }
}
