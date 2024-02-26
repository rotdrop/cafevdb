<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020, 2021, 2022, 2023, 2024 Claus-Justus Heine
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

use OCP\AppFramework\IAppContainer;
use OCP\IL10N;

use OCA\CAFEVDB\Service\Finance\FinanceService;

/**
 * Data provider for the ToolTipsService in order to make loading of
 * that class and constructing it more light weight.
 */
class ToolTipsDataService
{
  /**
   * Just a shim which injects the content of the data-array into the
   * to-be-translated strings. The actual translation will happen in the using
   * classes.
   *
   * @param string $text
   *
   * @param mixed $parameters
   *
   * @return string
   *
   * @SuppressWarnings(PHPMD.ShortMethodName)
   */
  private static function t(string $text, mixed $parameters = []):array
  {
    if (!is_array($parameters)
        || (isset($parameter['text'])
            && isset($parameter['parameters']))) {
      $parameters = [ $parameters ];
    }
    foreach ($parameters as &$parameter) {
      if (is_array($parameter)
          && isset($parameter['text'])
          && isset($parameter['parameters'])) {
        $text = $parameter['text'];
        $param = $parameter['parameters'];
        $parameter = fn(IL10N $l) => $l->t($text, $param);
      }
    }
    return [ 'text' => $text, 'parameters' => $parameters ];
  }

  private static $toolTipsData = null;

  /**
   * Return the array of all tooltips.
   *
   * @return array
   */
  public static function get():array
  {
    if (self::$toolTipsData === null) {
      self::$toolTipsData = self::generate();
    }
    return self::$toolTipsData;
  }

  // phpcs:disable Generic.Files.LineLength.TooLong
  /**
   * @return array
   */
  private static function generate():array
  {
    return [
      'mailmerge' => [
        'examples' => [
          'finance' => [
            'invoice' => [
              'subject' => self::t('engagement "c-moll-mess"'),
              'purpose' => self::t('for hiring our orchestra for the concerts on 2022-10-22 (church of our lady)'),
            ],
            'donation' => [
              'expensesSubject' => self::t('example expenses'),
              'incomeSubject' => self::t('example income'),
            ],
            'receipt' => [
              'expensesSubject' => self::t('example expenses'),
              'incomeSubject' => self::t('example income'),
            ],
          ],
        ],
      ],
      'autocomplete' => [
        'default' => self::t('Type some text to get autocomplete suggestions.'),
        'require-three' => self::t('Type at least three characters to get autocomplete suggestions.'),
      ],
      'blog' => [
        'acceptentry' => self::t('Save the changes for this blog-entry.'),

        'cancelentry' => self::t('Discard the changes for this blog-entry.'),

        'newentry' => self::t('Write a new bulletin entry.'),

        'popup-clear' => self::t('Disable the pop-up function for this blog-note. The list of associated readers is maintained, so reenabling the pop-up function will still not present this note to users already on the reader-list.'),

        'popup-set' => self::t('Place this note in a pop-up window after login. The window will only pop-up once, the list of readers is remembered.'),

        'priority' => self::t('Change the display-priority. Entries with higher priority are
displayed closer to the top of the page.'),

        'reader-clear' => self::t('Clear the list of readers of this note. Consequently, if this note is marked as popup, then it will pop-up again after clearing the list of readers.'),

        'entry' => [
          'delete' => self::t('Delete the message and the message-thread depending on this message.'),

          'edit' => self::t('Edit the bulletin entry; everyone is allowed to do so.'),

          'lower' => self::t('Decrease the display priority; move the note closer to the bottom of the page.'),

          'raise' => self::t('Increase the display priority; move the note closer to the top of the page.'),

          'reply' => self::t('Write a follow-up to the bulletin entry.'),
        ],
      ],

      'cloud-file-system-operations' => [
        'copy' => self::t('Copy the source file to the destination.'),
        'move' => self::t('Move the source file to the destination. That is: the source file will be deleted after successful completion of the operation.'),
        'link' => self::t('Link the source file to the destination. This currently only works for files backed by the app\'s database-storage. It can be used to link supporting documents to the project-balance, for example.'),
      ],

      'club-member-project' => self::t('Name of the pseudo-project listing the permanent members of the orchestra.'),

      'configrecheck' => self::t('Perform the configuration checks again. If all checks have been passed then you are led on to the ordinary entry page of the application.'),

      'bulk-transaction-creation-time' => self::t('The time when the bulk-transactionx data was created.'),

      'bulk-transaction-date-of-submission' => self::t('The date when the debit note records were actually transferred to the
bank.'),

      'bulk-transaction-due-date' => self::t('The date when (hopefully) the amount debited will reach our own bank
account.'),

      'debit-note-email-message-id' => self::t('Email message-id header of the notification email for this debit-note.'),

      'sepa-bulk-transactions-choice' => self::t('Select the receivables to generate bulk-transactions for. On submit the requested transactions are stored in the data-base and appropriate
export files are generated which are suitable for use with a local
banking application. The banking appliation has then to be fed with the export
sets on your local computer in order to actually transfer the data to the bank.
At the time of this writing the only supported banking
application is AQBanking.'),

      'bulk-transactions-regenerate-receivables' => self::t('Recompute the amounts for all automatically computed receivables.'),

      'sepa-due-deadline' => self::t('Select the due-date for the generated bulk-transactions. If left empty, then the earliest possible due-date is chosen, based on the legal regulations for bank-payments and the negotiated debit-mandates. Depending on the banking-software used to actually submit the bulk-transactions to the bank (currently AqBanking) the due-date is ignored for bank-transfers.'),

      'bulk-transaction-submission-deadline' => self::t('Date of latest submission of the debit note to our own bank.'),

      'bulk-transaction-announce' => self::t('Inform all debitors of this debit-note by email; an email dialog is
opened.'),

      'bulk-transaction-download' => self::t('Download the data-set of this bulk-transaction for transferal to our bank
institute.'),

      'database-storage' => [
        'finance' => [
          'receipts' => [
            'donations' => self::t(
              '# Donation Receipts

## What\'s This?

This is a virtual folder showing all fabricated donation receipts, grouped by year. The donation receipts are documents of support attached to a project payment which is marked as donation and thus can also be accessed through the [%2$s app](%3$s). Further, the receipts are also linked to the participants document folder below the project the payment refers to.

## Naming Scheme

```
DonationReceipt-PAYMENT_ID-PERSON-PROJECT.EXT
```
where PAYMENT_ID is the numeric database id of the asociated payment, PERSON is the display name of the donator in camel-case, PROJECT is the name of the associated project and the file extension EXT should be `pdf`.',
              [
                fn(IL10N $l) => implode(
                  '", "',
                  array_map(
                    fn(string $value) => $l->t($value),
                    array_values(\OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumTaxType::toArray()),
                  ),
                ),
                fn(IL10N $l, IAppContainer $appContainer) => $appContainer->get('appName'),
                function(IL10N $l, IAppContainer $appContainer) {
                  $appName = $appContainer->get('appName');
                  // GOAL:
                  // https://dev3.home.claus-justus-heine.de/apps/cafevdb/?template=project-payments
                  $template = \OCA\CAFEVDB\PageRenderer\ProjectPayments::TEMPLATE;
                  /** @var \OCP\IURLGenerator  $urlGenerator */
                  $urlGenerator = $appContainer->get(\OCP\IURLGenerator::class);
                  $url = $urlGenerator->linkToRoute($appName . '.page.index', compact('template'));
                  return $url;
                }
              ],
            ),
          ],
          'tax-exemption-notices' => self::t(
            '# Notices of Tax Exemption
## What\'s This?

This is a virtual folder giving direct access to stored copies -- probably scans of the real documents -- of notices of exemption from various tax-types, as issued on request by the respective tax authorities. The main overview table with additional information is accessible through the [%2$s app](%3$s).

## Naming Scheme
```
TaxExemptionNotice-TAX_TYPE-YYYY-YYYY
```

where `TAX_TYPE` is one of "%1$s".

- it is to some extend possible to rename the entries and push back the changes to the overview table of existing notices of exemption

- in order to do so the naming scheme must be strictly respected (no spaces, do not change hyphens to underscores, do not change the casing etc.).

- so if you are well-behaved and polite, then changing the years and the tax-types is allowed *and* those changes will be written back to the [overview table in the database](%2$s).
',
            [
              fn(IL10N $l) => implode(
                '", "',
                array_map(
                  fn(string $value) => $l->t($value),
                  array_values(\OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumTaxType::toArray()),
                ),
              ),
              fn(IL10N $l, IAppContainer $appContainer) => $appContainer->get('appName'),
              function(IL10N $l, IAppContainer $appContainer) {
                $appName = $appContainer->get('appName');
                // GOAL:
                // https://dev3.home.claus-justus-heine.de/apps/cafevdb/?template=tax-exemption-notices
                $template = \OCA\CAFEVDB\PageRenderer\TaxExemptionNotices::TEMPLATE;
                /** @var \OCP\IURLGenerator  $urlGenerator */
                $urlGenerator = $appContainer->get(\OCP\IURLGenerator::class);
                $url = $urlGenerator->linkToRoute($appName . '.page.index', compact('template'));
                return $url;
              }
            ],
          ),
          'transactions' => self::t(
            '# Banktransactions

## What\'s This?

This is a virtual folder giving direct access to banktransaction data files. The files are respectively were used by the treasurer to initiate banktransaction, either bank transfers or debit notes.

The data format is CSV, in order to transfer the data to the bank the command-line tool `aqbanking-cli` from the [AqBanking](https://www.aquamaniac.de/rdm/projects/aqbanking) software-suite has to be used.

Please note that the files listed here are read-only. They are generated by the [%1$s-app](%2$s). Each project has an entry in the side-bar menu which allows to generate bank transactions. The generated data files can also be downloaded from there.

## Naming Scheme
```
YYYYMMDD-HHMMSS-TRANSACTIONTYPE-PROJECTNAME-aqbanking.csv
```
where `TRANSACTIONTYPE` is one of `banktransfer` or `debitnote`.
',
            [
              fn(IL10N $l, IAppContainer $appContainer) => $appContainer->get('appName'),
              function(IL10N $l, IAppContainer $appContainer) {
                $appName = $appContainer->get('appName');
                // GOAL:
                // https://dev3.home.claus-justus-heine.de/apps/cafevdb/?template=tax-exemption-notices
                /** @var \OCP\IURLGenerator  $urlGenerator */
                $urlGenerator = $appContainer->get(\OCP\IURLGenerator::class);
                $url = $urlGenerator->linkToRoute($appName . '.page.index');
                return $url;
              }
            ],
          ),
        ],
      ],

      'debug-mode' => self::t('Amount of debug output. Keep this disabled for normal use. Debug output can be found in the log-file.'),

      'direct-change' => self::t('If enabled, clicking on a data-row in a table view opens the "change
dialog" for the respective record. If disabled, clicking on a data-row will open the "view dialog".'),

      'deselect-invisible-misc-recs' => self::t(
        'If checked the row selection markers (for email, bank-transfers and the like) of invisible records will be unset if the "select all" or "deselect all" button at the top of the table or clicked.'),

      'email-account-distribute' => self::t('Distribute the email account credentials to all members of the orchestra group. The credentials will be encrypted using an OpenSSL public key owned by the respective user and stored in the pre-user preferences table.'),


      'emailtest' => self::t('Test the email-settings; try to connect to the SMTP-server and the IMAP-server in turn.'),
      'emailform' => [
        'sender' => [
          'name' => self::t('Real name part of the sender address.'),
          'address' => self::t('Email address part of the sender address.'),
        ],
        'transport' => [
          'announcements' => [
            'mailing-list' => self::t('Optional email-address of a mailing list which can optionally be used to send "global" announcements to. If set then global @all emails are rather sent by default to this mailing list than sending it to each individual recipient by Bcc: as the latter may have legal implications unless you have obtained permission to do so from each individual musician. Mailing list transport will not be used when restricting the set of musicians by their instrument or member status, or when individual recipients are selected. It can also optionally be disabled in the email-form\'s address selection tab.'),
          ],
        ],
        'storage' => [
          'messages' => [
            'select' => self::t('Select either a message draft or template as base for the current message.'),
            'new-template' => self::t('Enter a short, no-nonsense name for the new template. The name will be converted to "camel-case", e.g. "hello world" will yield the name "HelloWorld".'),
            'save-as-template' => self::t('Activate this checkbox in order to save the current email message as
message template. If you leave this check-box unchecked, then messages
will be saved as draft.

The difference between a draft and a template
is the following: draft messages will be deleted when the message is
actually sent out (and potentially "inactive" drafts will be purged
from the data-base after some time). Templates will never be
purged.

Also, draft messages will be saved with all attachment and --
most important -- including the set of the currently selected
recipients. Message templates, in contrast, are saved with an empty recipient list, as should be.'),
            'save-message' => self::t('Save the currently active email message either as draft
(i.e. including recipients and attachments) or as message template
(without recipients and attachments). Message drafts will be deleted
after actually sending the message, and after some time of inactivity
(say a month or so), message templates are remembered
permanently. Please check the check-box to the left of this button in
order to store the message as template. Either templates or drafts can
also be "actively" deleted but clicking the delete button to the right
of this button.'),
            'draft-auto-save' => self::t('Automatically save the currently worked-on message as email-draft every 5 minutes.'),
            'delete-saved-message' => self::t('Delete the selected email-template or draft. You will be asked for confirmation before it is actually deleted.'),
          ],
        ],
        'composer' => [
          'subject' => [
            'tag' => self::t('A very short (around 3 characters) tag which is used to construct the subject of a bulk email. More specifically, the subject has the form "[TAG-ProjectNameYYYY] Example Subject" where "TAG" is just the short tag entered here, "ProjectName" is the short project-name, "YYYY" the year of the project, "Example Subject" is just any custom subject string which is supplied through the email editor.'),
          ],
          'recipients' => [
            'listing' => self::t('List of selected musicians; can be changed in the `Em@il-Recipients\' panel.'),
            'freeform-BCC' => self::t('Add arbitrary further hidden recipients.'),
            'freeform-CC' => self::t('Add arbitrary further recipients.'),
            'address-book' => self::t('Opens a select-box with choices from the shared Cloud-addressbook. You can also add new em@il-addresses to the address-book for later reusal. The addresses can also be added in the Cloud `Contacts\'-App.'),
            'disclosed-recipients' => self::t('Unchecking this option discloses the bulk-recipients of this message. Only recipients of project-related emails can be disclosed. Normally this should be left checked, in which case the email is sent with a hidden recipients list.'),
          ],
          'attachments' => [
            'cloud' => self::t('Choose a file to attach from the files stored remotely on in the Cloud storage area.'),
            'events' => self::t('Select calendar attachments from the associated project events.'),
            'personal' => self::t('Choose a file to attach from the project\'s per-musician file-attachments.'),
            'upload' => self::t('Upload a file from your local computer as attachment. The file will be removed from the remote-system after the message has been sent.'),
            'toggle-visibility' => [
              'default' => self::t('Hide or show the select boxes in order to select appropriate attachments.'),
            ],
            'event-select' => self::t('Select-box with all project-events. You can select events as attachments to your email.'),
            'delete-all-events' => self::t('Clear the list of selected event-attachments. Of course, this does not delete the events from their respective calendar, it just de-selects all events such that no event will be attached to the email.'),
            'delete-all-files' => self::t('Deselects all file-attachments. The uploaded attachments are kept on the server until the email-form dialog is closed and can be reselected without uploading them again.'),
            'link' => [
              'size-limit' => self::t('Attachments exceeding this size limit will be replaced by download-links. Set to 0 to convert all attachments to download links. Set to a negative value in order to disable this feature. The size can be specified in bytes or any usual storage unit, e.g. "16.5 MiB".'),
              'expiration-limit' => self::t('Download-links will expire after this time after sending the email. Set to 0 in order to never expire download-links. The time interval may be given in "natural" notation, e.g. "7 days", "1 week". The interval will be rounded to full days.'),
              'cloud-always' => self::t('If checked attachments originating from the cloud storage will always be replaced by a download-link. If unchecked "cloud-files" are just treated like uploaded attachments.'),
            ],
          ],
          'send' => self::t('Attempt to send the stuff you have composed out to your selection of
recipients. Please think thrice about it. In case of an error
additional diagnostic messages may (or may not ...) be available in
the `Debug\' tab'),
          'export' => self::t('Export the email text as HTML. In the case of per-member variable
substitutions this will result in a multi-page document with proper page breaks after each message, with all variables substituted.'),
          'cancel' => self::t('Cancel the email composition and close the input form. This has the
same effect as clicking the close button on top of the dialog-window. No email will be sent.'),
        ],
        'recipients' => [
          'choices' => self::t('Select the recipients for your email!'),
          'filter' => [
            'basic-set' => [
              'disableddefault' => self::t('Choose either among all musicians currently registered for the project
or from the complement set. Obviously, selecting both options will
give you the choice to select any musician as recipient.'),
              'from-project' => self::t('Choose among all musicians currently registered for this project.'),
              'except-project' => self::t('Choose among all musicians currently <b>NOT</b> registered for this project.'),
              'project-mailing-list' => self::t('Send the email to the project-mailing list. The project mailing-list is an open discussion list where all CONFIRMED project members are subscribed (unless they changed it by themselves). Replies to such emails normally end up again in the list and are thus also delivered to all project participants.'),
              'announcements-mailing-list' => self::t('Post to the global announcements mailing list instead of sending to the musicians registered in the data-base. Using the mailing list should be the preferred transport for global @all emails as it has less legal problems concerning the regulations for data privacy. Posting to the list does not make sense if any of the instrument filters is selected or if recipients are explicitly selected.'),
              'database' => self::t('Post to the musicians registered in the database. Unless instrument-filters are active or specific recipients are explicitly selected the global announcement mailing list should be preferred for @all emails.'),
            ],

            'member-status' => self::t('Select recipients by member status. Normally, conductors and soloists
are excluded from receiving mass-email. Please be careful when modifying the default selection!'),
            'apply' => self::t('Apply the currently selected instruments as filter. At your option,
-you can also simply double-click inside the boxed filter-region in order to activate your filter-choice.'),
            'undo' => self::t('Undo the last recipient filter operation and restore the previous selection of musicians.'),
            'redo' => self::t('Redo the last operation undone by the undo button.'),
            'reset' => self::t('Reset to the initial pre-selection which was activ when entering this
form. This will <b>REALLY</b> void all your recipient selections and
reset the form to the initial state. Note, however, that the text of
your email will be maintained, the reset only applies to the recipient
list.'),

            'instruments' => [
              'filter' => self::t('Restrict the basic set of musicians to the instruments selected
here. The filter is additive: selecting more than one instruments will
include the musicians playing either of them.

A double-click inside the filter-box will apply the filter.'),
              'apply' => self::t('Apply the currently selected instruments as filter. At your option,
you can also simply double-click inside the boxed filter-region in order to activate your filter-choice.'),
            ],
          ],
          'broken-emails' => self::t('List of musicians without or with ill-formed email-addresses. You can click on the names in order to open a dialog with the personal data of the respective musician and correct the email addresses there.'),
        ],
      ], // emailform

      'executive-board-project' => self::t('Name of the pseudo-project listing the members of the executive board.'),

      'expert-mode' => self::t('Display additional ``expert\'\' settings. Despite the name you are
invited to have a look, but please do not change anything unless you know what your are doing. Thanks!'),

      'expert-operations' => self::t('For those who know what they are doing, which essentially means: don\'t.'),

      'instrument-insurance' => [
        'not-a-club-member' => self::t('The bill-to-party of an instrument-insurance must be a club-member. This seems not to be the case.'),
        'bill' => self::t('Generate a PDF with detailed records of the insured items and the resulting insurance fee.'),
        'manufacturer' => self::t('Manufacturer and (brief) place of manufacture if know.'),
        'year-of-construction' => self::t('Year of manufacture, if known. "Fuzzy" expression like "unknown", "end of 19th century", "around 1900" etc. are allowed.'),
      ],

      'page-renderer' => [
        'pme' => [
          'showall' => [
            'tab' => self::t('Simply blends in all the columns of the table as if all the tabs would be activated at the same time.'),
          ],
        ],

        'attachment' => [
          'default' => self::t('Attach a supporting document.'),
          'delete' => self::t('Delete this file attachment. Undelete may be possible using the files-app of the cloud-software.'),

          'upload' => [
            'default' => self::t('Click to upload the relevant file or use drag and drop anywhere in this data-row.'),
            'from-client' => self::t('Upload a new attachment from your device.'),
            'from-cloud' => self::t('Select a new attachment from the cloud storage. The selected file will be copied.'),
          ],
          'download' => self::t('Click to download this file.'),
          'open-parent' => self::t('Open the containing folder using the files-app of the cloud.'),
        ],
        'miscinfo-tab' => self::t('Further "not so important" data of the participant.'),
        'musicians' => [
          'tab' => [
            'orchestra' => self::t('Display name, pre-name, instruments, status, general remarks.'),

            'contact' => self::t('Display name, pre-name, phone number, email, street-address.'),

            'miscinfo' => self::t('Further information like birthday, a photo, date of last change.'),

            'finance' => self::t('Finance related data like project fees, reimbursements etc.'),
          ],

          'instruments-disabled' => self::t('Instruments which were formerly known to be played by the respective musican but which are disabled for whatever reason. Re-enable by simply adding them again to the list of the musician\'s instruments.'),

          'register' => self::t('Add the musician to the project. A new form will open were details like the instrument etc. can be adjustetd.'),

          'avatar' => self::t('An optional avatar image provided by the person itself. This is part of the public self-expression of the respective user and thus read-only and only provided here for reference. Avatar images are only availabe for persons with an active cloud account.'),

          'cloud-account-deactivated' => self::t('Expert-setting. "Deactivating the cloud account" means that this musician will show up in the user list of the cloud but will not be able to log-in.'),

          'cloud-account-disabled' => self::t('Expert-setting. "Disabling the cloud account" means that this musician will be hidden from the user-management of the cloud, there will be not corresponding cloud account. Note that marking a musician as deleted will also have the effect to hide the person from the cloud.'),

          'mailing-list' => [
            'default' => self::t('Musicians are normally invited to the announcements mailing list when they are registered with the orchestra app. The announcements mailing list is a receive-only list for announcing projects, concerts and other things "to the public". It may also be used to forward announcements of other orchestras or off-topic notices if this seems appropriate in a case-to-case manner.'),
            'actions' => [
              'invite' => self::t('Invite the musician to join the announcements mailing list. The musician will receive an email with explanations and needs to reply to the invitation. On reply the musician will be subscribed to the list without further action.'),
              'subscribe' => self::t('Per-force subscribe the musician to the announcements mailing list. This may contradict privacy regulations, so use this option with care.'),
              'unsubscribe' => self::t('Unsubscribe the musician from the announcements mailing list.'),
              'accept' => self::t('Accept a pending subscription request of the musician.'),
              'reject' => self::t('Cancel a pending subscription or invitation request.'),
            ],
          ],

          'member-status' => self::t('A flag which indicates not so much social or functional status, but
default behaviour for mass-emails as follows
<br/>
<dl>
<dt>regular</dt>
<dd>ordinary member, receives mass-emails</dd>
<dt>passive</dt>
<dd>passive member, does not receive mass-emails unless participating in a project.</dd>
<dt>temporary</dt>
<dd>like passive, but defines another class of musicians during email-address selection</dd>
<dt>conductor</dt>
<dd>does not even receive mass-emails when participating in a project</dd>
<dt>soloist</dt>
<dd>like conductor, but defines yet another class for email-recipient selection</dd>
</dl>
<br/>
All classes of members can be explicitly added to a specific mass-emails through the controls
in the email form.'),

          'address-supplement' => self::t('Additional required address parts like "c/o X Y" or "Apt. N"'),
          'emails' => [
            'principal' => self::t('The principal email address of the musician.'),
            'all' => self::t('A musician must have at least one valid email address in order to contact the person. A person may have more than one email-address in which case the first in the list is used as the principal contact address. You can drag any address with your mouse to the beginning of the list. All email-addresses are subscribed to the configured mailing-lists. However, only the principal email address receives email traffic.'),
          ],
        ],
        'participants' => [
          'mailing-list' => [
            'default' => self::t('The project mailing list is an optional discussion mailing list open to the project participants. It is preferred by the orchestra-app when sending notifications to the project-participants but is otherwise optional. It can be used by the project participants to communicate to each other without disclosing their email-address to the other project-members.'),
            'operation' => [
              'subscribe' => self::t('Subscribe the participant to the project mailing list. Normally a participant is automatically subscribed to the project mailing list when its participation status is changed from "preliminary" to "confirmed". It is not possible to subscribe non-confirmed participants. The participant will receive a welcome message when after subscribing it.'),
              'unsubscribe' => self::t('Unsubscribe the participant from the mailing list. Normally a participant is automatically unsubscribed when it is deleted from the project or it participation status is change back to "preliminary" after its participation had been confirmed previously.'),
              'enable-delivery' => self::t('Re-enable delivery of the mailing list traffic to this participant. Normally, list-traffic is disabled for soloist, temporaries and conductors while even these people are still subscribed to the mailing list.'),
              'disable-delivery' => self::t('Disable delivery of the mailing list traffic to this participant. This can as well be done by the participant itself by tuing its membership settings on the configuration pages of the mailing list software.'),
            ],
          ],
          'voice' => [
            'default' => self::t('Select the instrument voice. If the desired voice number does not show up in the menu, then select the item with the question mark (e.g. "Violin ?") in order to enter the desired voice with the keyboard.'),
            'define-new' => self::t('Opens an input field in order to let you define an arbitrary new voice. The voice is automatically created if it does not yet exist.'),
          ],
          'section-leader' => [
            'default' => self::t('Check in order to mark the section leader. If this instrument is sub devided into voices the musician first has to be assigned to a voice before it can be marked as section leader for its configured voice.'),
            'view' => self::t('Set to "%s" in order to mark the section leader.', [ "&alpha;" ])
          ],
          'registration' => [
            'default' => self::t(
              'Set to "%1$s" for participants whose participation is not sure for whatever reason. Set to "%2$s" for participants whose participation is confirmative. "%1$s" may be the result of e.g. doubts of the executive board, missing confirmation of the participant, too many applications, missing signed registration form (if applicable). When the settings is changed from "%1$s" to "%2$s" the person is also subscribed to the project mailing list and an automatic confirmation email is sent out to the person.

This setting is meant to support a project in the planning phase: one may enter persons freely into the instrumentation table and decide later whether they really participate.',
              [
                self::t('tentatively'),
                self::t('confirmed'),
              ]),
            'tentatively' => self::t(
              '"%1$s" means that it is not clear whether this person really will participate in the project.  Changing the participation status from "%1$s" to "%2$s" will also subscribe the person to the project mailing list and results in an automated project subscription confirmation email.

This setting is here to support the planning phase of a project: one may freely enter persons to the instrumentation table and decide later about their confirmative participation, e.g. because one is waiting for confirmation from the tentative participant, or a signed application, or simply because there are too many applications which need to be cleaned up later.',
              [
                self::t('tentatively'),
                self::t('confirmed'),
              ]),
            'confirmed' => self::t(
              '"%2$s" means that this person definitely will participate in this project. The reason for labeling a person\'s participation status as "%2$s" may vary from project to project, e.g. a signed application may have been received for larger projects which carry a project fee, or maybe the person simply has confirmed its participation after checking its calendar or the like.

This setting is meant to support a project in the planning phase: one may enter persons freely into the instrumentation table, label their participoation as "%1$s" and decide later whether they really participate at which point the person is either deleted or its participation status is set to "%2$s".',
              [
                self::t('tentatively'),
                self::t('confirmed'),
              ],
            ),
          ],
        ],
        'participant-fields' => [
          'tabs' => [
            'access' => self::t('Configure access-restrictions for the field. In particular determine whether this field is visible by the respective participant in the associated members cloud-app.'),
          ],

          'participant-access' => self::t('Define whether this field is visible or even writable by the respective participant in the associated member\'s cloud-app.'),

          'readers' => self::t('Members of these Cloud user-groups are allowed to view the
field. If left blank, every logged in user is allowed to view the
field.'),

          'writers' => self::t('Members of these Cloud user-groups are allowed to change the
field. If left blank, every logged in user is allowed to change this field.'),

          'show-data' => self::t('Each option has an optional data-entry attached to it. Normally, this
is only useful for surcharge options, where the "data-entry" just is
the extra-charge amount associated to the option. Still, if you feel a
need to view the data-items also for non-surcharge options, then just
click here.'),

          'show-deleted' => self::t('Options already attached to musicians can no longer be deleted in
order to prevent data-loss in the underlying data-base. It is even
possible to recover those options by checking this checkbox in order to make them visible and
clicking the "recover" button to the left of each deleted entry.'),

          'definition' => [
            'multiplicity' => [
              'lock' => self::t('Changing the multiplicity of an extra-field after per-participant
data has already been recorded is dangerous and may result in
data-loss. Therefore changing the multiplicity is pro-forma disabled
in this case and the control for changing the multiplicity has to be
explicitly enable by the user.

Please be prepared that changing the multiplicity may still fail. If
you insist on changing the multiplicity then the app will try to cope
with some easy cases and for example transfer multiple choice data to
plain text fields, however, this is an irreversible operation.'),
            ],
            'data-type' => [
              'lock' => self::t('Changing the data-type of an extra-field after per-participant data
has already been recorded is dangerous and may result in
data-loss. Therefore changing the data-type is pro-forma disabled in
this case and the control for changing the multiplicity has to be
explicitly enable by you.

Please be perpared that changing the data-type may still fail. If you
insist on changing the data-type then the app will try to cope with
some easy cases like changing between liabilities and receivables and
treating dates as text. Depending on the already filled data the
results may be unexpected.'),
            ],
          ],

          'display' => [
            'revert-to-default' => self::t('Revert this setting to its default value.'),
            'attachment' => [
              'default' => self::t('Attach a supporting document to this monetary field.'),
              'delete' => self::t('Delete this file attachment. Undelete may be possible using the file-app of the cloud-software.'),

              'upload' => [
                'default' => self::t('Click to upload the relevant file or use drag and drop anywhere in this data-row.'),
                'from-client' => self::t('Upload a new attachment from your device.'),
                'from-cloud' => self::t('Select a new attachment from the cloud storage. The selected file will be copied.'),
              ],

              'download' => self::t('Click to download this file.'),

              'open-parent' => self::t('Open the containing folder using the file-app of the cloud.'),
            ],
            'show-empty-options' => self::t('Show all field options even if they contain no data.'),
            'total-fees' => [
              'summary' => self::t(
                'The accumulated total of all service fees, reimbursements and salaries the participant has to pay or to receive (TOTALS/PAID/REMAINING).'
              ),
              'invoiced' => self::t('The total amount invoiced for this participant. A negative amount means that the participant has to receive money from the orchestra.'),
              'received' =>  self::t('The total amount of money received from or paid to the participant. A negative amount means that the particiant has received money from the orchestra.'),
              'outstanding' => self::t('The total outstanding amount of money. A negative amount means that the orchestra has to pay money to the participant but did not yet do so.'),
            ],
          ],
        ],
        'projects' => [
          'edit-project-instrumentation-numbers' => self::t('Edit the instrumentation numbers in a dialog window. It is possible to specify which instruments are needed and how many, with an optional sub-division into voices.'),
          'edit-project-participant-fields' => self::t('Edit the extra participant fields in a dialog window. The "extra fields" can be used to collect additional data like twin-room preferences, special diets, additional fees and much more!'),
          'public-downloads' => [
            'create' => self::t('Create the download folder and generate a public share link.'),
            'clipboard' => self::t('Copy the share-link to the clipboard'),
            'delete' => self::t('Delete the download share. This will make it impossible to access the data using any previously generated share-link. Note that this of course does not delete the data in the cloud, it just invalidates the public web-link.'),
            'expiration-date' => self::t('Set the expiration date for the link. The default expiration date is the end of the year where the project is performed.'),
          ],
          'registration' => [
            'deadline' => self::t('Optional registration deadline. Thje registration deadline is used by the project registration form in order to filter out projects with expired deadline. If left empty then a deadline one day before the first rehearsal or concert is imposed, if those events are present in the cloud calendar. Otherwise no deadline is imposed.'),
          ],
        ],
        'instrument-insurances' => [
          'instrument-holder' => self::t('The person who actually uses or "has" this instrument or insured object.'),
          'bill-to-party' => self::t('The person who pays the insurance fees. If left blank then the instrument-holder receives the bills.'),
          'instrument-owner' => self::t('The person who has the legal possession of this instrument. If left blank then it is assumed that the instrument holder is also the instrument owner.'),
          'deleted' => self::t('End of insurance, either because the musician has with-drawn the element from the instrument insurances or maybe because the instrument got damaged or otherwise lost. In order to "undelete" this item please enable "expert-mode" in the settings-menu in the side-bar, reload the page and just delete the date.'),
        ],
        'project-payments' => [
          'donation' => self::t('Mark this payment as donation. The (single) supporting document for this
payment should then be the donation receipt. When checking this option an
additional control button will appear which generates a prefabricated donation
receipt. The letter has to be triple checked, signed manully, sent by
snail-mail to the payee and a digital copy has to be uploaded to the cloud as
supporting document.'),
          'project-balance' => [
            'default' => self::t('Link this payment and the supporting documents of its receivable to the project balance folder.'),
            'open' => self::t('Open the project balance folder in the files app of the cloud.'),
          ],
        ],
      ],

      'participant-field-multiplicity' => [
        'default' => self::t('Multiplicity of the field, e.g. free-text, single choice, multiple choice etc.'),

        'groupofpeople' => self::t('Group of people, e.g. to define room-mates.'),

        'groupsofpeople' => self::t('Group of people with predefined group-names and a potentially
different maximal number of people fitting in the group. For example to define room-mates.'),

        'multiple' => self::t('Multiple choices, excluding each other.'),

        'parallel' => self::t('Multiple choices where, more than one option can be selected.'),

        'single' => self::t('Simple yes-no choice.'),

        'simple' => self::t('General date field with the respective meaning.'),
      ],

      'participant-field-data-type' => [
        'default' => self::t('Data type of the field, e.g service-fee, text, HTML-text etc.'),
      ],

      'participant-fields-data-options' => [
        'delete-undelete' => self::t('Hit this button to delete or undelete each item. Note that items that
already have been associated with musicians in the data-base can no
longer be "really" deleted. Instead, an attempt to delete them will
just result in marking them as "inactive". Inactive items will be kept
until the end of the world (or this data-base application, whatever
happens to come earlier). Inactive buttons will no longer show up in
the instrumentation table, but inactive items can be "undeleted", just
but clicking this button again.'),

        'default' => self::t('Table with all admissible values for this multiple choice option.'),
        'placeholder' => self::t('In order to add a new option just enter its name here and hit enter or
just click somewhere else. Further attributes can be changed later (data-base key, label, data, context help)'),
        'key' => self::t('Please enter here a unique short non-nonsense key. You will no longer
be able to change this db-key once this option has be attached to a
musician. However, changing the display-label (just the field to the right) is always possible.'),
        'label' => self::t('Just the display-label shown in the select-boxes in the instrumentation table.'),
        'data' => self::t('For surcharge-items this is just the surcharge-amount associated with
the option. For other multi-choice items this is just one arbitrary
string. Please entry the surcharge amount for surcharge items here.'),
        'tooltip' => self::t('An extra-tooltip which can be associated to this specific option. A
help text in order to inform others what this option is about.'),
        'limit' => self::t('The maximum allowed number of people in a "group of people" field'),

        'single' => self::t('For a yes/no option please enter here the single item to select, e.g. the surcharge amount for a service-fee field.'),
        'groupofpeople' => self::t('For a yes/no option please enter here the single item to select, e.g. the surcharge amount for a service-fee field.'),
        'simple' => self::t('Please enter the default value for this free-text option.'),

      ],

      'participant-fields-recurring-data' => [
        'delete-undelete' => self::t('Delete or undelete the receivable for this musician. The data will only be deleted when hitting the "save"-button of the form. Undelete is only possible until the "save"-button has been clicked.'),
        'regenerate' => self::t('Recompute the values of this particular recurring field. The action will be performed immediately.'),
        'regenerate-all' => [
          'default' => self::t('Recompute all receivables for the musician. Note that this will reload the input-form discarding all changes which have not been saved yet.'),
          'everybody' => self::t('Recompute the values of all recurring fields for all participants.'),
          'manually' => self::t('Make sure that at least one new empty input field is available. Note that this will reload the input-form discarding all changes which have not been saved yet.'),
        ],
        'generator' => self::t('Name of a the generator for this field. Can be be a fully-qualified PHP class-name or one of the known short-cuts.'),
        'generator-startdate' => self::t('Starting date for the receivable generation. Maybe overridden by the concrete generator framework.'),
        'generator-run' => self::t('Run the value generator. Depending on the generator this might result in new fields or just does nothing if all relevant fields are already there.'),
        'update-strategy' => [
          'default' => self::t('Select how to handle conflicts with existing data during recomputation of receivables.'),
          'replace' => self::t('During update of receivables just replace any old value by the newly computed value.'),
          'skip' => self::t('During update of receivables skip the update of existing records and record inconsistencies for later processing.'),
          'exception' => self::t('During update of receivables compare with the newly computed value and throw an exception if the values differ. This is the default.'),
        ],
      ],

      'participant-fields-default-multi-value' => self::t('Specify a default value for the custom field here. Leave blank if unsure.'),

      'participant-fields-default-single-value' => self::t('Specify a default value for the custom field here. Leave blank if unsure.'),

      'participant-fields-default-cloud-file-value' => self::t('Default policy when replacing files with new uploads. Default is to rename the old file by attaching a time-stamp. "replace" will just overwrite the old  data. Note that independent of this setting the file-app of the cloud may provide undelete operations and versioning of overwritten files.'),

      'participant-fields-default-value' => self::t('Specify a default value for the custom field here. Leave blank if unsure.'),

      'participant-fields-disabled' => self::t('Disable this extra field. This will not erase any data in the
data-base, but simply mark the field as unused and hide it from sight.'),

      'participant-fields-display-order' => self::t('Define the display priority. Larger values will move the item more to
the left or the top inside its table-tab.'),

      'participant-fields-encrypted' => self::t('Expert use: store encrypted values in the data-base. If unsure: <em>DON\'T</em>'),

      'participant-fields-extra-tab' => self::t('Extra-tab to group project-specific data which just didn\'t fit
somewhere else.'),

      'participant-fields-field-name' => self::t('Just the name for this option. Please keep the name as <em>short</em> as
possible, but try to be descriptive. If further explanations are
needed, then please enter those in the <strong>Tooltip</strong> field in the
<strong>Display</strong> tab.'),

      'participant-fields-maximum-group-size' => self::t('The maximum number of peopel allowed in the group.'),

      'participant-fields-new-tab' => self::t('Define a new table-tab. In order to do so, first deselect any
predefined tab in the select box above, then enter the new name. The
new tab-name will also be available as tab-option for other fields.'),

      'participant-fields-tab' => self::t('Define the table-tab this entry should be grouped with. It is also
possible to define new table-tabs. In order to do so, first deselect
any possible selected tab, and then enter the name of a new tab in the
input box below.'),

      'participant-fields-tooltip' => self::t('Optionally define a tool-tip (context-help) for the field. The tooltip
may contain HTML formatting.'),

      'file-attachments-select' => self::t('Select-box with all currently uploaded attachments. Note that a file will only be attached to a message if it is also checked in this select box.'),

      'restore-history' => self::t('Try to restore the last visited table view whenreloading the entire page with the web-browser. If unchecked either the musician\'s view or the blog-page is loaded.'),

      'filter-visibility' => self::t('Toggle the initial display of the search-filters for data-base tables
in order to make the table view a little less crowded. Search-filters
can be reenabled at any time by clicking the ``Search\'\' button in
each individual table view.'),

      'further-settings' => self::t('Further personal settings, normally not needed use with care.'),

      'project-instrumentation-numbers' => [
        'required' => self::t('The number of the required musicians per instrument per voice (if the section is split by voices, e.g. "violin 1", "violin 2")'),

        'voice' => self::t('The voice for the respective instrument. Leave at the default to signal that this instrument does not need to be separated into voices. You probably want to distinguish between violin 1 and violin 2, thought ...'),

        'balance' => self::t('The differences between the number of required musicians and the registered / confirmed musicians.'),
      ],

      'instruments-disabled' => self::t('Instruments which are already used by musicians or
projects cannot be deleted; instead "deleting" them flags them as "Disabled".'),

      'mailing-list' => [
        'domain' => [
          'default' => self::t('Externally visible domains and configuration web-pages.'),
          'config' => self::t('The base-URL of the public web-pages of the used Mailman3 server. The web-pages give access to personal list configuration settings for list-members as well as access to the list configuration pages for administrators.'),
          'email' => self::t('The email-domain of the mailing lists.'),
        ],
        'restapi' => [
          'default' => self::t('REST API account for interaction with a Mailman3 server. Should be located on the same server or proxied via SSL.'),
        ],
        'generated' => [
          'defaults' => [
            'default' => self::t('Some settings for generated per-project mailing lists. The detail configuration can be tuned by visiting the list-configuration pages.'),
            'owner' => self::t('An email address which owns all auto-generated mailing lists. This email will receive notifications by the mailing-list software about necessary administrative tasks.'),
            'moderator' => self::t('An email address which handle moderator-tasks for the mailing lists. List moderation is e.g. necessary for rejecting or accepting posts by non-members or to handle subscription requests.'),
          ],
        ],
        'announcements' => [
          'autoconf' => self::t('Attempt to auto-configure the announcements mailing-list as one-way announcements-only list, with the same moderators and owners as specified below for the auto-generated mailing-list. The moderator will also be allowed to post to the list unmoderated.

Further, if any customized auto-response message are found in the confgured templates folder (see "sharing"-tab) then these are used for the announcements mailing-list.

In order to configure the mailing-list the REST_API credentials have to be configured first.'),
        ],
      ],

      'musician-disabled' => self::t('Musicians which already paid something for the project cannot be
deleted in order not to loose track of the payments. Instead, they are
simply marked as "disabled" and normally are hidden from sight.'),

      'musician-instrument-insurance' => self::t('Opens a new table view with the insured items for the respective musician.'),

      'nothing' => self::t('nothing'),

      'clouddev-link' => self::t('Web-link to the current Cloud developer documentation.'),

      'payment-status' => self::t(
        'Status of outstanding project fees:
<dl>
<dt>%s</dt>
<dd>Project-fees are just entirely outstanding yet.</dd>
<dt>%s</dt>
<dd>Awaiting execution of direct debit transfer for deposit.</dd>
<dt>%s</dt>
<dd>Depsosit payment has been received.</dd>
<dt>%s</dt>
<dd>Awaiting execution of direct debit for final payment.</dd>
<dt>%s</dt>
<dd>Final payment has been received.</dd>
</dl>',
        array('&empty;', '&#9972;', '&#9684;', '&#9951;', '&#10004;')
      ),

      'phpmyadmin-link' => self::t('Link to the data-base administration tool for the underlying data-base. Swiss-army-knife-like.'),

      'phpmyadminoc-link' => self::t('Link to the documentation for the database-management tool.'),

      'pme' => [
        'add' => self::t('  Click me to add a new
row to the current table.'),

        'apply' => self::t('Saves the current values; the current input form will remain active.'),

        'bulkcommit' => [
          'default' => self::t('  Click me to add all selected musicians
to the selected project. All selected
musicians on all pages will be added.'),

          'plus' => self::t('  Click me to pre-select
all musicians on all pages
for the current project.
Please click the ``Add all\'\'-button to
actually add them.'),

          'minus' => self::t('  Click me to remove
all musicians on all pages
from the pre-selection for
the current project.'),

          'check' => self::t('  Check me to pre-select
this musician for the current
project. Please click the
 ``Add all\'\'-button to
actually add all selected
musicians.'),
        ],

        'cancel' => [
          'default' => self::t('Stop the current operation. Settings which already have been stored by
hitting an "Apply" button are maintained, though. You will be returned
to the previous view.'),
          'canceldelete' => self::t('Stop the current operation. You will be returned to the previous view.'),
        ],

        'change' => [
          'default' => self::t('Directs you to a form with input fields. From there you can return to
this form by means of the "Save" or "Back" resp. "Cancel" buttons.'),

          'navigation' => [
            'operation' => self::t('Einzelnen Datensatz anzeigen,
zeigt ein neues Formular mit
detaillierten Eingabefeldern
und Abbruchmöglichkeit.'),
          ],
        ],

        'clear' => [
          'sfn' => self::t('  Klick mich, um die
Sortierreihenfolge auf die
Voreinstellung zurückzusetzen.'),
          'sw' => self::t('  Klick mich, um den
aktuellen Filter zu löschen.'),
        ],

        'copy' => [
          'navigation' => [
            'operation' => self::t('Einzelnen Datensatz kopieren,
zeigt ein neues Formular mit
detaillierten Eingabefeldern
und Abbruchmöglichkeit.'),
          ],
        ],

        'debit' => [
          'note' => [
            'default' => self::t('Click me to export a CSV-table with the selected debit notes suitable for use with AQBanking command-line tool `aqbanking-cli\'. Please refer to the HOWTO in the wiki for further information. Clicking this button will also open the email dialog in order to inform the selected musicians about debiting their bank account.'),

            '+' => self::t('Select all displayed debit-notes for export.'),

            '-' => self::t('Deselect all displayed debit-notes from export selection.'),

            'check' => self::t('Select this debit note for debiting the project fees. In order to actually export the debit-note you have to hit the `Debit\' button above.'),
          ],
        ],

        'delete' => [
          'navigation' => [
            'operation' => self::t('Einzelnen Datensatz löschen,
zeigt den aktuellen Datensatz zunächst an.
Gelöscht wird der erst nach einer
weiteren Bestätigung. Trotzdem:
VORSICHT!.'),
          ],
        ],

        'misc' => [
          'email' => self::t('Klick mich, um eine Em@il an die ausgewählten
Musiker zu versenden. Auf der folgenden Seite kann
die Auswahl dann noch modifiziert werden.
`ausgewält\' bedeutet: nicht
nur die auf der aktuellen
Anzeige-Seite, sondern
alle, die den Such-Kriterien
entsprechen.'),
          'debit' => [
            'note' => self::t('Click to generate bulk-transactions for the selected musicians and receivables.'),
          ],
          'plus' => [
            'email' => self::t('Klick mich, um alle gerade
angezeigten Musiker zu der
Em@il-Auswahl hinzuzufügen.
`angezeigt\' bedeutet: nicht
nur die auf der aktuellen
Anzeige-Seite, sondern
alle, die den Such-Kriterien
entsprechen.'),
            'debit' => [
              'note' => self::t('Click to select all displayed participants for bulk-transaction generation.'),
            ],
          ],
          'minus' => [
            'email' => self::t('Klick mich, um alle gerade
angezeigten Musiker von der
Em@il-Auswahl zu entfernen'),
            'debit' => [
              'note' => self::t('Click to deselect all displayed participants from the bulk-transaction generation.'),
            ],
          ],
          'check' => [
            'email' => self::t('Adressaten in potentielle
Massenmail Adressliste aufnehmen.
Die Adressaten kann man
vor dem Senden der Em@il noch
korrigieren.'),
            'debit' => [
              'note' => self::t('Select and deselect this participant and bank-acccount to and from bulk-transaction generation.'),
            ],
          ],
        ],

        'export' => [
          'choice' => self::t('Export the visible part of the data-base to an office-format. The `Excel\'-export should produce useful input for either Libre- or OpenOffice or for the product of some well-known software-corporation with seat in Redmond, USA.'),

          'csv' => self::t('Export in CSV-format using a semicolon as delimiter (Excel convention)'),

          'ods' => self::t('Export in OpenDocument-format (LibreOffice/OpenOffice)'),

          'pdf' => self::t('Export as PDF in A3/Landscape, scaled to fit the page size'),

          'excel' => self::t('Export as `full-featured\' Excel-2007 table, `.xslx\'.'),

          'html' => self::t('Export as HTML page without navigation elements; can also be loaded into your office-programs.'),
        ],

        'filter' => [
          'default' => self::t('Field for filter/search criteria.
Short explanation: simply type somthing and press <code>ENTER</code>.
<br/>
In more detail: For numerical fields there is a select-box with comparison
operators on the left. For text-fields there are `catch-all\' wild-cards
`%%\' and `*\'. Text-fields allow (in particular) for the following
filter possibilities (meaning that <code>SOMETHING</code> is your example
search-string):
<br/><br/>
<dl>
<dt>SOMETHING</dt>
<dd>search for the wild-card expression %%SOMETHING%%</dd>
<dt>"SOMETHING"</dt>
<dd>search for exactly the expression SOMETHING</dd>
<dt>!SOMETHING</dt>
<dd>match everything not being matched by SOMETHING</dd>
</dl>
<br/>
Single quotes are equivalent to double-quotes; instead of `!\' one may
use as well use `!=\', instead of using quotes it is also possible to
prefix the search expression by either `=\' or `==\'.
It is also possible to match empty fields, in particular:
<br/>
<dl>
<dt>"%%"</dt>
<dt>!""</dt>
<dd>match any row with something non-empty in the search-field</dd>
<dt>""</dt>
<dt>!"%%"</dt>
<dd>match any row with empty search-field</dd>
</dl>'),
          'numeric' => self::t('Field for filter/search criteria.
Short explanation: simply type somthing and press <code>ENTER</code>.
This is a numeric field, you can choose from the comparison operators to the left.'),
          'comp' => self::t('Numeric comparison operators. To submit the query double-click into the search-field to the right or click on the "query" button.'),
          'select' => self::t('Select from the pull-down menu. Double-click will submit the form. The pull-down can be closed by clicking anywhere outside the menu.'),
          'negate' => self::t('Negate the filter, i.e. search for anything not matching the selected options.'),
        ],

        'gotoselect' => self::t('Jumps directly to the given page'),

        'hide' => [
          'sw' => self::t('  Klick mich, um die
Suchkriterien zu verstecken.'),
        ],

        'input' => [
          'lock' => [
            'empty' => self::t('Click to unlock if the field is empty, click again to clear the field if the field contains data.'),
            'unlock' => self::t('Click to lock and unlock this input field.'),
          ],
        ],

        'instrumentation' => [
          'actions' => self::t('Some usefull convenience actions (click me for details!)'),
        ],

        'more' => [
          'moreadd' => self::t('Saves the current values and start to generate another new data-set.'),
          'morecopy' => self::t('Saves the current values and continues to make yet another copy of the source data-set.'),
          'morechange' => self::t('Saves the current values; the current input form will remain active.'),
        ],

        'pagerows' => self::t('Limits the number of rows per page to the given value. A "∞" means to display all records on one large page.'),

        'query' => [
          'default' => self::t('Klick mich, um die
aktuellen Suchkriterien anzuwenden. Suchkriterien
können in den Feldern eingegeben werden.
Als Platzhalter verwendet man `%%\'.'),
        ],

        'reload' => [
          'default' => self::t('Refreshes the current view by reloading all data from the data-base.'),
          'reloadchange' => self::t('Discards all unsaved data and reloads all fields form the data-base. Settings which already have been stored by hitting an
"Apply" button are maintained, though.'),
          'reloadcopy' => self::t('Discards all unsaved data and reloads all fields from the data-base. Settings which already have been stored by hitting an
"Apply" button are maintained, though.'),
        ],

        'save' => [
          'default' => self::t('Saves the current values and returns to the previous view.'),
          'savedelete' => self::t('Deletes the current record and returns to the previous view.'),
        ],

        'search' => [
          'sw' => self::t('  Klick mich, um die
Suchkriterien anzuzeigen.'),
        ],

        'showall' => [
          'tab' => self::t('Simply blends in all the columns of the table as if all the tabs would be activated at the same time.'),
        ],

        'sort' => [
          'default' => self::t('Click me to sort by this field! Click again to reverse the search direction. Click another time to disable sorting by this field.'),

          'rvrt' => self::t('Click me to reverse the sort order by this field!'),

          'off' => self::t('Click me to remove this field from the sorting criteria!'),
        ],

        'transpose' => self::t('Transpose the displayed table; may be beneficial for tables with only a few rows but many columns!'),

        'view' => [
          'navigation' => [
            'operation' => self::t('Einzelnen Datensatz anzeigen'),
          ],
        ],
      ], // pme:

      'projects' => [
        'participant-fields' => self::t('Define participant-fields for the instrumentation table. E.g.: surcharge
fields for double-/single-room preference, room-mates and such.'),

        'instrumentation-voices' => self::t('Display the desired instrumentaion numbers, i.e. how many musicians are already registered for each instrument group and how many are finally needed.'),

        'type' => self::t('Either "temporary" -- the regular case -- or "permanent" -- the
exceptional case for "virtual pseudo projects". The latter in
particular includes the pseudo-project for the administative board and
the members of the registered orchestra association. Non-permanents
always have per-force the project-year attached to their name,
permanent "pseudo-projects" don\'t, as it does not make any sense.'),

        'mailing-list' => [
          'default' => self::t('The project mailing list is an optional discussion mailing list open to the project participants. It is preferred by the orchestra-app when sending notifications to the project-participants but is otherwise optional. It can be used by the project participants to communicate to each other without disclosing their email-address to the other project-members.'),
          'dropdown' => self::t('Pull-down menu with interesting mailing-list operations.'),
          'create' => self::t('Create a mailing-list for the project participants. The list is open for posting from members, participants are auto-subscribed if they are accepted as project-participants, the list archives are accessible to members only.'),
          'manage' => self::t('External link to the list configuration page.'),
          'close' => self::t('Close the list, i.e. disallow further postings to the list.'),
          'reopen' => self::t('Reopen the list, i.e. again allow further postings to the list.'),
          'delete' => self::t('Delete the list and all of its archived messages.'),
          'subscribe' => self::t('Subscribe those participants to the project mailing list which have been finally accepted for participation.'),
        ],
      ],

      'project-actions' => self::t('Pull-down menu with entries to move on
to pages with the instrumentation, events, instrumentation numbers etc.'),

      'project-action' => [
        'debit-mandates' => self::t('Load a new page with all debit-mandates for project-fees'),

        'project-participants' => self::t('Display all registered musicians for the selected project. The table
        shows project related details as well as all stored personal
        "information about the respective musician'),

        'email' => self::t('Opens the email-form for the project inside a dialog window.'),

        'events' => self::t('Open a dialog with all known
events associated to the project.
Events can be added and modified
as needed.'),

        'participant-fields' => self::t('Define participant-fields for the instrumentation table. E.g.: surcharge
fields for double-/single-room preference, room-mates and such.'),

        'files' => self::t('Change to the folder with project related files.'),

        'financial-balance' => self::t('Change to the folder with the financial balance
sheets for the project (only available after the project
has been ``closed\'\'.'),

        'project-instrumentation-numbers' => self::t('Display the desired instrumentaion numbers, i.e. how many musicians are already registered for each instrument group and how many are finally needed.'),

        'wiki' => self::t('Change to the DokuWiki-page for this project (if there is one)'),
      ],

      'project-direct-debit-allowed' => self::t('Some people gave us debit mandates but still want to pay by bank-transfer. Uncheck in order to exclude the person from direct debits transfers.'),

      'project-participant-fee-summary' => self::t('Sum of the fees for all booked etra items.'),

      'project-finance-tab' => self::t('Everything related to project fees, surcharges, bank transfers, debit
mandates.'),

      'project-infopage' => self::t('Opens a dialog-window which gives access to all stored informations for the project.'),

      'project-instrumentation-tab' => self::t('Displays the columns directly related to the instrumentation for the project.'),

      'project-metadata-tab' => self::t('Displays `meta-data\' like project fees, single/double room preferences, debit-mandates and the like.'),

      'project-name' => self::t('Please enter here a <b>SHORT</b> project name, rather a project tag. The software will try very hard to confine you to the following rules:
<dl>
<dt>BE SHORT, PLEASE</dt>
<dd>No more than 20 characters, s.v.p. Please: rather <b>MUCH</b> shorter.</dd>
<dt>NO SPACES, PLEASE</dt>
<dd>Please use "camel case" instead.</dd>
</dl>
Please <b>DO NOT TRY</b> to "work around" those "limitations. Just don\'t. Thanks.'),

      'project-name-yearattach' => self::t('Append the year to the name if checked.
Regardless of this checkbox any decimal digit will first be stripped from the end
of the project name before the year is added.'),

      'project-personaldata-tab' => self::t('Displays the personal data of the respective musicians, like address, email, date of birth if known, phone numbers.'),

      'project-remarks' => self::t('Project specific remarks for this musician. Please check first if there is a special field for the things you want to note'),

      'project-web-article-add' => self::t('Add a new public web-page to the project by generating a new, empty concert announcement.'),

      'project-web-article-delete' => self::t('Delete the currently displayed web-page fromthe project. The web page will be "detached" from the project and moved to the trash-bin
"category" (folger) inside the CMS.'),

      'project-web-article-linkpage' => self::t('Link existing pages to the project. This can be used, for instance, in order to add a page to the project which has not been created by hitting the `+\'-button above, but was created directly in the CMS backend. When linking articles from the `trashbin\' category then those articles will automatically moved to the `preview\' category; this is some not-so-hidden undelete feature.z'),

      'project-web-article-linkpage-select' => self::t('Please select articles to link to the current project. The articles will be immediately added to the project if you select them. In order to remove the article, please use the `-\' button above.'),

      'project-web-article-unlinkpage' => self::t('Detach the currently displayed event announcement from the project. Primarily meant to provide means to undo erroneous linking of articles.'),

      'projectevents' => [
        'button' => self::t('Open a dialog with all known
events associated to the project.
Events can be added and modified
as needed.'),

        'event' => [
          'edit' => self::t('Modify the event.'),

          'clone' => self::t('Clone (duplicate) the event. A new event-form will popup with just the data from this event.'),

          'delete' => self::t('Delete the event from the system (no undo possible).'),

          'detach' => self::t('Detach the respective event
from the project, but do not
delete it from the calender.
The event can be reattached by
adding the project-name to its
categories.'),

          'select' => self::t('Mark the respective event for being
sent by email as ICS-attachment.

Hitting the email button at the top of the dialog
will open an Email form suitable for sending the
marked events to selected recipients.

The download button at the top will just download the selected items.'),

          'absence-field' => [
            'check' => self::t('Optionally augment the instrumenation table by an additional columns which can
be used to note down absence from events. The default is to automatically
provide those fields for rehearsals and concerts, however, this checkbox can
be used to generate or remove such fields as needed. The absence-fields are in
principle just ordinary extra-fields where the name of the field is the date
or the event.'),
            'indicator' => self::t('The instrumentation table can be augmented by additional columns which can be
used to note down absence from events. This indiciator shows if this is the
case for this particular event.'),
          ],

          'calendar-app' => [
            'default' => self::t('Open the respective event instance in the calendar app in another browser window or tab.'),
          ],

          'event-series-uid' => self::t('When changing particular events of repeating event series then calendar apps may choose to even split those repeating events into several distinct series. This happens in particular if the user chooses to alter properties for "this and future events". This column is used to visually group such related event series. Events which do not belong to a repeating event will have no label and just show the background color.'),

          'event-uid' => self::t('If this particular event instance belongs to a series of repeating events then all events which belong to this series will show the same letter and color in this column in order to visually group events which belong to the same series. Events which do not belong to a repeating event will have no label and just show the background color.'),


          'scope' => [
            //             'default' => self::t('Select the scope of your following operations, whether it shall act on this
            // single event instance, an event series this event maybe belongs to or a family
            // of related events.'),
            'single' => self::t('Act only on this particular event.'),
            'series' => self::t('Act on the event series this event belongs to.'),
            'related' => self::t('Act on the entire family of related events. When changing particular events of
repeating event series then calendar apps may choose to even split those
repeating events into several distinct series. This happens in particular if
the user chooses to alter properties for "this and future events".'),
          ],
        ],

        'all' => [
          'new' => [
            'default' => self::t('Add a new event for the project. The event is added to the respective
calendar and will also be visible and editable through the calendar
app. It is also possible to subscribe to the calendars using a
suitable CalDAV client from your smartphone, tablet or desktop
computer. The link between an "ordinary" event in the web-calendar and
a project is maintained by attching the project name as "category" to
the event.'),

            'concerts' => self::t('Add a new concert-event to the project.'),

            'rehearsals' => self::t('Add a new rehearsal-event to the project.'),

            'other' => self::t('Add a non-categorized event to the project.'),

            'management' => self::t('Add a private management event which is not exposed to the rest of the world.'),

            'finance' => self::t('Add a finance event to the project, e.g. a dead-line for debit-notes or bank transfers.'),
          ],

          'sendmail' => self::t('Click to open an email-form
and send the selected events to
selected recipients.'),

          'select' => self::t('Select all events for email-submission'),

          'deselect' => self::t('Exclude all events from email-submission'),

          'download' => self::t('Download the events as ICS file. In principle it is possible to import
the ICS file into the respective calendar apps of your smartphone,
tablet or desktop computer.'),

          'reload' => self::t('Reload all events from the cloud-calendars.'),
        ],

      ],

      'balancesfolder-force' => self::t('Force the re-creation of the folder where financial balances are stored.'),

      'projectsfolder-force' => self::t('Force the re-creation of the folder where project data is stored.'),

      'redaxo-archive' => self::t('Article category of the concert-archive inside the Redaxo CMS.'),

      'redaxo-preview' => self::t('Article category of the concert-previews inside the Redaxo CMS.'),

      'redaxo-rehearsals' => self::t('Article category of the rehearsals inside the Redaxo CMS.'),

      'redaxo-template' => self::t('Article category for templates inside the Redaxo CMS.'),

      'redaxo-trashbin' => self::t('Trashbin pseudo-category, articles deleted from within the
project-views are moved here.'),

      'registration-mark' => self::t('This is checked for officially registered musicians, those, who have
sent us a signed registration form. It is left blannk otherwise.'),

      'section-leader-mark' => self::t('This is checked for section-leaders and left blank otherwise.'),

      'sepa-bank-account' => [
        'delete-undelete' => self::t('Delete the given account. If the account has been used for payments then the bank-account will just be marked disabled, but not removed from the data-base.'),
        'info' => self::t('Add a new dialog with detailed information about the bank-account.'),
        'add' => self::t('Add a new dialog for defining a new bank-account.'),
        'show-deleted' => self::t('Show also the disabled bank accounts, if any.'),
      ],

      'sepa-bank-data-form' => [
        'instant-validation' => self::t('Toggle instant validation and automatic computation of derived bank account data. If instant validation is disabled, the final values will still be validated and an error message will appear if an error is detected. It is only possible to save of store the debit-mandate if instant validation is enabled.'),
        'debit-mandate' => [
          'expired' => self::t(
            'This debit-mandate has not been used for more than %d month and
therefore is expired and cannot be used any longer. Pleae delete it
and contact the treasurer for further instructions.',
            FinanceService::SEPA_MANDATE_EXPIRE_MONTHS
          ),
          'only-for-project' => self::t('Use this debit-mandate only for the given project. Note that debit-mandates of club-members are always general debit-mandates for all receivables.'),
          'for-all-receivables' => self::t('Use this as a general debit-mandate for all receivables of this person.'),
          'download' => [
            'default' => self::t('Download the existing signed hard-copy of the debit mandate.'),
            'form' => self::t('Download a prefilled debit-mandate form with the name and bank-account of this person, suitable to be handed to the person in order to be signed. Note that the email-form has also access to such pre-filled debit-mandates which can be attached to personalized mail-merged emails.'),
          ],
          'upload' => [
            'from-client' => self::t('Upload a signed hard-copy of the debit-mandate from the computer.'),
            'from-cloud' => self::t('Choose a signed hard-copy of the debit-mandate from the cloud file-system.'),
            'later' => self::t('For your inconvenience you have to check this box if you want skip the upload-step and store the mandate-data without a signed hard-copy.'),
          ],
        ],
      ],

      'sharedfolder' => self::t('Folder shared by the orchestra group.'),
      'postboxfolder' => self::t('Public upload "postbox" folder. Meant for anonymous public uploads of larger files.'),
      'documenttemplatesfolder' => self::t('Shared folder for document templates.'),
      'projectsfolder' => self::t('Shared folder for per-project data.'),
      'projectparticipantsfolder' => self::t('Shared folder for per-project per-participant data'),
      'projectpostersfolder' => self::t('Shared folder for flyers and posters'),
      'projectpublicdownloadsfolder' => self::t(
        'Link-shared folder for data needed by participants.'
        . ' In particular, this can be used for music-sheet downloads.'
        . ' The folder is automatically created and shared when the project is created.'
        . ' During mail-merge of emails the shared-link is available as ${GLOBAL::PARTICIPANTS_DOWNLOADS_URL}.'
      ),
      'balancesfolder' => self::t('Shared folder for the financial balances, probably used after the project is finished.'),
      'taxOfficeInTrayFolder' => self::t('Subfolder for incoming letters from the tax offices.'),
      'taxExcemptionNoticeTemplate' => self::t('Template file name for tax excemption notices, may and probably should contain
placeholder "{FROM_YEAR}" and "{TO_YEAR}" in order to have the validity period in the file-name.'),

      'settings' => [
        'admin' => [
          'user-group' => [
            'default' => self::t('Add the name of a dedicated user-group for the people allowed to access the orchestra-administration app.'),
            'admins' => self::t('The list of group-admins for the dedicated user-group. You should at least add one group-admin.'),
          ],
          'wiki-name-space' => self::t('Add the name of a DokuWiki namespace which will host all wiki-pages of the orchestra. The namespace should be all lower-case and must not contain any spaces or fancy characters.'),
          'cloud-user-backend-conf' => self::t('It is possible to inject cloud-user-accounts for all orchestra club-members into the ambient cloud-software. This works by granting select access to the cloud database account on special views which just expose the necessary information to the cloud. The configuration has to be set up first in the "sharing" section of the personal configuration dialog of a group-admin of the orchestra group.'),
        ],
        'personal' => [
          'general' => [
            'orchestra' => [
              'name' => self::t('Short descriptive name of the orchestra, e.g. use "camerata" instead of "camerata academica freiburg e.V.". The short name is used in various places. It should be lower-case and "handy", without spaces.'),
              'locale' => self::t('The locale of the orchestral organization. In particular, this determines the currency and the name of some directories in the file-system. Note that the timezone is always the same as the one used by the server the app runs on.'),
            ],
          ],
          'sharing' => [
            'user-sql' => [
              'enable' => self::t('Import the orchestra club-members into the ambient cloud as ordinary cloud-users. This requires in particular GRANT privileges for the database. It also requires further configuration of the underlying "%s"-app as admin-user.', 'user_sql'),
              'recreate-views' => self::t('Recreate all "%1$s" views and grant permissions to use them to the cloud dastabase-user.', 'user_sql'),
              'separate-database' => self::t('In order to isolate the SQL-views for the cloud-user-backend from the rest of the database tables it is possible to put them into their own separate database. Please note that it is neccessary that the app\'s database account has all -- and in particular: GRANT -- privileges on that dedicated database.'),
            ],
          ],
          'encryptionkey' => [
            'default' => self::t('Optionally parts of the data-base and some configuration settings can be stored encrypted. If you are confronted with error messages about a missing encryption key, then you can re-install the encryption key here (if you know it). In order to authorize the change you have also to enter your login-password in the password-field.'),
            'own-password' => self::t('Changing the encryption key needs your login-password. Please enter it here before trying to save the changed encryption key.'),
          ],
        ],
      ],

      'settings-button' => self::t('Personal application settings.'),

      'sharedfolder-force' => self::t('Force the re-creation of the root of the shared-folder hierarchy.'),

      'shareowner-force' => self::t('Re-create the share-owner.'),

      'show-disabled' => self::t('Some data-sets should better not be deleted because they have attached
important - e.g. financial - data attached to it. In this case a
"delete" simply marks the data-set as "disabled". Normally these
data-sets are hidden from the spectator. Checking this option unhides
these data-items.'),

      'show-tool-tips' => self::t('Toggle Tooltips'),

      'sourcecode-link' => self::t('Link to the source-code archives for the DB app.'),

      'sourcedocs-link' => self::t('Link to the source-code documentation for the DB app.'),

      'syncevents' => self::t('Recompute the link between projects and events, using the event-categories as primary key.'),

      'table-rows-per-page' => self::t('The initial number of rows per page when displaying data-base
tables. The actual number of rows per page can also changed later in
the individual table views.'),

      'templates' => [
        'logo' => self::t('An image file with the orchestra logo -- preferably in SVG format. The logo is substituted into document templates using the "[LOGO]" placeholder.'),
        'projectDebitNoteMandateForm' => self::t('A fillable PDF form for debit-mandates bound to special projects. The app is able to auto-fill form-fields with the names "projectName", "bankAccountOwner", "projectParticipant", "bankAccountIBAN", "bankAccountBIC", "bank". The fields in the PDF-form are identified by these names. Auto-filled mandates can be downloaed from the musician and project-participant views.'),

        'generalDebitNoteMandateForm' => self::t('A fillable PDF form for debit-mandates not bound to special projects. The app is able to auto-fill form-fields with the names "bankAccountOwner", "projectParticipant", "bankAccountIBAN", "bankAccountBIC", "bank". The fields in the PDF-form are identified by these names. Auto-filled mandates can be downloaed from the musician and member\'s project view.'),

        'memberDataUpdateForm' => self::t('A fillable form in order to collect address updates and for renewal of debit-mandates.'),

        'instrumentInsuranceRecord' => self::t('An office document with variable substitutions "[PLACEHOLDER]" used to communicate the
yearly insurance fees to the club-members.'),

        'delete' => self::t('Delete this document template from the server'),

        'upload' => [
          'cloud' => [ 'default' => self::t('Choose a template file from the cloud storage.'), ],
          'client' => [ 'default' => self::t('Upload a document template from your computer or mobile.'), ],
        ],

        'auto-fill-test' => [
          'default' => self::t('Test the form-filling features with a test-data-set. The file-format of the generated download remains the same as of the original template.'),
          'pdf' => self::t('Test the form-filling features with a test-data-set and convert the generated file to PDF, providing a fillable PDF-form if the original document contains form-elements.'),
          'data' => self::t('Just download the test-data set as JSON data for inspection and for debugging.'),
        ],
        'cloud' => [
          'integration' => [
            'sender' => self::t('Select the sender from the members of the executive board.'),
            'recipients' => [
              'musicians' => self::t('Select the recipients for the mail-merge.'),
              'contacts' => self::t('Select recipients from the one of the cloud\'s address-books.'),
            ],
            'project' => self::t('Optionally select a project to provide more context information for the mail-merge operation.'),
            'download' => self::t('Preferred for one-time merges or for testing. Just download to your local computer.'),
            'cloudstore' => self::t('Preferred for mass-mail-merges and when the substituted template does not need to be post-processed.'),
            'dataset' => self::t('Download the data-set which would be substituted into the document. This is primarily meant for debuggin.'),
          ],
        ],
      ],

      'test-cafevdb_dbpassword' => self::t('Test data-base passphrase.'),

      'test-dbpassword' => self::t('Check whether the data-base can be accessed with the given account
information and password. The password will only be stored in the
configuration storage if the test can be performed successfully.'),

      'test-linktarget' => self::t('Try to connect to the specified web-address, will open the web-address in another window or tab, depending your local browser preferences.'),

      'total-fee-summary' => self::t('Total amount the participant has to pay, perhaps followed by total amount paid, followed by the outstanding amount.'),

      'transfer-registered-instruments' => self::t('Add the instruments of the actually registered musicians to the instrument-table for the project.'),

      'wysiwyg-edtior' => self::t('Change to another WYSIWYG editor.'),
    ];
  }
}
