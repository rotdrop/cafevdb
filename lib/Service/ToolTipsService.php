<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCP\IL10N;

/** Tool-tips management with translations.
 *
 * @todo Perhaps base on \ArrayObject
 */
class ToolTipsService implements \ArrayAccess, \Countable
{
  /** @var IL10N */
  private $l;

  /** @var array */
  private $toolTipsData;

  /** @var bool */
  private $debug = false;

  public function __construct(IL10N $l) {
    $this->l = $l;
    $this->toolTipsData = [];
  }

  public function debug($debug = null) {
    if ($debug === true || $debug === false) {
      $this->debug = $debug;
    }
    return $this->debug;
  }

  public function toolTips() {
    $this->makeToolTips();
    return $this->$toolTipsData;
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
    throw new \RuntimeException($l->t("Unimplemented, tooltips cannot be altered at runtime yet"));
  }

  /**
   * @see offsetExists
   * @param string $offset
   */
  public function offsetUnset($offset) {
    throw new \RuntimeException($l->t("Unimplemented, tooltips cannot be altered at runtime yet"));
  }

  /**Return a translated tool-tip for the given key.
   */
  private function fetch($key)
  {
    $this->makeToolTips();

    $keys = explode(':', $key);
    if (count($keys) == 2) {
      $key = $keys[0];
      $subKey = $keys[1];
    } else {
      $subKey = null;
    }

    $tip = '';
    if (!empty($subKey)) {
      if (isset($this->toolTipsData[$key][$subKey])) {
        $tip = $this->toolTipsData[$key][$subKey];
      } else if (isset($this->toolTipsData[$key]['default'])) {
        $tip = $this->toolTipsData[$key]['default'];
      } else if (is_scalar($this->toolTipsData[$key])) {
        $tip = $this->toolTipsData[$key];
      }
    } else if (isset($this->toolTipsData[$key])) {
      $tip = $this->toolTipsData[$key];
      !empty($tip['default']) && $tip = $tip['default'];
    }

    if (!is_scalar($tip)) {
      $tip = '';
    }

    if ($this->debug && empty($tip)) {
      if (!empty($subKey)) {
        $tip = $this->l->t('Unknown Tooltip for key "%s-%s" requested.',
                           [$key, $subKey]);
      } else {
        $tip = $this->l->t('Unknown Tooltip for key "%s" requested.',
                           [$key]);
      }
    }

    return empty($tip) ? null : htmlspecialchars($tip);
  }

  private function makeToolTips()
  {
    if (!empty($this->toolTipsData)) {
      return;
    }
    $this->toolTipsData = [
      'address-book-emails' => $this->l->t('Opens a select-box with choices from the shared Cloud-addressbook. You can also add new em@il-addresses to the address-book for later reusal. The addresses can also be added in the Cloud `Contacts\'-App.'),

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

      'blogentry-sticky' => $this->l->t('Toggle the sticky marker; sticky notes are listed at the top of the list.'),

      'cancel-email-composition' => $this->l->t('Cancel the email composition and close the input form. This has the
same effect as clicking the close button on top of the dialog-window. No email will be sent.'),

      'club-member-project' => $this->l->t('Name of the pseudo-project listing the permanent members of the orchestra.'),

      'configrecheck' => $this->l->t('Perform the configuration checks again. If all checks have been passed then you are led on to the ordinary entry page of the application.'),

      'debit-mandate-orchestra-member' => $this->l->t('Please check this box if this musician is a club-member. Otherwise
please leave it unchecked.'),

      'bulk-transaction-creation-time' => $this->l->t('The time when the bulk-transactionx data was created.'),

      'bulk-transaction-date-of-submission' => $this->l->t('The date when the debit note records were actually transferred to the
bank.'),

      'bulk-transacxtion-due-date' => $this->l->t('The date when (hopefully) the amount debited will reach our own bank
account.'),

      'debit-note-email-message-id' => $this->l->t('Email message-id header of the notification email for this debit-note.'),

      'sepa-bulk-transactions-choice' => $this->l->t('Select which kind of bulk-transactions should be generated.
On submit the requested transactions are stored in the data-base and appropriate
export files are generated which are suitable for use with a local
banking application. The banking appliation has then to be fed with the export
sets on your local computer in order to actually transfer the data to the bank.
At the time of this writing the only supported banking
application is AQBanking.'),

      'debit-note-job-option-amount' => $this->l->t('Draw an arbitrary amount from the debitor. However, the amount is
automatically limited not to exceed the outstanding debts of the
musician.'),

      'debit-note-job-option-deposit' => $this->l->t('Just draw an amount up to the deposit for the project. If there are
already payments for the project which sum up to the deposit amount,
then nothing is debited.'),

      'debit-note-job-option-insurance' => $this->l->t('Issue the yearly debit-note for the instrument insurance.'),

      'debit-note-job-option-membership-fee' => $this->l->t('Issue the yearly debit-note for the member-ship fee.'),

      'debit-note-job-option-remaining' => $this->l->t('Issue a debit-note over the remaining debts of the musician, taking
into account how much already has been paid.'),

      'bulk-transaction-submission-deadline' => $this->l->t('Date of latest submission of the debit note to our own bank.'),

      'debit-notes-announce' => $this->l->t('Inform all debitors of this debit-note by email; an email dialog is
opened.'),

      'bulk-transaction-download' => $this->l->t('Download the data-set of this bulk-transaction for transferal to our bank
institute.'),

      'debug-mode' => $this->l->t('Amount of debug output. Keep this disabled for normal use. Debug output can be found in the log-file.'),

      'delete-all-event-attachments' => $this->l->t('Clear the list of selected event-attachments. Of course, this does not delete the events from their respective calendar, it just de-selects all events such that no event will be attached to the email.'),

      'delete-all-file-attachments' => $this->l->t('Delete all uploaded file-attachments from the server. This is also done automatically when closing the email-form. This will also empty the select box.'),

      'delete-saved-message' => $this->l->t('Delete the selected email-template or draft. You will be asked for confirmation before it is actually deleted.'),

      'direct-change' => $this->l->t('If enabled, clicking on a data-row in a table view opens the "change
dialog" for the respective record. If disabled, clicking on a data-row will open the "view dialog".'),

      'email-account-distribute' => $this->l->t('Distribute the email account credentials to all members of the orchestra group. The credentials will be encrypted using an OpenSSL public key owned by the respective user and stored in the pre-user preferences table.'),

      'email-message-export' => $this->l->t('Export the email text as HTML. In the case of per-member variable
substitutions this will result in a multi-page document with proper page breaks after each message, with all variables substituted.'),

      'email-recipients-basic-set' => $this->l->t('Choose either among all musicians currently registered for the project
or from the complement set. Obviously, selecting both options will
give you the choice to select any musician as recipient.'),

      'email-recipients-broken-emails' => $this->l->t('List of musicians without or with ill-formed email-addresses. You can click on the names in order to open a dialog with the personal data of the respective musician and correct the email addresses there.'),

      'email-recipients-choices' => $this->l->t('Select the recipients for your email!'),

      'email-recipients-except-project' => $this->l->t('Choose among all musicians currently <b>NOT</b> registered for this project.'),

      'email-recipients-filter-apply' => $this->l->t('Apply the currently selected instruments as filter. At your option,
you can also simply double-click inside the boxed filter-region in order to activate your filter-choice.'),

      'email-recipients-filter-redo' => $this->l->t('Redo the last operation undone by the undo button.'),

      'email-recipients-filter-reset' => $this->l->t('Reset to the initial pre-selection which was activ when entering this
form. This will <b>REALLY</b> void all your recipient selections and
reset the form to the initial state. Note, however, that the text of
your email will be maintained, the reset only applies to the recipient
list.'),

      'email-recipients-filter-undo' => $this->l->t('Undo the last recipient filter operation and restore the previous selection of musicians.'),

      'email-recipients-freeform-BCC' => $this->l->t('Add arbitrary further hidden recipients.'),

      'email-recipients-freeform-CC' => $this->l->t('Add arbitrary further recipients.'),

      'email-recipients-from-project' => $this->l->t('Choose among all musicians currently registered for this project.'),

      'email-recipients-instruments-filter' => $this->l->t('Restrict the basic set of musicians to the instruments selected
here. The filter is additive: selecting more than one instruments will
include the musicians playing either of them.'),

      'email-recipients-instruments-filter-container' => $this->l->t('A double click inside the boxed filter-region will apply the instruments-filter'),

      'email-recipients-instruments-filter-label' => $this->l->t('A double click inside the boxed filter-region will apply the instruments-filter'),

      'email-recipients-listing' => $this->l->t('List of selected musicians; can be changed in the `Em@il-Recipients\' panel.'),

      'email-recipients-member-status-filter' => $this->l->t('Select recipients by member status. Normally, conductors and soloists
are excluded from receiving mass-email. Please be careful when modifying the default selection!'),

      'emailtest' => $this->l->t('Test the email-settings; try to connect to the SMTP-server and the IMAP-server in turn.'),

      'event-attachments-select' => $this->l->t('Select-box with all project-events. You can select events as attachments to your email.'),

      'events-attachment' => $this->l->t('Select calendar attachments from the associated project events.'),

      'executive-board-project' => $this->l->t('Name of the pseudo-project listing the members of the executive board.'),

      'expert-mode' => $this->l->t('Display additional ``expert\'\' settings. Despite the name you are
invited to have a look, but please do not change anything unless you know what your are doing. Thanks!'),

      'expert-operations' => $this->l->t('For those who know what they are doing, which essentially means: don\'t.'),

      'participant-attachment-delete' => $this->l->t('Delete this file attachment. Undelete may be possible using the file-app of the cloud-software.'),

      'participant-attachment-upload-replace' => $this->l->t('Upload a new attachment. The old file will be overwritten but possibly may be restored using hte file-app of the cloud-software.'),

      'participant-attachment-upload-rename' => $this->l->t('Upload a new attachment. The old file will be renamed by attaching the current time to its name.'),

      'participant-attachment-upload' => $this->l->t('Click to upload the relevant file or use drag and drop anywhere in this data-row.'),

      'participant-attachment-download' => $this->l->t('Click to download this file.'),

      'participant-attachment-open-parent' => $this->l->t('Open the containing folder using the file-app of the cloud.'),

      'participant-field-' => $this->l->t('placeholder'),

      'participant-field-choices-groupofpeople' => $this->l->t('Group of people, e.g. to define room-mates.'),

      'participant-field-choices-groupsofpeople' => $this->l->t('Group of people with predefined group-names and a potentially
different maximal number of people fitting in the group. For example to define room-mates.'),

      'participant-field-choices-multiple' => $this->l->t('Multiple choices, excluding each other.'),

      'participant-field-choices-parallel' => $this->l->t('Multiple choices where, more than one option can be selected.'),

      'participant-field-choices-single' => $this->l->t('Simple yes-no choice.'),

      'participant-field-general-simple' => $this->l->t('General date field with the respective meaning.'),

      'participant-field-surcharge-groupofpeople' => $this->l->t('E.g. to define double-room surcharges.'),

      'participant-field-surcharge-groupsofpeople' => $this->l->t('Surcharge-group of people with predefined group-names and a potentially
different maximal number of people fitting in the group. Maybe this is completely useless ...'),

      'participant-field-surcharge-multiple' => $this->l->t('Multiple choices, excluding each other. For the individual choices a potentially different amount of money may be charged.'),

      'participant-field-surcharge-parallel' => $this->l->t('Multiple choice where more than one option can be selected. For the individual choices a potentially different amount of money may be charged.'),

      'participant-field-surcharge-single' => $this->l->t('Simple yes-no choice which increases the project fees. Please fill also the "amount" field.'),

      'participant-fields-data-options' => [
        'generator' => $this->l->t('Name of a the generator for this field. Can be be a fully-qualified PHP class-name or one of the known short-cuts.'),
        'generator-run' => $this->l->t('Run the value generator. Depending on the generator this might result in new fields or just does nothing if all relevant fields are already there.'),
        'regenerate' => $this->l->t('Recompute the values of this particular recurring field.'),
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
      ],

      'participant-fields-recurring-data' => [
        'delete-undelete' => $this->l->t('Delete or undelete the receivable for this musician. The data will only be deleted when hitting the "save"-button of the form. Undelete is only possible until the "save"-button has been clicked.'),
        'regenerate' => $this->l->t('Recompute the values of this particular recurring field. The action will be performed immediately.'),
        'regenerate-all' => $this->l->t('Recompute all receivables for the musician. Note that this will reload the input-form discarding all changes which have not been saved yet.'),
      ],

      'participant-fields-data-options-single' => $this->l->t('For a surcharge option, please enter here the surcharge amount
associated with this option.'),

      'participant-fields-default-multi-value' => $this->l->t('Specify a default value for the custom field here. Leave blank if unsure.'),

      'participant-fields-default-single-value' => $this->l->t('Specify a default value for the custom field here. Leave blank if unsure.'),

      'participant-fields-default-file-data-value' => $this->l->t('Default policy when replacing files with new uploads. Default is to rename the old file by attaching a time-stamp. "replace" will just overwrite the old  data. Note that independent of this setting the file-app of the cloud may provide undelete operations and versioning of overwritten files.'),

      'participant-fields-default-value' => $this->l->t('Specify a default value for the custom field here. Leave blank if unsure.'),

      'participant-fields-disabled' => $this->l->t('Disable this extra field. This will not erase any data in the
data-base, but simply mark the field as unused and hide it from sight.'),

      'participant-fields-display-order' => $this->l->t('Define the display priority. Larger values will move the item more to
the left or the top inside its table-tab.'),

      'participant-fields-encrypted' => $this->l->t('Expert use: store encrypted values in the data-base. If unsure: <em>DON\'T</em>'),

      'participant-fields-extra-tab' => $this->l->t('Extra-tab to group project-specific data which just didn\'t fit
somewhere else.'),

      'participant-fields-field-index' => $this->l->t('Backwards-compatibility link into extra-data stored together with old
projects.'),

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

      'participant-fields-type' => $this->l->t('Data-type for the custom field. The most practical types are probably
yes-no and multiple-choice options. Extra-charge options can also be
defined, with the respective extra-charge amount tied to the option.'),

      'participant-fields-writers' => $this->l->t('Members of these Cloud user-groups are allowed to change the
field. If left blank, every logged in user is allowed to change this field.'),

      'file-attachments-select' => $this->l->t('Select-box with all currently uploaded attachments. Note that a file will only be attached to a message if it is also checked in this select box.'),

      'filter-visibility' => $this->l->t('Toggle the initial display of the search-filters for data-base tables
in order to make the table view a little less crowded. Search-filters
can be reenabled at any time by clicking the ``Search\'\' button in
each individual table view.'),

      'further-settings' => $this->l->t('Further personal settings, normally not needed use with care.'),

      'project-instrumentation-numbers-required' => $this->l->t('The number of the required musicians per instrument per voice (if the section is split by voices, e.g. "violin 1", "violin 2")'),

      'project-instrumentation-numbers-voice' => $this->l->t('The voice for the respective instrument. Leave at the default to signal that this instrument does not need to be separated into voices. You probably want to distinguish between violin 1 and violin 2, thought ...'),

      'project-instrumentation-numbers-balance' => $this->l->t('The differences between the number of required musicians and the registered / confirmed musicians.'),

      'instrument-insurance-bill' => $this->l->t('Generate a PDF with detailed records of the insured items and the
resulting insurance fee.'),

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

      'musican-contact-tab' => $this->l->t('Display name, pre-name, phone number, email, street-address.'),

      'musician-disabled' => $this->l->t('Musicians which already paid something for the project cannot be
deleted in order not to loose track of the payments. Instead, they are
simply marked as "disabled" and normally are hidden from sight.'),

      'musician-instruments-disabled' => $this->l->t('Instruments which were formerly known to be played by the respective musican but which are disabled for whatever reason. Re-enable by simply adding them again to the list of the musician\'s instruments.'),

      'musician-instrument-insurance' => $this->l->t('Opens a new table view with the insured items for the respective musician.'),

      'musician-miscinfo-tab' => $this->l->t('Further information like birthday, a photo, date of last change.'),

      'musician-orchestra-tab' => $this->l->t('Display name, pre-name, instruments, status, general remarks.'),

      'new-email-template' => $this->l->t('Enter a short, no-nonsense name for the new template. Please omit spaces.'),

      'new-project-event' => $this->l->t('Add a new event for the project. The event is added to the respective
calendar and will also be visible and editable through the calendar
app. It is also possible to subscribe to the calendars using a
suitable CalDAV client from your smartphone, tablet or desktop
computer. The link between an "ordinary" event in the web-calendar and
a project is maintained by attching the project name as "category" to
the event.'),

      'nothing' => $this->l->t('nothing'),

      'cloud-attachment' => $this->l->t('Choose a file to attach from the files stored remotely on in the Cloud storage area.'),

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

      'pme-add' => $this->l->t('  Click me to add a new
row to the current table.'),

      'pme-apply' => $this->l->t('Saves the current values; the current input form will remain active.'),

      'pme-bulkcommit' => $this->l->t('  Click me to add all selected musicians
to the selected project. All selected
musicians on all pages will be added.'),

      'pme-bulkcommit+' => $this->l->t('  Click me to pre-select
all musicians on all pages
for the current project.
Please click the ``Add all\'\'-button to
actually add them.'),

      'pme-bulkcommit-' => $this->l->t('  Click me to remove
all musicians on all pages
from the pre-selection for
the current project.'),

      'pme-bulkcommit-check' => $this->l->t('  Check me to pre-select
this musician for the current
project. Please click the
 ``Add all\'\'-button to
actually add all selected
musicians.'),

      'pme-cancel' => array(
        'default' => $this->l->t('Stop the current operation. Settings which already have been stored by
hitting an "Apply" button are maintained, though. You will be returned
to the previous view.'),
        'canceldelete' => $this->l->t('Stop the current operation. You will be returned to the previous view.'),
      ),

      'pme-change' => $this->l->t('Directs you to a form with input fields. From there you can return to
this form by means of the "Save" or "Back" resp. "Cancel" buttons.'),

      'pme-change-navigation' => array(
        'operation' => $this->l->t('Einzelnen Datensatz anzeigen,
zeigt ein neues Formular mit
detaillierten Eingabefeldern
und Abbruchmöglichkeit.'),
      ),

      'pme-clear' => array(
        'sfn' => $this->l->t('  Klick mich, um die
Sortierreihenfolge auf die
Voreinstellung zurückzusetzen.'),
        'sw' => $this->l->t('  Klick mich, um den
aktuellen Filter zu löschen.'),
      ),

      'pme-copy-navigation' => array(
        'operation' => $this->l->t('Einzelnen Datensatz kopieren,
zeigt ein neues Formular mit
detaillierten Eingabefeldern
und Abbruchmöglichkeit.'),
      ),

      'pme-debit-note' => $this->l->t('Click me to export a CSV-table with the selected debit notes suitable for use with AQBanking command-line tool `aqbanking-cli\'. Please refer to the HOWTO in the wiki for further information. Clicking this button will also open the email dialog in order to inform the selected musicians about debiting their bank account.'),

      'pme-debit-note+' => $this->l->t('Select all displayed debit-notes for export.'),

      'pme-debit-note-' => $this->l->t('Deselect all displayed debit-notes from export selection.'),

      'pme-debit-note-check' => $this->l->t('Select this debit note for debiting the project fees. In order to actually export the debit-note you have to hit the `Debit\' button above.'),

      'pme-delete-navigation' => array(
        'operation' => $this->l->t('Einzelnen Datensatz löschen,
zeigt den aktuellen Datensatz zunächst an.
Gelöscht wird der erst nach einer
weiteren Bestätigung. Trotzdem:
VORSICHT!.'),
      ),

      'pme-email' => $this->l->t('  Klick mich, um eine Em@il an die ausgewählten
Musiker zu versenden. Auf der folgenden Seite kann
die Auswahl dann noch modifiziert werden.
`ausgewält\' bedeutet: nicht
nur die auf der aktuellen
Anzeige-Seite, sondern
alle, die den Such-Kriterien
entsprechen.'),

      'pme-email+' => $this->l->t('  Klick mich, um alle gerade
angezeigten Musiker zu der
Em@il-Auswahl hinzuzufügen.
`angezeigt\' bedeutet: nicht
nur die auf der aktuellen
Anzeige-Seite, sondern
alle, die den Such-Kriterien
entsprechen.'),

      'pme-email-' => $this->l->t('  Klick mich, um alle gerade
angezeigten Musiker von der
Em@il-Auswahl zu entfernen'),

      'pme-email-check' => $this->l->t('  Adressaten in potentielle
Massenmail Adressliste aufnehmen.
Die Adressaten kann man
vor dem Senden der Em@il noch
korrigieren.'),

      'pme-export-choice' => $this->l->t('Export the visible part of the data-base to an office-format. The `Excel\'-export should produce useful input for either Libre- or OpenOffice or for the product of some well-known software-corporation with seat in Redmond, USA.'),

      'pme-export-csv' => $this->l->t('Export in CSV-format using a semicolon as delimiter (Excel convention)'),

      'pme-export-ods' => $this->l->t('Export in OpenDocument-format (LibreOffice/OpenOffice)'),

      'pme-export-pdf' => $this->l->t('Export as PDF in A3/Landscape, scaled to fit the page size'),

      'pme-export-excel' => $this->l->t('Export as `full-featured\' Excel-2007 table, `.xslx\'.'),

      'pme-export-html' => $this->l->t('Export as HTML page without navigation elements; can also be loaded into your office-programs.'),

      'pme-filter' => $this->l->t('Field for filter/search criteria.
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

      'pme-filter-negate' => $this->l->t('Negate the filter, i.e. search for anything not matching the selected options.'),

      'pme-gotoselect' => $this->l->t('Jumps directly to the given page'),

      'pme-hide' => array(
        'sw' => $this->l->t('  Klick mich, um die
Suchkriterien zu verstecken.'),
      ),

      'pme-input-lock-empty' => $this->l->t('Click to unlock if the field is empty, click again to clear the field if the field contains data.'),

      'pme-input-lock-unlock' => $this->l->t('Click to lock and unlock this input field.'),

      'pme-instrumentation-actions' => $this->l->t('Some usefull convenience actions (click me for details!)'),

      'pme-lock-unlock' => $this->l->t('Lock and unlock the underlying input-field.'),

      'pme-more' => array(
        'moreadd' => $this->l->t('Saves the current values and start to generate another new data-set.'),
        'morecopy' => $this->l->t('Saves the current values and continues to make yet another copy of the source data-set.'),
        'morechange' => $this->l->t('Saves the current values; the current input form will remain active.'),
      ),

      'pme-pagerowsselect' => $this->l->t('Limits the number of rows per page to the given value. A "*" means to display all records on one large page.'),

      'pme-query' => $this->l->t('  Klick mich, um die
aktuellen Suchkriterien anzuwenden. Suchkriterien
können in den Feldern eingegeben werden.
Als Platzhalter verwendet man `%%\'.'),

      'pme-reload' => array(
        'reloadview' => $this->l->t('Refreshes the current view by reloading all data from the data-base.'),
        'reloadchange' => $this->l->t('Discards all unsaved data and reloads all fields form the
data-base. Settings which already have been stored by hitting an
"Apply" button are maintained, though.'),
      ),

      'pme-save' => array(
        'default' => $this->l->t('Saves the current values and returns to the previous view.'),
        'savedelete' => $this->l->t('Deletes the current record and returns to the previous view.'),
      ),

      'pme-search' => array(
        'sw' => $this->l->t('  Klick mich, um die
Suchkriterien anzuzeigen.'),
      ),

      'pme-showall-tab' => $this->l->t('Simply blends in all the columns of the table as if all the tabs would be activated at the same time.'),

      'pme-sort' => $this->l->t('  Klick mich, um das Sortieren
nach diesem Feld ein-
oder auszuschalten!'),

      'pme-sort-rvrt' => $this->l->t('  Klick mich, um die Sortierreihenfolge umzukehren!'),

      'pme-transpose' => $this->l->t('Transpose the displayed table; may be beneficial for tables with only a few rows but many columns!'),

      'pme-view-navigation' => array(
        'operation' => $this->l->t('Einzelnen Datensatz anzeigen'),
      ),

      'project-action-debit-mandates' => $this->l->t('Load a new page with all debit-mandates for project-fees'),

      'project-action-project-participants' => $this->l->t('Display all registered musicians for the selected project. The table
        shows project related details as well as all stored personal
        "information about the respective musician'),

      'project-action-email' => $this->l->t('Opens the email-form for the project inside a dialog window.'),

      'project-action-events' => $this->l->t('Open a dialog with all known
events associated to the project.
Events can be added and modified
as needed.'),

      'project-action-participant-fields' => $this->l->t('Define participant-fields for the instrumentation table. E.g.: surcharge
fields for double-/single-room preference, room-mates and such.'),

      'project-action-files' => $this->l->t('Change to the folder with project related files.'),

      'project-action-financial-balance' => $this->l->t('Change to the folder with the financial balance
sheets for the project (only available after the project
has been ``closed\'\'.'),

      'project-action-project-instrumentation-numbers' => $this->l->t('Display the desired instrumentaion numbers, i.e. how many musicians are already registered for each instrument group and how many are finally needed.'),

      'project-action-wiki' => $this->l->t('Change to the DokuWiki-page for this project (if there is one)'),

      'project-actions' => $this->l->t('Pull-down menu with entries to move on
to pages with the instrumentation, events, instrumentation numbers etc.'),

      'project-direct-debit-allowed' => $this->l->t('Some people gave us debit mandates but still want to pay by bank-transfer. Uncheck in order to exclude the person from direct debits transfers.'),

      'project-participant-fee-summary' => $this->l->t('Sum of the fees for all booked etra items.'),

      'project-finance-tab' => $this->l->t('Everything related to project fees, surcharges, bank transfers, debit
mandates.'),

      'project-infopage' => $this->l->t('Opens a dialog-window which gives access to all stored informations for the project.'),

      'project-instrumentation-tab' => $this->l->t('Displays the columns directly related to the instrumentation for the project.'),

      'project-kind' => $this->l->t('Either "temporary" -- the regular case -- or "permanent" -- the
exceptional case for "virtual pseudo projects". The latter in
particular includes the pseudo-project for the administative board and
the members of the registered orchestra association. Non-permanents
always have per-force the project-year attached to their name,
permanent "pseudo-projects" don\'t, as it does not make any sense.'),

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

      'projectevents-button' => $this->l->t('Open a dialog with all known
events associated to the project.
Events can be added and modified
as needed.'),

      'projectevents-delete' => $this->l->t('Delete the event from the system
(no undo possible).'),

      'projectevents-deselect' => $this->l->t('Exclude all events from
email-submission'),

      'projectevents-detach' => $this->l->t('Detach the respective event
from the project, but do not
delete it from the calender.
The event can be reattached by
adding the project-name to its
categories.'),

      'projectevents-download' => $this->l->t('Download the events as ICS file. In principle it is possible to import
the ICS file into the respective calendar apps of your smartphone,
tablet or desktop computer.'),

      'projectevents-edit' => $this->l->t('Modify the event.'),

      'projectevents-newconcert' => $this->l->t('Add a new concert-event to the project.'),

      'projectevents-newmanagement' => $this->l->t('Add a private management event which is not exposed to the rest of the world.'),

      'projectevents-newother' => $this->l->t('Add some other event to the project.'),

      'projectevents-newrehearsal' => $this->l->t('Add a new rehearsal-event to the project.'),

      'projectevents-select' => $this->l->t('Select all events for
email-submission'),

      'projectevents-selectevent' => $this->l->t('Mark the respective event for being
sent by email as ICS-attachment per email.
Hitting the email button above the form
will open an Email form suitable for
sending the marked events to selected
recipients.'),

      'projectevents-sendmail' => $this->l->t('Click to open an email-form
and send the selected events to
selected recipients.'),

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

      'save-as-template' => $this->l->t('Activate this checkbox in order to save the current email message as
message template. If you leave this check-box unchecked, then messages
will be saved as draft. The difference between a draft and a template
is the following: draft messages will be deleted when the message is
actually sent out (and potentially "inactive" drafts will be purged
from the data-base after some time). Templates will never be
purged. Also, draft messages will be saved with all attachment and --
most important -- inlcuding the set of the currently selected
recipients. Message templates, in contrast, are saved with an empty recipient list, as should be.'),

      'save-email-message' => $this->l->t('Save the currently active email message either as draft
(i.e. including recipients and attachments) or as message template
(without recipients and attachments). Message drafts will be deleted
after actually sending the message, and after some time of inactivity
(say a month or so), message templates are remembered
permanently. Please check the check-box to the left of this button in
order to store the message as template. Either templates or drafts can
also be "actively" deleted but clicking the delete button to the right
of this button.'),

      'save-email-template' => $this->l->t('Save the current email for later re-usal in the data-base.
An email template can contain per-member substitutions with the syntax ${MEMBER::VARIABLE},
where VARIABLE is one of VORNAME, NAME, EMAIL, TELEFON_1, TELEFON_2, STRASSE, PLZ, STADT and LAND.
There is also one global (i.e. not per-member) substitution ${GLOAB$this->l->ORGANIZER} which is substituted
by the pre-names of the organizing committe in order to compose  greetings.'),

      'section-leader-mark' => $this->l->t('This is checked for section-leaders and left blank otherwise.'),

      'select-email-template' => $this->l->t('Select one of the email templates previously stored in the data-base.'),

      'select-stored-messages' => $this->l->t('Select either a message draft or template as base for the current message.'),

      'send-mass-email' => $this->l->t('Attempt to send the stuff you have composed out to your selection of
recipients. Please think thrice about it. In case of an error
additional diagnostic messages may (or may not ...) be available in
the `Debug\' tab'),

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
        'projectDebitNoteMandateForm' => $this->l->t('A fillable PDF form for debit-mandates bound to special projects. The app is able to auto-fill form-fields with the names "projectName", "bankAccountOwner", "projectParticipant", "bankAccountIBAN", "bankAccountBIC", "bank". The fields in the PDF-form are identified by this names. Auto-filled mandates can be downloaed from the musician and project-participant views.'),

        'generalDebitNoteMandateForm' => $this->l->t('A fillable PDF form for debit-mandates bound to special projects. The app is able to auto-fill form-fields with the names "bankAccountOwner", "projectParticipant", "bankAccountIBAN", "bankAccountBIC", "bank". The fields in the PDF-form are identified by this names. Auto-filled mandates can be downloaed from the musician and member\'s project view.'),
      ],

      'test-cafevdb_dbpassword' => $this->l->t('Test data-base passphrase.'),

      'test-dbpassword' => $this->l->t('Check whether the data-base can be accessed with the given account
information and password. The password will only be stored in the
configuration storage if the test can be performed successfully.'),

      'test-linktarget' => $this->l->t('Try to connect to the specified web-address, will open the web-address in another window or tab, depending your local browser preferences.'),

      'total-fee-summary' => $this->l->t('Total amount the participant has to pay, perhaps followed by total amount paid, followed by the outstanding amount.'),

      'transfer-registered-instruments' => $this->l->t('Add the instruments of the actually registered musicians to the instrument-table for the project.'),

      'upload-attachment' => $this->l->t('Upload a file from your local computer as attachment. The file will be removed from the remote-system after the message has been sent.'),

      'wysiwyg-edtior' => $this->l->t('Change to another WYSIWYG editor.'),


    ];

  } // method makeToolTips()
}; // class toolTips

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
