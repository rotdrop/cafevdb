<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Service;

use OCP\ILogger;
use OCP\IL10N;
use OCP\AppFramework\IAppContainer;

/** Tool-tips management with translations.
 *
 * @todo Perhaps base on \ArrayObject
 */
class ToolTipsService implements \ArrayAccess, \Countable
{
  const SUBKEY_PREFIXES = [ 'pme' ];
  const SUB_KEY_SEP = ':';

  use \OCA\CAFEVDB\Traits\LoggerTrait;

  /** @var IL10N */
  private $l;

  /** @var array */
  private $toolTipsData;

  /** @var bool */
  private $debug = false;

  /** @var string */
  private $lastKey = null;

  /** @var array */
  private $failedKeys = [];

  public function __construct(
    IAppContainer $appContainer
    , IL10N $l
    , ILogger $logger
  ) {
    $this->logger = $logger;
    $this->l = $l;
    $this->toolTipsData = [];

    try {
      $debugMode = $appContainer->get(EncryptionService::class)->getConfigValue('debugmode', 0);
      if ($debugMode & ConfigService::DEBUG_TOOLTIPS) {
        $this->debug = true;
      }

    } catch (\Throwable $t) {
      // forget it
    }
  }

  public function debug($debug = null) {
    if ($debug === true || $debug === false) {
      $this->debug = $debug;
    }
    return $this->debug;
  }

  public function getLastKey()
  {
    return $this->lastKey;
  }

  public function getFailedKeys()
  {
    return $this->failedKeys;
  }

  public function toolTips() {
    $this->makeToolTips();
    return $this->toolTipsData;
  }

  /**
   * Countable method
   * @return int
   */
  public function count(): int {
    $this->makeToolTips();
    return \count($this->toolTipsData);
  }

  /**
   * ArrayAccess methods
   *
   * @param string $offset The key to lookup
   * @return boolean
   */
  public function offsetExists($offset): bool {
    return $this->fetch($offset) !== null;
  }

  /**
   * @see offsetExists
   * @param string $offset
   * @return mixed
   */
  public function offsetGet($offset) {
    return $this->fetch($offset);
  }

  /**
   * @see offsetExists
   * @param string $offset
   * @param mixed $value
   */
  public function offsetSet($offset, $value) {
    throw new \RuntimeException($this->l->t("Unimplemented, tooltips cannot be altered at runtime yet"));
  }

  /**
   * @see offsetExists
   * @param string $offset
   */
  public function offsetUnset($offset) {
    throw new \RuntimeException($this->l->t("Unimplemented, tooltips cannot be altered at runtime yet"));
  }

  private function preProcessKey($key)
  {
    foreach (self::SUBKEY_PREFIXES as $prefix) {
      if (strpos($key, $prefix . '-') === 0) {
        return $prefix.self::SUB_KEY_SEP.substr($key, strlen($prefix)+1);
      }
    }
    return $key;
  }

  /**
   * Return a translated tool-tip for the given key.
   *
   * @param string $key
   */
  public function fetch($key, bool $escape = true)
  {
    $this->lastKey = $key;
    $this->makeToolTips();
    $toolTipsData = $this->toolTipsData;

    $key = $this->preprocessKey($key);

    $keys = explode(self::SUB_KEY_SEP, $key);
    while (count($keys) > 0) {
      $key = array_shift($keys);
      $toolTipsData = $toolTipsData[$key]??($toolTipsData['default']??null);
    }
    $tip = $toolTipsData['default']??$toolTipsData;

    if (!is_scalar($tip)) {
      $tip = null;
    }

    if (empty($tip)) {
      $this->failedKeys[] = $this->lastKey;
      if ($this->debug) {
        $tip = $this->l->t('Unknown Tooltip for key "%s" requested.', $this->lastKey);
      }
    } else {
      $this->failedKeys = [];
      if ($this->debug) {
        $tip .= ' (' . $this->l->t('ToolTip-Key "%s"', $this->lastKey) . ')';
      }
    }

    $tip = preg_replace('/(^\s*[\n])+/m', '<p class="tooltip-paragraph">', $tip);

    // idea: allow markdown?

    return empty($tip) ? null : ($escape ? htmlspecialchars($tip) : $tip);
  }

  private function makeToolTips()
  {
    if (!empty($this->toolTipsData)) {
      return;
    }
    $this->toolTipsData = [
      'autocomplete' => [
        'default' => $this->l->t('Type some text to get autocomplete suggestions.'),

        'require-three' => $this->l->t('Type at least three characters to get autocomplete suggestions.'),
      ],
      'blog-acceptentry' => $this->l->t('Save the changes for this blog-entry.'),

      'blog-cancelentry' => $this->l->t('Discard the changes for this blog-entry.'),

      'blog-newentry' => $this->l->t('Write a new bulletin entry.'),

      'blog-popup-clear' => $this->l->t('Disable the pop-up function for this blog-note. The list of associated readers is maintained, so reenabling the pop-up function will still not present this note to users already on the reader-list.'),

      'blog-popup-set' => $this->l->t('Place this note in a pop-up window after login. The window will only pop-up once, the list of readers is remembered.'),

      'blog-priority' => $this->l->t('Change the display-priority. Entries with higher priority are
displayed closer to the top of the page.'),

      'blog-reader-clear' => $this->l->t('Clear the list of readers of this note. Consequently, if this note is marked as popup, then it will pop-up again after clearing the list of readers.'),

      'blogentry-delete' => $this->l->t('Delete the message and the message-thread depending on this message.'),

      'blogentry-edit' => $this->l->t('Edit the bulletin entry; everyone is allowed to do so.'),

      'blogentry-lower' => $this->l->t('Decrease the display priority; move the note closer to the bottom of the page.'),

      'blogentry-raise' => $this->l->t('Increase the display priority; move the note closer to the top of the page.'),

      'blogentry-reply' => $this->l->t('Write a follow-up to the bulletin entry.'),

      'club-member-project' => $this->l->t('Name of the pseudo-project listing the permanent members of the orchestra.'),

      'configrecheck' => $this->l->t('Perform the configuration checks again. If all checks have been passed then you are led on to the ordinary entry page of the application.'),

      'bulk-transaction-creation-time' => $this->l->t('The time when the bulk-transactionx data was created.'),

      'bulk-transaction-date-of-submission' => $this->l->t('The date when the debit note records were actually transferred to the
bank.'),

      'bulk-transaction-due-date' => $this->l->t('The date when (hopefully) the amount debited will reach our own bank
account.'),

      'debit-note-email-message-id' => $this->l->t('Email message-id header of the notification email for this debit-note.'),

      'sepa-bulk-transactions-choice' => $this->l->t('Select the receivables to generate bulk-transactions for. On submit the requested transactions are stored in the data-base and appropriate
export files are generated which are suitable for use with a local
banking application. The banking appliation has then to be fed with the export
sets on your local computer in order to actually transfer the data to the bank.
At the time of this writing the only supported banking
application is AQBanking.'),

      'bulk-transactions-regenerate-receivables' => $this->l->t('Recompute the amounts for all automatically computed receivables.'),

      'sepa-due-deadline' => $this->l->t('Select the due-date for the generated bulk-transactions. If left empty, then the earliest possible due-date is chosen, based on the legal regulations for bank-payments and the negotiated debit-mandates. Depending on the banking-software used to actually submit the bulk-transactions to the bank (currently AqBanking) the due-date is ignored for bank-transfers.'),

      'bulk-transaction-submission-deadline' => $this->l->t('Date of latest submission of the debit note to our own bank.'),

      'bulk-transaction-announce' => $this->l->t('Inform all debitors of this debit-note by email; an email dialog is
opened.'),

      'bulk-transaction-download' => $this->l->t('Download the data-set of this bulk-transaction for transferal to our bank
institute.'),

      'debug-mode' => $this->l->t('Amount of debug output. Keep this disabled for normal use. Debug output can be found in the log-file.'),

      'direct-change' => $this->l->t('If enabled, clicking on a data-row in a table view opens the "change
dialog" for the respective record. If disabled, clicking on a data-row will open the "view dialog".'),

      'email-account-distribute' => $this->l->t('Distribute the email account credentials to all members of the orchestra group. The credentials will be encrypted using an OpenSSL public key owned by the respective user and stored in the pre-user preferences table.'),


      'emailtest' => $this->l->t('Test the email-settings; try to connect to the SMTP-server and the IMAP-server in turn.'),
      'emailform' => [
        'sender' => [
          'name' => $this->l->t('Real name part of the sender address.'),
          'address' => $this->l->t('Email address part of the sender address.'),
        ],
        'transport' => [
          'announcements' => [
            'mailing-list' => $this->l->t('Optional email-address of a mailing list which can optionally be used to send "global" announcements to. If set then global @all emails are rather sent by default to this mailing list than sending it to each individual recipient by Bcc: as the latter may have legal implications unless you have obtained permission to do so from each individual musician. Mailing list transport will not be used when restricting the set of musicians by their instrument or member status, or when individual recipients are selected. It can also optionally be disabled in the email-form\'s address selection tab.'),
          ],
        ],
        'storage' => [
          'messages' => [
            'select' => $this->l->t('Select either a message draft or template as base for the current message.'),
            'new-template' => $this->l->t('Enter a short, no-nonsense name for the new template. The name will be converted to "camel-case", e.g. "hello world" will yield the name "HelloWorld".'),
            'save-as-template' => $this->l->t('Activate this checkbox in order to save the current email message as
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
            'save-message' => $this->l->t('Save the currently active email message either as draft
(i.e. including recipients and attachments) or as message template
(without recipients and attachments). Message drafts will be deleted
after actually sending the message, and after some time of inactivity
(say a month or so), message templates are remembered
permanently. Please check the check-box to the left of this button in
order to store the message as template. Either templates or drafts can
also be "actively" deleted but clicking the delete button to the right
of this button.'),
            'draft-auto-save' => $this->l->t('Automatically save the currently worked-on message as email-draft every 5 minutes.'),
            'delete-saved-message' => $this->l->t('Delete the selected email-template or draft. You will be asked for confirmation before it is actually deleted.'),
          ],
        ],
        'composer' => [
          'subject' => [
            'tag' => $this->l->t('A very short (around 3 characters) tag which is used to construct the subject of a bulk email. More specifically, the subject has the form "[TAG-ProjectNameYYYY] Example Subject" where "TAG" is just the short tag entered here, "ProjectName" is the short project-name, "YYYY" the year of the project, "Example Subject" is just any custom subject string which is supplied through the email editor.'),
          ],
          'recipients' => [
            'listing' => $this->l->t('List of selected musicians; can be changed in the `Em@il-Recipients\' panel.'),
            'freeform-BCC' => $this->l->t('Add arbitrary further hidden recipients.'),
            'freeform-CC' => $this->l->t('Add arbitrary further recipients.'),
            'address-book' => $this->l->t('Opens a select-box with choices from the shared Cloud-addressbook. You can also add new em@il-addresses to the address-book for later reusal. The addresses can also be added in the Cloud `Contacts\'-App.'),
            'disclosed-recipients' => $this->l->t('Unchecking this option discloses the bulk-recipients of this message. Only recipients of project-related emails can be disclosed. Normally this should be left checked, in which case the email is sent with a hidden recipients list.'),
          ],
          'attachments' => [
            'cloud' => $this->l->t('Choose a file to attach from the files stored remotely on in the Cloud storage area.'),
            'events' => $this->l->t('Select calendar attachments from the associated project events.'),
            'personal' => $this->l->t('Choose a file to attach from the project\'s per-musician file-attachments.'),
            'upload' => $this->l->t('Upload a file from your local computer as attachment. The file will be removed from the remote-system after the message has been sent.'),
            'toggle-visibility' => [
              'default' => $this->l->t('Hide or show the select boxes in order to select appropriate attachments.'),
            ],
            'event-select' => $this->l->t('Select-box with all project-events. You can select events as attachments to your email.'),
            'delete-all-events' => $this->l->t('Clear the list of selected event-attachments. Of course, this does not delete the events from their respective calendar, it just de-selects all events such that no event will be attached to the email.'),
            'delete-all-files' => $this->l->t('Deselects all file-attachments. The uploaded attachments are kept on the server until the email-form dialog is closed and can be reselected without uploading them again.'),
            'link' => [
              'size-limit' => $this->l->t('Attachments exceeding this size limit will be replaced by download-links. Set to 0 to convert all attachments to download links. Set to a negative value in order to disable this feature. The size can be specified in bytes or any usual storage unit, e.g. "16.5 MiB".'),
              'expiration-limit' => $this->l->t('Download-links will expire after this time after sending the email. Set to 0 in order to never expire download-links. The time interval may be given in "natural" notation, e.g. "7 days", "1 week". The interval will be rounded to full days.'),
              'cloud-always' => $this->l->t('If checked attachments originating from the cloud storage will always be replaced by a download-link. If unchecked "cloud-files" are just treated like uploaded attachments.'),
            ],
          ],
          'send' => $this->l->t('Attempt to send the stuff you have composed out to your selection of
recipients. Please think thrice about it. In case of an error
additional diagnostic messages may (or may not ...) be available in
the `Debug\' tab'),
          'export' => $this->l->t('Export the email text as HTML. In the case of per-member variable
substitutions this will result in a multi-page document with proper page breaks after each message, with all variables substituted.'),
          'cancel' => $this->l->t('Cancel the email composition and close the input form. This has the
same effect as clicking the close button on top of the dialog-window. No email will be sent.'),
        ],
        'recipients' => [
          'choices' => $this->l->t('Select the recipients for your email!'),
          'filter' => [
            'basic-set' => [
              'disableddefault' => $this->l->t('Choose either among all musicians currently registered for the project
or from the complement set. Obviously, selecting both options will
give you the choice to select any musician as recipient.'),
              'from-project' => $this->l->t('Choose among all musicians currently registered for this project.'),
              'except-project' => $this->l->t('Choose among all musicians currently <b>NOT</b> registered for this project.'),
              'project-mailing-list' => $this->l->t('Send the email to the project-mailing list. The project mailing-list is an open discussion list where all CONFIRMED project members are subscribed (unless they changed it by themselves). Replies to such emails normally end up again in the list and are thus also delivered to all project participants.'),
              'announcements-mailing-list' => $this->l->t('Post to the global announcements mailing list instead of sending to the musicians registered in the data-base. Using the mailing list should be the preferred transport for global @all emails as it has less legal problems concerning the regulations for data privacy. Posting to the list does not make sense if any of the instrument filters is selected or if recipients are explicitly selected.'),
              'database' => $this->l->t('Post to the musicians registered in the database. Unless instrument-filters are active or specific recipients are explicitly selected the global announcement mailing list should be preferred for @all emails.'),
            ],
            'member-status' => $this->l->t('Select recipients by member status. Normally, conductors and soloists
are excluded from receiving mass-email. Please be careful when modifying the default selection!'),
            'apply' => $this->l->t('Apply the currently selected instruments as filter. At your option,
-you can also simply double-click inside the boxed filter-region in order to activate your filter-choice.'),
            'undo' => $this->l->t('Undo the last recipient filter operation and restore the previous selection of musicians.'),
            'redo' => $this->l->t('Redo the last operation undone by the undo button.'),
            'reset' => $this->l->t('Reset to the initial pre-selection which was activ when entering this
form. This will <b>REALLY</b> void all your recipient selections and
reset the form to the initial state. Note, however, that the text of
your email will be maintained, the reset only applies to the recipient
list.'),
            'instruments' => [
              'filter' => $this->l->t('Restrict the basic set of musicians to the instruments selected
here. The filter is additive: selecting more than one instruments will
include the musicians playing either of them.

A double-click inside the filter-box will apply the filter.'),
              'apply' => $this->l->t('Apply the currently selected instruments as filter. At your option,
you can also simply double-click inside the boxed filter-region in order to activate your filter-choice.'),
            ],
          ],
          'broken-emails' => $this->l->t('List of musicians without or with ill-formed email-addresses. You can click on the names in order to open a dialog with the personal data of the respective musician and correct the email addresses there.'),
        ],
      ], // emailform

      'executive-board-project' => $this->l->t('Name of the pseudo-project listing the members of the executive board.'),

      'expert-mode' => $this->l->t('Display additional ``expert\'\' settings. Despite the name you are
invited to have a look, but please do not change anything unless you know what your are doing. Thanks!'),

      'expert-operations' => $this->l->t('For those who know what they are doing, which essentially means: don\'t.'),

      'instrument-insurance' => [
        'not-a-club-member' => $this->l->t('The bill-to-party of an instrument-insurance must be a club-member. This seems not to be the case.'),
        'bill' => $this->l->t('Generate a PDF with detailed records of the insured items and the resulting insurance fee.'),
        'manufacturer' => $this->l->t('Manufacturer and (brief) place of manufacture if know.'),
        'year-of-construction' => $this->l->t('Year of manufacture, if known. "Fuzzy" expression like "unknown", "end of 19th century", "around 1900" etc. are allowed.'),
      ],

      'page-renderer' => [
        'musicians' => [
          'cloud-account-deactivated' => $this->l->t('Expert-setting. "Deactivating the cloud account" means that this musician will show up in the user list of the cloud but will not be able to log-in.'),
          'cloud-account-disabled' => $this->l->t('Expert-setting. "Disabling the cloud account" means that this musician will be hidden from the user-management of the cloud, there will be not corresponding cloud account. Note that marking a musician as deleted will also have the effect to hide the person from the cloud.'),
          'mailing-list' => [
            'default' => $this->l->t('Musicians are normally invited to the announcements mailing list when they are registered with the orchestra app. The announcements mailing list is a receive-only list for announcing projects, concerts and other things "to the public". It may also be used to forward announcements of other orchestras or off-topic notices if this seems appropriate in a case-to-case manner.'),
            'actions' => [
              'invite' => $this->l->t('Invite the musician to join the announcements mailing list. The musician will receive an email with explanations and needs to reply to the invitation. On reply the musician will be subscribed to the list without further action.'),
              'subscribe' => $this->l->t('Per-force subscribe the musician to the announcements mailing list. This may contradict privacy regulations, so use this option with care.'),
              'unsubscribe' => $this->l->t('Unsubscribe the musician from the announcements mailing list.'),
              'accept' => $this->l->t('Accept a pending subscription request of the musician.'),
              'reject' => $this->l->t('Cancel a pending subscription or invitation request.'),
            ],
          ],
        ],
        'participants' => [
          'mailing-list' => [
            'default' => $this->l->t('The project mailing list is an optional discussion mailing list open to the project participants. It is preferred by the orchestra-app when sending notifications to the project-participants but is otherwise optional. It can be used by the project participants to communicate to each other without disclosing their email-address to the other project-members.'),
            'operation' => [
              'subscribe' => $this->l->t('Subscribe the participant to the project mailing list. Normally a participant is automatically subscribed to the project mailing list when its participation status is changed from "preliminary" to "confirmed". It is not possible to subscribe non-confirmed participants. The participant will receive a welcome message when after subscribing it.'),
              'unsubscribe' => $this->l->t('Unsubscribe the participant from the mailing list. Normally a participant is automatically unsubscribed when it is deleted from the project or it participation status is change back to "preliminary" after its participation had been confirmed previously.'),
              'enable-delivery' => $this->l->t('Re-enable delivery of the mailing list traffic to this participant. Normally, list-traffic is disabled for soloist, temporaries and conductors while even these people are still subscribed to the mailing list.'),
              'disable-delivery' => $this->l->t('Disable delivery of the mailing list traffic to this participant. This can as well be done by the participant itself by tuing its membership settings on the configuration pages of the mailing list software.'),
            ],
          ],
        ],
        'participants' => [
          'voice' => $this->l->t('Select the instrument voice. If the desired voice number does not show up in the menu, then select the item with the question mark (e.g. "Violin ?") in order to enter the desired voice with the keyboard.'),
          'section-leader' => [
            'default' => $this->l->t('Check in order to mark the section leader. If this instrument is sub devided into voices the musician first has to be assigned to a voice before it can be marked as section leader for its configured voice.'),
            'view' => $this->l->t('Set to "%s" in order to mark the section leader.', [ "&alpha;" ])
          ],
        ],
      ],

      'participant-fields' => [
      ],

      'participant-attachment-delete' => $this->l->t('Delete this file attachment. Undelete may be possible using the file-app of the cloud-software.'),

      'participant-attachment-upload-replace' => $this->l->t('Upload a new attachment. The old file will be overwritten but possibly may be restored using hte file-app of the cloud-software.'),

      'participant-attachment-upload-rename' => $this->l->t('Upload a new attachment. The old file will be renamed by attaching the current time to its name.'),

      'participant-attachment-upload' => $this->l->t('Click to upload the relevant file or use drag and drop anywhere in this data-row.'),

      'participant-attachment-download' => $this->l->t('Click to download this file.'),

      'participant-attachment-open-parent' => $this->l->t('Open the containing folder using the file-app of the cloud.'),

      'participant-field-multiplicity' => [
        'default' => $this->l->t('Multiplicity of the field, e.g. free-text, single choice, multiple choice etc.'),

        'groupofpeople' => $this->l->t('Group of people, e.g. to define room-mates.'),

        'groupsofpeople' => $this->l->t('Group of people with predefined group-names and a potentially
different maximal number of people fitting in the group. For example to define room-mates.'),

        'multiple' => $this->l->t('Multiple choices, excluding each other.'),

        'parallel' => $this->l->t('Multiple choices where, more than one option can be selected.'),

        'single' => $this->l->t('Simple yes-no choice.'),

        'simple' => $this->l->t('General date field with the respective meaning.'),
      ],

      'participant-field-data-type' => [
        'default' => $this->l->t('Data type of the field, e.g service-fee, text, HTML-text etc.'),
      ],

      'participant-fields-data-options' => [
        'delete-undelete' => $this->l->t('Hit this button to delete or undelete each item. Note that items that
already have been associated with musicians in the data-base can no
longer be "really" deleted. Instead, an attempt to delete them will
just result in marking them as "inactive". Inactive items will be kept
until the end of the world (or this data-base application, whatever
happens to come earlier). Inactive buttons will no longer show up in
the instrumentation table, but inactive items can be "undeleted", just
but clicking this button again.'),

        'default' => $this->l->t('Table with all admissible values for this multiple choice option.'),
        'placeholder' => $this->l->t('In order to add a new option just enter its name here and hit enter or
just click somewhere else. Further attributes can be changed later (data-base key, label, data, context help)'),
        'key' => $this->l->t('Please enter here a unique short non-nonsense key. You will no longer
be able to change this db-key once this option has be attached to a
musician. However, changing the display-label (just the field to the right) is always possible.'),
        'label' => $this->l->t('Just the display-label shown in the select-boxes in the instrumentation table.'),
        'data' => $this->l->t('For surcharge-items this is just the surcharge-amount associated with
the option. For other multi-choice items this is just one arbitrary
string. Please entry the surcharge amount for surcharge items here.'),
        'tooltip' => $this->l->t('An extra-tooltip which can be associated to this specific option. A
help text in order to inform others what this option is about.'),
        'limit' => $this->l->t('The maximum allowed number of people in a "group of people" field'),

        'single' => $this->l->t('For a yes/no option please enter here the single item to select, e.g. the surcharge amount for a service-fee field.'),
        'groupofpeople' => $this->l->t('For a yes/no option please enter here the single item to select, e.g. the surcharge amount for a service-fee field.'),
        'simple' => $this->l->t('Please enter the default value for this free-text option.'),

      ],

      'participant-fields-recurring-data' => [
        'delete-undelete' => $this->l->t('Delete or undelete the receivable for this musician. The data will only be deleted when hitting the "save"-button of the form. Undelete is only possible until the "save"-button has been clicked.'),
        'regenerate' => $this->l->t('Recompute the values of this particular recurring field. The action will be performed immediately.'),
        'regenerate-all' => [
          'default' => $this->l->t('Recompute all receivables for the musician. Note that this will reload the input-form discarding all changes which have not been saved yet.'),
          'everybody' => $this->l->t('Recompute the values of all recurring fields for all participants.'),
          'manually' => $this->l->t('Make sure that at least one new empty input field is available. Note that this will reload the input-form discarding all changes which have not been saved yet.'),
        ],
        'generator' => $this->l->t('Name of a the generator for this field. Can be be a fully-qualified PHP class-name or one of the known short-cuts.'),
        'generator-startdate' => $this->l->t('Starting date for the receivable generation. Maybe overridden by the concrete generator framework.'),
        'generator-run' => $this->l->t('Run the value generator. Depending on the generator this might result in new fields or just does nothing if all relevant fields are already there.'),
      ],

      'participant-fields-default-multi-value' => $this->l->t('Specify a default value for the custom field here. Leave blank if unsure.'),

      'participant-fields-default-single-value' => $this->l->t('Specify a default value for the custom field here. Leave blank if unsure.'),

      'participant-fields-default-cloud-file-value' => $this->l->t('Default policy when replacing files with new uploads. Default is to rename the old file by attaching a time-stamp. "replace" will just overwrite the old  data. Note that independent of this setting the file-app of the cloud may provide undelete operations and versioning of overwritten files.'),

      'participant-fields-default-value' => $this->l->t('Specify a default value for the custom field here. Leave blank if unsure.'),

      'participant-fields-disabled' => $this->l->t('Disable this extra field. This will not erase any data in the
data-base, but simply mark the field as unused and hide it from sight.'),

      'participant-fields-display-order' => $this->l->t('Define the display priority. Larger values will move the item more to
the left or the top inside its table-tab.'),

      'participant-fields-encrypted' => $this->l->t('Expert use: store encrypted values in the data-base. If unsure: <em>DON\'T</em>'),

      'participant-fields-extra-tab' => $this->l->t('Extra-tab to group project-specific data which just didn\'t fit
somewhere else.'),

      'participant-fields-field-name' => $this->l->t('Just the name for this option. Please keep the name as <em>short</em> as
possible, but try to be descriptive. If further explanations are
needed, then please enter those in the <strong>Tooltip</strong> field in the
<strong>Display</strong> tab.'),

      'participant-fields-maximum-group-size' => $this->l->t('The maximum number of peopel allowed in the group.'),

      'participant-fields-new-tab' => $this->l->t('Define a new table-tab. In order to do so, first deselect any
predefined tab in the select box above, then enter the new name. The
new tab-name will also be available as tab-option for other fields.'),

      'participant-fields-readers' => $this->l->t('Members of these Cloud user-groups are allowed to view the
field. If left blank, every logged in user is allowed to view the
field.'),

      'participant-fields-show-data' => $this->l->t('Each option has an optional data-entry attached to it. Normally, this
is only useful for surcharge options, where the "data-entry" just is
the extra-charge amount associated to the option. Still, if you feel a
need to view the data-items also for non-surcharge options, then just
click here.'),

      'participant-fields-show-deleted' => $this->l->t('Options already attached to musicians can no longer be deleted in
order to prevent data-loss in the underlying data-base. It is even
possible to recover those options by checking this checkbox in order to make them visible and
clicking the "recover" button to the left of each deleted entry.'),

      'participant-fields-tab' => $this->l->t('Define the table-tab this entry should be grouped with. It is also
possible to define new table-tabs. In order to do so, first deselect
any possible selected tab, and then enter the name of a new tab in the
input box below.'),

      'participant-fields-tooltip' => $this->l->t('Optionally define a tool-tip (context-help) for the field. The tooltip
may contain HTML formatting.'),

      'participant-fields-writers' => $this->l->t('Members of these Cloud user-groups are allowed to change the
field. If left blank, every logged in user is allowed to change this field.'),

      'file-attachments-select' => $this->l->t('Select-box with all currently uploaded attachments. Note that a file will only be attached to a message if it is also checked in this select box.'),

      'restore-history' => $this->l->t('Try to restore the last visited table view whenreloading the entire page with the web-browser. If unchecked either the musician\'s view or the blog-page is loaded.'),

      'filter-visibility' => $this->l->t('Toggle the initial display of the search-filters for data-base tables
in order to make the table view a little less crowded. Search-filters
can be reenabled at any time by clicking the ``Search\'\' button in
each individual table view.'),

      'further-settings' => $this->l->t('Further personal settings, normally not needed use with care.'),

      'project-instrumentation-numbers' => [
        'required' => $this->l->t('The number of the required musicians per instrument per voice (if the section is split by voices, e.g. "violin 1", "violin 2")'),

        'voice' => $this->l->t('The voice for the respective instrument. Leave at the default to signal that this instrument does not need to be separated into voices. You probably want to distinguish between violin 1 and violin 2, thought ...'),

        'balance' => $this->l->t('The differences between the number of required musicians and the registered / confirmed musicians.'),
      ],

      'instruments-disabled' => $this->l->t('Instruments which are already used by musicians or
projects cannot be deleted; instead "deleting" them flags them as "Disabled".'),

      'member-status' => $this->l->t('A flag which indicates not so much social or functional status, but
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

      'mailing-list' => [
        'domain' => [
          'default' => $this->l->t('Externally visible domains and configuration web-pages.'),
          'config' => $this->l->t('The base-URL of the public web-pages of the used Mailman3 server. The web-pages give access to personal list configuration settings for list-members as well as access to the list configuration pages for administrators.'),
          'email' => $this->l->t('The email-domain of the mailing lists.'),
        ],
        'restapi' => [
          'default' => $this->l->t('REST API account for interaction with a Mailman3 server. Should be located on the same server or proxied via SSL.'),
        ],
        'generated' => [
          'defaults' => [
            'default' => $this->l->t('Some settings for generated per-project mailing lists. The detail configuration can be tuned by visiting the list-configuration pages.'),
            'owner' => $this->l->t('An email address which owns all auto-generated mailing lists. This email will receive notifications by the mailing-list software about necessary administrative tasks.'),
            'moderator' => $this->l->t('An email address which handle moderator-tasks for the mailing lists. List moderation is e.g. necessary for rejecting or accepting posts by non-members or to handle subscription requests.'),
          ]
        ],
      ],

      'musican-contact-tab' => $this->l->t('Display name, pre-name, phone number, email, street-address.'),

      'musician-disabled' => $this->l->t('Musicians which already paid something for the project cannot be
deleted in order not to loose track of the payments. Instead, they are
simply marked as "disabled" and normally are hidden from sight.'),

      'musician-instruments-disabled' => $this->l->t('Instruments which were formerly known to be played by the respective musican but which are disabled for whatever reason. Re-enable by simply adding them again to the list of the musician\'s instruments.'),

      'musician-instrument-insurance' => $this->l->t('Opens a new table view with the insured items for the respective musician.'),

      'musician-miscinfo-tab' => $this->l->t('Further information like birthday, a photo, date of last change.'),

      'musician-orchestra-tab' => $this->l->t('Display name, pre-name, instruments, status, general remarks.'),

      'nothing' => $this->l->t('nothing'),

      'clouddev-link' => $this->l->t('Web-link to the current Cloud developer documentation.'),

      'payment-status' => $this->l->t('Status of outstanding project fees:
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

      'phpmyadmin-link' => $this->l->t('Link to the data-base administration tool for the underlying data-base. Swiss-army-knife-like.'),

      'phpmyadminoc-link' => $this->l->t('Link to the documentation for the database-management tool.'),

      'pme' => [
        'add' => $this->l->t('  Click me to add a new
row to the current table.'),

        'apply' => $this->l->t('Saves the current values; the current input form will remain active.'),

        'bulkcommit' => $this->l->t('  Click me to add all selected musicians
to the selected project. All selected
musicians on all pages will be added.'),

        'bulkcommit+' => $this->l->t('  Click me to pre-select
all musicians on all pages
for the current project.
Please click the ``Add all\'\'-button to
actually add them.'),

        'bulkcommit-' => $this->l->t('  Click me to remove
all musicians on all pages
from the pre-selection for
the current project.'),

        'bulkcommit-check' => $this->l->t('  Check me to pre-select
this musician for the current
project. Please click the
 ``Add all\'\'-button to
actually add all selected
musicians.'),

        'cancel' => array(
        'default' => $this->l->t('Stop the current operation. Settings which already have been stored by
hitting an "Apply" button are maintained, though. You will be returned
to the previous view.'),
        'canceldelete' => $this->l->t('Stop the current operation. You will be returned to the previous view.'),
      ),

        'change' => $this->l->t('Directs you to a form with input fields. From there you can return to
this form by means of the "Save" or "Back" resp. "Cancel" buttons.'),

        'change-navigation' => [
          'operation' => $this->l->t('Einzelnen Datensatz anzeigen,
zeigt ein neues Formular mit
detaillierten Eingabefeldern
und Abbruchmöglichkeit.'),
          ],

        'clear' => [
          'sfn' => $this->l->t('  Klick mich, um die
Sortierreihenfolge auf die
Voreinstellung zurückzusetzen.'),
          'sw' => $this->l->t('  Klick mich, um den
aktuellen Filter zu löschen.'),
        ],

        'copy-navigation' => [
          'operation' => $this->l->t('Einzelnen Datensatz kopieren,
zeigt ein neues Formular mit
detaillierten Eingabefeldern
und Abbruchmöglichkeit.'),
        ],

        'debit-note' => $this->l->t('Click me to export a CSV-table with the selected debit notes suitable for use with AQBanking command-line tool `aqbanking-cli\'. Please refer to the HOWTO in the wiki for further information. Clicking this button will also open the email dialog in order to inform the selected musicians about debiting their bank account.'),

        'debit-note+' => $this->l->t('Select all displayed debit-notes for export.'),

        'debit-note-' => $this->l->t('Deselect all displayed debit-notes from export selection.'),

        'debit-note-check' => $this->l->t('Select this debit note for debiting the project fees. In order to actually export the debit-note you have to hit the `Debit\' button above.'),

        'delete-navigation' => [
          'operation' => $this->l->t('Einzelnen Datensatz löschen,
zeigt den aktuellen Datensatz zunächst an.
Gelöscht wird der erst nach einer
weiteren Bestätigung. Trotzdem:
VORSICHT!.'),
        ],

        'misc-email' => $this->l->t('Klick mich, um eine Em@il an die ausgewählten
Musiker zu versenden. Auf der folgenden Seite kann
die Auswahl dann noch modifiziert werden.
`ausgewält\' bedeutet: nicht
nur die auf der aktuellen
Anzeige-Seite, sondern
alle, die den Such-Kriterien
entsprechen.'),

        'misc+-email' => $this->l->t('Klick mich, um alle gerade
angezeigten Musiker zu der
Em@il-Auswahl hinzuzufügen.
`angezeigt\' bedeutet: nicht
nur die auf der aktuellen
Anzeige-Seite, sondern
alle, die den Such-Kriterien
entsprechen.'),

        'misc--email' => $this->l->t('Klick mich, um alle gerade
angezeigten Musiker von der
Em@il-Auswahl zu entfernen'),

        'misc-check-email' => $this->l->t('Adressaten in potentielle
Massenmail Adressliste aufnehmen.
Die Adressaten kann man
vor dem Senden der Em@il noch
korrigieren.'),

        'misc-debit-note' => $this->l->t('Click to generate bulk-transactions for the selected musicians and receivables.'),

        'misc+-debit-note' => $this->l->t('Click to select all displayed participants for bulk-transaction generation.'),

        'misc--debit-note' => $this->l->t('Click to deselect all displayed participants from the bulk-transaction generation.'),

        'misc-check-debit-note' => $this->l->t('Select and deselect this participant and bank-acccount to and from bulk-transaction generation.'),

        'export-choice' => $this->l->t('Export the visible part of the data-base to an office-format. The `Excel\'-export should produce useful input for either Libre- or OpenOffice or for the product of some well-known software-corporation with seat in Redmond, USA.'),

        'export-csv' => $this->l->t('Export in CSV-format using a semicolon as delimiter (Excel convention)'),

        'export-ods' => $this->l->t('Export in OpenDocument-format (LibreOffice/OpenOffice)'),

        'export-pdf' => $this->l->t('Export as PDF in A3/Landscape, scaled to fit the page size'),

        'export-excel' => $this->l->t('Export as `full-featured\' Excel-2007 table, `.xslx\'.'),

        'export-html' => $this->l->t('Export as HTML page without navigation elements; can also be loaded into your office-programs.'),

        'filter' => $this->l->t('Field for filter/search criteria.
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

        'filter-negate' => $this->l->t('Negate the filter, i.e. search for anything not matching the selected options.'),

        'gotoselect' => $this->l->t('Jumps directly to the given page'),

        'hide' => [
          'sw' => $this->l->t('  Klick mich, um die
Suchkriterien zu verstecken.'),
        ],

        'input' => [
          'lock-empty' => $this->l->t('Click to unlock if the field is empty, click again to clear the field if the field contains data.'),

          'lock-unlock' => $this->l->t('Click to lock and unlock this input field.'),
        ],

        'instrumentation-actions' => $this->l->t('Some usefull convenience actions (click me for details!)'),

        'more' => [
          'moreadd' => $this->l->t('Saves the current values and start to generate another new data-set.'),
          'morecopy' => $this->l->t('Saves the current values and continues to make yet another copy of the source data-set.'),
          'morechange' => $this->l->t('Saves the current values; the current input form will remain active.'),
        ],

        'pagerows' => $this->l->t('Limits the number of rows per page to the given value. A "∞" means to display all records on one large page.'),

        'query' => [
          'default' => $this->l->t('Klick mich, um die
aktuellen Suchkriterien anzuwenden. Suchkriterien
können in den Feldern eingegeben werden.
Als Platzhalter verwendet man `%%\'.'),
        ],

        'reload' => [
          'default' => $this->l->t('Refreshes the current view by reloading all data from the data-base.'),
          'reloadchange' => $this->l->t('Discards all unsaved data and reloads all fields form the data-base. Settings which already have been stored by hitting an
"Apply" button are maintained, though.'),
          'reloadcopy' => $this->l->t('Discards all unsaved data and reloads all fields from the data-base. Settings which already have been stored by hitting an
"Apply" button are maintained, though.'),
        ],

        'save' => [
          'default' => $this->l->t('Saves the current values and returns to the previous view.'),
          'savedelete' => $this->l->t('Deletes the current record and returns to the previous view.'),
        ],

        'search' => [
          'sw' => $this->l->t('  Klick mich, um die
Suchkriterien anzuzeigen.'),
        ],

        'showall-tab' => $this->l->t('Simply blends in all the columns of the table as if all the tabs would be activated at the same time.'),

        'sort' => $this->l->t('Click me to sort by this field! Click again to reverse the search direction. Click another time to disable sorting by this field.'),

        'sort-rvrt' => $this->l->t('Click me to reverse the sort order by this field!'),

        'sort-off' => $this->l->t('Click me to remove this field from the sorting criteria!'),

        'transpose' => $this->l->t('Transpose the displayed table; may be beneficial for tables with only a few rows but many columns!'),

        'view-navigation' => [
          'operation' => $this->l->t('Einzelnen Datensatz anzeigen'),
        ],
      ], // pme:

      'projects' => [
        'participant-fields' => $this->l->t('Define participant-fields for the instrumentation table. E.g.: surcharge
fields for double-/single-room preference, room-mates and such.'),

        'instrumentation-voices' => $this->l->t('Display the desired instrumentaion numbers, i.e. how many musicians are already registered for each instrument group and how many are finally needed.'),

        'type' => $this->l->t('Either "temporary" -- the regular case -- or "permanent" -- the
exceptional case for "virtual pseudo projects". The latter in
particular includes the pseudo-project for the administative board and
the members of the registered orchestra association. Non-permanents
always have per-force the project-year attached to their name,
permanent "pseudo-projects" don\'t, as it does not make any sense.'),

        'mailing-list' => [
          'default' => $this->l->t('The project mailing list is an optional discussion mailing list open to the project participants. It is preferred by the orchestra-app when sending notifications to the project-participants but is otherwise optional. It can be used by the project participants to communicate to each other without disclosing their email-address to the other project-members.'),
          'create' => $this->l->t('Create a mailing-list for the project participants. The list is open for posting from members, participants are auto-subscribed if they are accepted as project-participants, the list archives are accessible to members only.'),
          'manage' => $this->l->t('External link to the list configuration page.'),
          'close' => $this->l->t('Close the list, i.e. disallow further postings to the list.'),
          'reopen' => $this->l->t('Reopen the list, i.e. again allow further postings to the list.'),
          'delete' => $this->l->t('Delete the list and all of its archived messages.'),
          'subscribe' => $this->l->t('Subscribe those participants to the project mailing list which have been finally accepted for participation.'),
        ],
      ],

      'project-actions' => $this->l->t('Pull-down menu with entries to move on
to pages with the instrumentation, events, instrumentation numbers etc.'),

      'project-action' => [
        'debit-mandates' => $this->l->t('Load a new page with all debit-mandates for project-fees'),

        'project-participants' => $this->l->t('Display all registered musicians for the selected project. The table
        shows project related details as well as all stored personal
        "information about the respective musician'),

        'email' => $this->l->t('Opens the email-form for the project inside a dialog window.'),

        'events' => $this->l->t('Open a dialog with all known
events associated to the project.
Events can be added and modified
as needed.'),

        'participant-fields' => $this->l->t('Define participant-fields for the instrumentation table. E.g.: surcharge
fields for double-/single-room preference, room-mates and such.'),

        'files' => $this->l->t('Change to the folder with project related files.'),

        'financial-balance' => $this->l->t('Change to the folder with the financial balance
sheets for the project (only available after the project
has been ``closed\'\'.'),

        'project-instrumentation-numbers' => $this->l->t('Display the desired instrumentaion numbers, i.e. how many musicians are already registered for each instrument group and how many are finally needed.'),

        'wiki' => $this->l->t('Change to the DokuWiki-page for this project (if there is one)'),
      ],

      'project-direct-debit-allowed' => $this->l->t('Some people gave us debit mandates but still want to pay by bank-transfer. Uncheck in order to exclude the person from direct debits transfers.'),

      'project-participant-fee-summary' => $this->l->t('Sum of the fees for all booked etra items.'),

      'project-finance-tab' => $this->l->t('Everything related to project fees, surcharges, bank transfers, debit
mandates.'),

      'project-infopage' => $this->l->t('Opens a dialog-window which gives access to all stored informations for the project.'),

      'project-instrumentation-tab' => $this->l->t('Displays the columns directly related to the instrumentation for the project.'),

      'project-metadata-tab' => $this->l->t('Displays `meta-data\' like project fees, single/double room preferences, debit-mandates and the like.'),

      'project-name' => $this->l->t('Please enter here a <b>SHORT</b> project name, rather a project tag. The software will try very hard to confine you to the following rules:
<dl>
<dt>BE SHORT, PLEASE</dt>
<dd>No more than 20 characters, s.v.p. Please: rather <b>MUCH</b> shorter.</dd>
<dt>NO SPACES, PLEASE</dt>
<dd>Please use "camel case" instead.</dd>
</dl>
Please <b>DO NOT TRY</b> to "work around" those "limitations. Just don\'t. Thanks.'),

      'project-name-yearattach' => $this->l->t('Append the year to the name if checked.
Regardless of this checkbox any decimal digit will first be stripped from the end
of the project name before the year is added.'),

      'project-personaldata-tab' => $this->l->t('Displays the personal data of the respective musicians, like address, email, date of birth if known, phone numbers.'),

      'project-personalmisc-tab' => $this->l->t('Further "not so important" data of the participant.'),

      'project-remarks' => $this->l->t('Project specific remarks for this musician. Please check first if there is a special field for the things you want to note'),

      'project-total-fee-summary' => $this->l->t(
        'The accumulated total of all service fees, reimbursements and salaries the participant has to pay or to receive (TOTALS/PAID/REMAINING).'),

      'project-web-article-add' => $this->l->t('Add a new public web-page to the project by generating a new, empty concert announcement.'),

      'project-web-article-delete' => $this->l->t('Delete the currently displayed web-page fromthe project. The web page will be "detached" from the project and moved to the trash-bin
"category" (folger) inside the CMS.'),

      'project-web-article-linkpage' => $this->l->t('Link existing pages to the project. This can be used, for instance, in order to add a page to the project which has not been created by hitting the `+\'-button above, but was created directly in the CMS backend. When linking articles from the `trashbin\' category then those articles will automatically moved to the `preview\' category; this is some not-so-hidden undelete feature.z'),

      'project-web-article-linkpage-select' => $this->l->t('Please select articles to link to the current project. The articles will be immediately added to the project if you select them. In order to remove the article, please use the `-\' button above.'),

      'project-web-article-unlinkpage' => $this->l->t('Detach the currently displayed event announcement from the project. Primarily meant to provide means to undo erroneous linking of articles.'),

      'projectevents' => [
        'button' => $this->l->t('Open a dialog with all known
events associated to the project.
Events can be added and modified
as needed.'),

        'event' => [
          'edit' => $this->l->t('Modify the event.'),

          'clone' => $this->l->t('Clone (duplicate) the event. A new event-form will popup with just the data from this event.'),

          'delete' => $this->l->t('Delete the event from the system (no undo possible).'),

          'detach' => $this->l->t('Detach the respective event
from the project, but do not
delete it from the calender.
The event can be reattached by
adding the project-name to its
categories.'),

          'select' => $this->l->t('Mark the respective event for being
sent by email as ICS-attachment per email.
Hitting the email button above the form
will open an Email form suitable for
sending the marked events to selected
recipients.'),

        ],

        'all' => [
          'new' => [
            'default' => $this->l->t('Add a new event for the project. The event is added to the respective
calendar and will also be visible and editable through the calendar
app. It is also possible to subscribe to the calendars using a
suitable CalDAV client from your smartphone, tablet or desktop
computer. The link between an "ordinary" event in the web-calendar and
a project is maintained by attching the project name as "category" to
the event.'),

            'concerts' => $this->l->t('Add a new concert-event to the project.'),

            'rehearsals' => $this->l->t('Add a new rehearsal-event to the project.'),

            'other' => $this->l->t('Add a non-categorized event to the project.'),

            'management' => $this->l->t('Add a private management event which is not exposed to the rest of the world.'),

            'finance' => $this->l->t('Add a finance event to the project, e.g. a dead-line for debit-notes or bank transfers.'),
          ],

          'sendmail' => $this->l->t('Click to open an email-form
and send the selected events to
selected recipients.'),

          'select' => $this->l->t('Select all events for email-submission'),

          'deselect' => $this->l->t('Exclude all events from email-submission'),

          'download' => $this->l->t('Download the events as ICS file. In principle it is possible to import
the ICS file into the respective calendar apps of your smartphone,
tablet or desktop computer.'),

          'reload' => $this->l->t('Reload all events from the cloud-calendars.'),
        ],

      ],

      'projectsbalancefolder-force' => $this->l->t('Force the re-creation of the folder where project balanaces are
stored.'),

      'projectsfolder-force' => $this->l->t('Force the re-creation of the folder where project data is stored.'),

      'redaxo-archive' => $this->l->t('Article category of the concert-archive inside the Redaxo CMS.'),

      'redaxo-preview' => $this->l->t('Article category of the concert-previews inside the Redaxo CMS.'),

      'redaxo-rehearsals' => $this->l->t('Article category of the rehearsals inside the Redaxo CMS.'),

      'redaxo-template' => $this->l->t('Article category for templates inside the Redaxo CMS.'),

      'redaxo-trashbin' => $this->l->t('Trashbin pseudo-category, articles deleted from within the
project-views are moved here.'),

      'register-musician' => $this->l->t('Add the musician to the project. A new form will open were details like the instrument etc. can be adjustetd.'),

      'registration-mark' => $this->l->t('This is checked for officially registered musicians, those, who have
sent us a signed registration form. It is left blannk otherwise.'),

      'section-leader-mark' => $this->l->t('This is checked for section-leaders and left blank otherwise.'),

      'sepa-bank-account' => [
        'delete-undelete' => $this->l->t('Delete the given account. If the account has been used for payments then the bank-account will just be marked disabled, but not removed from the data-base.'),
        'info' => $this->l->t('Add a new dialog with detailed information about the bank-account.'),
        'add' => $this->l->t('Add a new dialog for defining a new bank-account.'),
        'show-deleted' => $this->l->t('Show also the disabled bank accounts, if any.'),
      ],

      'sepa-debit-mandate-active' => $this->l->t('Used SEPA mandates are not deleted from the DB, but just flagged as
"inactive" if they expire or are manually pseudo-deleted.'),

      'sepa-instant-validation' => $this->l->t('Toggle instant validation and automatic computation of derived bank account data. If instant validation is disabled, the final values will still be validated and an error message will appear if an error is detected. It is only possible to save of store the debit-mandate if instant validation is enabled.'),

      'sepa-mandate-expired' => $this->l->t('This debit-mandate has not been used for more than %d month and
therefore is expired and cannot be used any longer. Pleae delete it
and contact the treasurer for further instructions.',
                                            array('Finance::SEPA_MANDATE_EXPIRE_MONTHS')
      ),

      'sharedfolder' => $this->l->t('Folder shared by the orchestra group.'),
      'postboxfolder' => $this->l->t('Public upload "postbox" folder. Meant for anonymous public uploads of larger files.'),
      'documenttemplatesfolder' => $this->l->t('Shared folder for document templates.'),
      'projectsfolder' => $this->l->t('Shared folder for per-project data.'),
      'projectparticipantsfolder' => $this->l->t('Shared folder for per-project per-participant data'),
      'projectsbalancefolder' => $this->l->t('Shared folder for the financial balances, probably used after the project is finished.'),

      'settings' => [
        'admin' => [
          'user-group' => $this->l->t('Add the name of a dedicated user-group for the people allowed to access the orchestra-administration app.'),
          'wiki-name-space' => $this->l->t('Add the name of a DokuWiki namespace which will host all wiki-pages of the orchestra. The namespace should be all lower-case and must not contain any spaces or fancy characters.'),
          'cloud-user-backend-conf' => $this->l->t('It is possible to inject cloud-user-accounts for all orchestra club-members into the ambient cloud-software. This works by granting select access to the cloud database account on special views which just expose the necessary information to the cloud. The configuration has to be set up first in the "sharing" section of the personal configuration dialog of a group-admin of the orchestra group.'),
        ],
        'personal' => [
          'general' => [
            'orchestra' => [
              'name' => $this->l->t('Short descriptive name of the orchestra, e.g. use "camerata" instead of "camerata academica freiburg e.V.". The short name is used in various places. It should be lower-case and "handy", without spaces.'),
              'locale' => $this->l->t('The locale of the orchestral organization. In particular, this determines the currency and the name of some directories in the file-system. Note that the timezone is always the same as the one used by the server the app runs on.'),
            ],
          ],
          'sharing' => [
            'user-sql' => [
              'enable' => $this->l->t('Import the orchestra club-members into the ambient cloud as ordinary cloud-users. This requires in particular GRANT privileges for the database. It also requires further configuration of the underlying "%s"-app as admin-user.', 'user_sql'),
              'recreate-views' => $this->l->t('Recreate all "%1$s" views and grant permissions to use them to the cloud dastabase-user.', 'user_sql'),
              'separate-database' => $this->l->t('In order to isolate the SQL-views for the cloud-user-backend from the rest of the database tables it is possible to put them into their own separate database. Please note that it is neccessary that the app\'s database account has all -- and in particular: GRANT -- privileges on that dedicated database.'),
            ],
          ],
        ],
      ],

      'settings-button' => $this->l->t('Personal application settings.'),

      'sharedfolder-force' => $this->l->t('Force the re-creation of the root of the shared-folder hierarchy.'),

      'shareowner-force' => $this->l->t('Re-create the share-owner.'),

      'show-disabled' => $this->l->t('Some data-sets should better not be deleted because they have attached
important - e.g. financial - data attached to it. In this case a
"delete" simply marks the data-set as "disabled". Normally these
data-sets are hidden from the spectator. Checking this option unhides
these data-items.'),

      'show-tool-tips' => $this->l->t('Toggle Tooltips'),

      'sourcecode-link' => $this->l->t('Link to the source-code archives for the DB app.'),

      'sourcedocs-link' => $this->l->t('Link to the source-code documentation for the DB app.'),

      'syncevents' => $this->l->t('Recompute the link between projects and events, using the event-categories as primary key.'),

      'table-rows-per-page' => $this->l->t('The initial number of rows per page when displaying data-base
tables. The actual number of rows per page can also changed later in
the individual table views.'),

      'templates' => [
        'logo' => $this->l->t('An image file with the orchestra logo -- preferably in SVG format. The logo is substituted into document templates using the "[LOGO]" placeholder.'),
        'projectDebitNoteMandateForm' => $this->l->t('A fillable PDF form for debit-mandates bound to special projects. The app is able to auto-fill form-fields with the names "projectName", "bankAccountOwner", "projectParticipant", "bankAccountIBAN", "bankAccountBIC", "bank". The fields in the PDF-form are identified by these names. Auto-filled mandates can be downloaed from the musician and project-participant views.'),

        'generalDebitNoteMandateForm' => $this->l->t('A fillable PDF form for debit-mandates not bound to special projects. The app is able to auto-fill form-fields with the names "bankAccountOwner", "projectParticipant", "bankAccountIBAN", "bankAccountBIC", "bank". The fields in the PDF-form are identified by these names. Auto-filled mandates can be downloaed from the musician and member\'s project view.'),

        'memberDataUpdateForm' => $this->l->t('A fillable form in order to collect address updates and for renewal of debit-mandates.'),

        'instrumentInsuranceRecord' => $this->l->t('An office document with variable substitutions "[PLACEHOLDER]" used to communicate the
yearly insurance fees to the club-members.'),

        'delete' => $this->l->t('Delete this document template from the server'),

        'upload' => [
          'cloud' => [ 'default' => $this->l->t('Choose a template file from the cloud storage.'), ],
          'client' => [ 'default' => $this->l->t('Upload a document template from your computer or mobile.'), ],
        ],

        'auto-fill-test' => [
          'default' => $this->l->t('Test the form-filling features with a test-data-set. The file-format of the generated download remains the same as of the original template.'),
          'pdf' => $this->l->t('Test the form-filling features with a test-data-set and convert the generated file to PDF, providing a fillable PDF-form if the original document contains form-elements.'),
          'data' => $this->l->t('Just download the test-data set as JSON data for inspection and for debugging.'),
        ],
      ],

      'test-cafevdb_dbpassword' => $this->l->t('Test data-base passphrase.'),

      'test-dbpassword' => $this->l->t('Check whether the data-base can be accessed with the given account
information and password. The password will only be stored in the
configuration storage if the test can be performed successfully.'),

      'test-linktarget' => $this->l->t('Try to connect to the specified web-address, will open the web-address in another window or tab, depending your local browser preferences.'),

      'total-fee-summary' => $this->l->t('Total amount the participant has to pay, perhaps followed by total amount paid, followed by the outstanding amount.'),

      'transfer-registered-instruments' => $this->l->t('Add the instruments of the actually registered musicians to the instrument-table for the project.'),

      'wysiwyg-edtior' => $this->l->t('Change to another WYSIWYG editor.'),

    ];

  } // method makeToolTips()
}; // class toolTips

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
