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

use OCP\IL10N;
use OCP\L10N\IFactory as IL10NFactory;

use OCA\CAFEVDB\EmailForm\EnumGlobalSubstitutionKey as GlobalKey;
use OCA\CAFEVDB\EmailForm\EnumMemberSubstitutionKey as MemberKey;
use OCA\CAFEVDB\EmailForm\EnumSubstitutionNamespace;

/** Generate localized starter templates for payment-related email flows. */
class EmailTemplateDefaultsService
{
  public const BANK_TRANSFER_ANNOUNCEMENT = 'BankTransferAnnouncement';
  public const DEBIT_NOTE_ANNOUNCEMENT = 'DebitNoteAnnouncement';
  public const DONATION_RECEIPT_NOTIFICATION = 'donation-receipt-notification';
  public const INVOICE_NOTIFICATION = 'invoice-notification';

  public const SUBSTITUTIONS_REFERENCE_ENGLISH = 'AllVariables';
  public const SUBSTITUTIONS_REFERENCE_GERMAN = 'AlleVariablen';

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
    $l = $this->getL10N($locale);

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
   * Generate two reference templates listing all available substitutions,
   * one with the English and one with the German variable names.
   *
   * @return array<string, array{subject: string, contents: string}>
   */
  public function generateSubstitutionsReferenceTemplates(): array
  {
    return [
      self::SUBSTITUTIONS_REFERENCE_ENGLISH => $this->substitutionsReferenceTemplate($this->getL10N('en_US')),
      self::SUBSTITUTIONS_REFERENCE_GERMAN => $this->substitutionsReferenceTemplate($this->getL10N('de_DE')),
    ];
  }

  /**
   * @param IL10N $l
   *
   * @return array{subject: string, contents: string}
   */
  private function substitutionsReferenceTemplate(IL10N $l): array
  {
    $contents = [
      '<p><strong>' . $l->t('This template lists all available substitutions. It is meant as a reference, not as a ready-to-send email.') . '</strong></p>',
    ];
    foreach ($this->substitutionGroups($l) as $nameSpaceValue => $groups) {
      $nameSpace = EnumSubstitutionNamespace::from($nameSpaceValue);
      $contents[] = '<h2>' . $l->t('%s substitutions', [ $nameSpace->t($l) ]) . '</h2>';
      foreach ($groups as $heading => $substitutions) {
        $contents[] = '<h3>' . $heading . '</h3>';
        foreach ($substitutions as $substitution) {
          $contents[] = '<p>' . $this->substitution($l, $nameSpace, ...$substitution) . '</p>';
        }
      }
    }

    return [
      'subject' => $l->t('Substitutions reference'),
      'contents' => implode("\n", $contents),
    ];
  }

  /**
   * Generate a single substitution in the given language.
   *
   * @param IL10N $l
   *
   * @param EnumSubstitutionNamespace $nameSpace
   *
   * @param GlobalKey|MemberKey $key
   *
   * @param string|GlobalKey|MemberKey ...$arguments Optional arguments,
   * substitution keys are translated as well.
   *
   * @return string
   */
  private function substitution(
    IL10N $l,
    EnumSubstitutionNamespace $nameSpace,
    GlobalKey|MemberKey $key,
    string|GlobalKey|MemberKey ...$arguments,
  ): string {
    $parts = [ $key->t($l) ];
    foreach ($arguments as $argument) {
      $parts[] = is_string($argument) ? $argument : $argument->t($l);
    }
    return '${' . $nameSpace->t($l) . '::' . implode(':', $parts) . '}';
  }

  /**
   * Group all substitutions by namespace and purpose. Each entry of a group
   * is a list consisting of the substitution key and optional arguments.
   *
   * @param IL10N $l
   *
   * @return array<string, array<string, list<list<string|GlobalKey|MemberKey>>>>
   */
  private function substitutionGroups(IL10N $l): array
  {
    $formal = mb_strtoupper($l->t('formal'));
    $informal = mb_strtoupper($l->t('informal'));
    $phrase = $l->t('phrase');

    return [
      EnumSubstitutionNamespace::GLOBAL->value => [
        $l->t('General') => [
          [ GlobalKey::ORGANIZER ],
          [ GlobalKey::SENDER ],
          [ GlobalKey::PRESIDENT, 'NAME' ],
          [ GlobalKey::TREASURER, 'NAME' ],
          [ GlobalKey::SECRETARY, 'NAME' ],
          [ GlobalKey::ADDRESS ],
          [ GlobalKey::BANK_ACCOUNT ],
          [ GlobalKey::CREDITOR_IDENTIFIER ],
          [ GlobalKey::DATE, 'now', 'long' ],
          [ GlobalKey::TIME, 'now', 'short' ],
          [ GlobalKey::DATETIME, 'now', 'medium' ],
        ],
        $l->t('Projects') => [
          [ GlobalKey::PROJECT ],
          [ GlobalKey::PROJECT_MUSIC_SHEETS_SHARE ],
          [ GlobalKey::PROJECT_MUSIC_SHEETS_SHARE_EXPIRATION ],
          [ GlobalKey::PROJECT_MUSIC_SHEETS_SHARE_EXPIRATION, $phrase ],
          [ GlobalKey::POST_PROJECT_MEDIA_SHARE ],
          [ GlobalKey::POST_PROJECT_MEDIA_FOLDER ],
        ],
        $l->t('Debit notes, bank transfers and invoices') => [
          [ GlobalKey::BANK_TRANSACTION_DUE_DATE ],
          [ GlobalKey::BANK_TRANSACTION_DUE_DAYS ],
        ],
        $l->t('Debit notes and bank transfers') => [
          [ GlobalKey::BANK_TRANSACTION_SUBMIT_DATE ],
          [ GlobalKey::BANK_TRANSACTION_SUBMIT_DAYS ],
        ],
      ],
      EnumSubstitutionNamespace::MEMBER->value => [
        $l->t('General') => [
          [ MemberKey::SALUTATION, $formal ],
          [ MemberKey::SALUTATION, $informal ],
          [ MemberKey::FIRST_NAME ],
          [ MemberKey::SUR_NAME ],
          [ MemberKey::NICK_NAME ],
          [ MemberKey::DISPLAY_NAME ],
          [ MemberKey::GENDER ],
          [ MemberKey::EMAIL ],
          [ MemberKey::MOBILE_PHONE ],
          [ MemberKey::FIXED_LINE_PHONE ],
          [ MemberKey::STREET ],
          [ MemberKey::STREET_NUMBER ],
          [ MemberKey::STREET_AND_NUMBER ],
          [ MemberKey::POSTAL_CODE ],
          [ MemberKey::CITY ],
          [ MemberKey::COUNTRY ],
          [ MemberKey::LANGUAGE ],
          [ MemberKey::BIRTHDAY ],
          [ MemberKey::DATE, MemberKey::BIRTHDAY, 'long' ],
        ],
        $l->t('Projects') => [
          [ MemberKey::TOTAL_FEES ],
          [ MemberKey::AMOUNT_PAID ],
          [ MemberKey::MISSING_AMOUNT ],
          [ MemberKey::PROJECT_DATA ],
        ],
        $l->t('Debit notes, bank transfers, donation receipts and invoices') => [
          [ MemberKey::BANK_TRANSACTION_AMOUNT ],
          [ MemberKey::BANK_TRANSACTION_PURPOSE ],
          [ MemberKey::BANK_TRANSACTION_PARTS ],
          [ MemberKey::BANK_ACCOUNT_IBAN ],
          [ MemberKey::BANK_ACCOUNT_BIC ],
          [ MemberKey::BANK_ACCOUNT_BANK ],
          [ MemberKey::BANK_ACCOUNT_OWNER ],
        ],
        $l->t('Debit notes') => [
          [ MemberKey::SEPA_MANDATE_REFERENCE ],
          [ MemberKey::SEPA_MANDATE_DATE ],
        ],
        $l->t('Donation receipts') => [
          [ MemberKey::DONATION_AMOUNT ],
          [ MemberKey::WAIVED_AMOUNT ],
          [ MemberKey::WAIVING_OF_REIMBURSEMENT ],
          [ MemberKey::DONATION_RECEIPT_DATE ],
          [ MemberKey::DONATION_PARTS ],
        ],
        $l->t('Invoices') => [
          [ MemberKey::INVOICE_NUMBER ],
          [ MemberKey::INVOICE_DATE ],
          [ MemberKey::INVOICE_DUE_DATE ],
          [ MemberKey::INVOICE_AMOUNT ],
          [ MemberKey::INVOICE_SUBJECT ],
          [ MemberKey::INVOICE_PURPOSE ],
          [ MemberKey::INVOICE_ITEMS ],
        ],
      ],
    ];
  }

  /**
   * @param null|string $locale The requested locale, or the current default.
   *
   * @return IL10N
   */
  private function getL10N(?string $locale): IL10N
  {
    if ($locale === null) {
      return $this->l10nFactory->get(self::APP_ID);
    }
    $language = locale_get_primary_language($locale) ?: $locale;
    return $this->l10nFactory->get(self::APP_ID, $language, $locale);
  }

  /**
   * @param string $subject
   * @param IL10N $l
   * @param list<string> $substitutions
   *
   * @return array{subject: string, contents: string}
   */
  private function template(string $subject, IL10N $l, array $substitutions): array
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
