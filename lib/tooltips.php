<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016 Claus-Justus Heine <himself@claus-justus-heine.de>
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

/**CamerataDB namespace to prevent name-collisions.
 */
namespace CAFEVDB
{

  /** Tool-tips management with translations. */
  class ToolTips
  {
    static private $toolTipsData = '';
    static function toolTips()
    {
      if (self::$toolTipsData == '') {
        self::$toolTipsData = self::makeToolTips();
      }
      return self::$toolTipsData;
    }
    static private function makeToolTips()
    {
      return array(
        'address-book-emails' => L::t('Opens a select-box with choices from the shared Owncloud-addressbook. You can also add new em@il-addresses to the address-book for later reusal. The addresses can also be added in the Owncloud `Contacts\'-App.'),

        'blog-acceptentry' => L::t('Save the changes for this blog-entry.'),

        'blog-cancelentry' => L::t('Discard the changes for this blog-entry.'),

        'blog-newentry' => L::t('Write a new bulletin entry.'),

        'blog-popup-clear' => L::t('Disable the pop-up function for this blog-note. The list of associated readers is maintained, so reenabling the pop-up function will still not present this note to users already on the reader-list.'),

        'blog-popup-set' => L::t('Place this note in a pop-up window after login. The window will only pop-up once, the list of readers is remembered.'),

        'blog-priority' => L::t('Change the display-priority. Entries with higher priority are
displayed closer to the top of the page.'),

        'blog-reader-clear' => L::t('Clear the list of readers of this note. Consequently, if this note is marked as popup, then it will pop-up again after clearing the list of readers.'),

        'blogentry-delete' => L::t('Delete the message and the message-thread depending on this message.'),

        'blogentry-edit' => L::t('Edit the bulletin entry; everyone is allowed to do so.'),

        'blogentry-lower' => L::t('Decrease the display priority; move the note closer to the bottom of the page.'),

        'blogentry-raise' => L::t('Increase the display priority; move the note closer to the top of the page.'),

        'blogentry-reply' => L::t('Write a follow-up to the bulletin entry.'),

        'blogentry-sticky' => L::t('Toggle the sticky marker; sticky notes are listed at the top of the list.'),

        'cancel-email-composition' => L::t('Cancel the email composition and close the input form. This has the
same effect as clicking the close button on top of the dialog-window. No email will be sent.'),

        'club-member-project' => L::t('Name of the pseudo-project listing the permanent members of the orchestra.'),

        'configrecheck' => L::t('Perform the configuration checks again. If all checks have been passed then you are led on to the ordinary entry page of the application.'),

        'debit-mandate-orchestra-member' => L::t('Please check this box if this musician is a club-member. Otherwise
please leave it unchecked.'),

        'debit-note-creation-time' => L::t('The time when the debit note data was created.'),

        'debit-note-date-of-submission' => L::t('The date when the debit note records were actually transferred to the
bank.'),

        'debit-note-due-date' => L::t('The date when (hopefully) the amount debited will reach our own bank
account.'),

        'debit-note-email-message-id' => L::t('Email message-id header of the notification email for this debit-note.'),

        'debit-note-job-choice' => L::t('Select here which kind of debit note you would like to
issue. Basically, besides the special instrument insureance and
member-fee debits there are the choices of an arbitrary amount, the
project fee deposit are the remaining (are all, if there was no
deposit) project fees. In any case the data-base application will take
care not to draw to much money, based on the payments stored in the
data-base.'),

        'debit-note-job-option-amount' => L::t('Draw an arbitrary amount from the debitor. However, the amount is
automatically limited not to exceed the outstanding debts of the
musician.'),

        'debit-note-job-option-deposit' => L::t('Just draw an amount up to the deposit for the project. If there are
already payments for the project which sum up to the deposit amount,
then nothing is debited.'),

        'debit-note-job-option-insurance' => L::t('Issue the yearly debit-note for the instrument insurance.'),

        'debit-note-job-option-membership-fee' => L::t('Issue the yearly debit-note for the member-ship fee.'),

        'debit-note-job-option-remaining' => L::t('Issue a debit-note over the remaining debts of the musician, taking
into account how much already has been paid.'),

        'debit-note-submission-deadline' => L::t('Date of latest submission of the debit note to our own bank.'),

        'debit-notes-announce' => L::t('Inform all debitors of this debit-note by email; an email dialog is
opened.'),

        'debit-notes-download' => L::t('Download the data-set of this debit-note for transferal to our bank
institute.'),

        'debug-mode' => L::t('Amount of debug output. Keep this disabled for normal use.'),

        'delete-all-event-attachments' => L::t('Clear the list of selected event-attachments. Of course, this does not delete the events from their respective calendar, it just de-selects all events such that no event will be attached to the email.'),

        'delete-all-file-attachments' => L::t('Delete all uploaded file-attachments from the server. This is also done automatically when closing the email-form. This will also empty the select box.'),

        'delete-saved-message' => L::t('Delete the selected email-template or draft. You will be asked for confirmation before it is actually deleted.'),

        'direct-change' => L::t('If enabled, clicking on a data-row in a table view opens the "change
dialog" for the respective record. If disabled, clicking on a data-row will open the "view dialog".'),

        'email-account-distribute' => L::t('Distribute the email account credentials to all members of the orchestra group. The credentials will be encrypted using an OpenSSL public key owned by the respective user and stored in the pre-user preferences table.'),

        'email-message-export' => L::t('Export the email text as HTML. In the case of per-member variable
substitutions this will result in a multi-page document with proper page breaks after each message, with all variables substituted.'),

        'email-recipients-basic-set' => L::t('Choose either among all musicians currently registered for the project
or from the complement set. Obviously, selecting both options will
give you the choice to select any musician as recipient.'),

        'email-recipients-broken-emails' => L::t('List of musicians without or with ill-formed email-addresses. You can click on the names in order to open a dialog with the personal data of the respective musician and correct the email addresses there.'),

        'email-recipients-choices' => L::t('Select the recipients for your email!'),

        'email-recipients-except-project' => L::t('Choose among all musicians currently <b>NOT</b> registered for this project.'),

        'email-recipients-filter-apply' => L::t('Apply the currently selected instruments as filter. At your option,
you can also simply double-click inside the boxed filter-region in order to activate your filter-choice.'),

        'email-recipients-filter-redo' => L::t('Redo the last operation undone by the undo button.'),

        'email-recipients-filter-reset' => L::t('Reset to the initial pre-selection which was activ when entering this
form. This will <b>REALLY</b> void all your recipient selections and
reset the form to the initial state. Note, however, that the text of
your email will be maintained, the reset only applies to the recipient
list.'),

        'email-recipients-filter-undo' => L::t('Undo the last recipient filter operation and restore the previous selection of musicians.'),

        'email-recipients-freeform-BCC' => L::t('Add arbitrary further hidden recipients.'),

        'email-recipients-freeform-CC' => L::t('Add arbitrary further recipients.'),

        'email-recipients-from-project' => L::t('Choose among all musicians currently registered for this project.'),

        'email-recipients-instruments-filter' => L::t('Restrict the basic set of musicians to the instruments selected
here. The filter is additive: selecting more than one instruments will
include the musicians playing either of them.'),

        'email-recipients-instruments-filter-container' => L::t('A double click inside the boxed filter-region will apply the instruments-filter'),

        'email-recipients-instruments-filter-label' => L::t('A double click inside the boxed filter-region will apply the instruments-filter'),

        'email-recipients-listing' => L::t('List of selected musicians; can be changed in the `Em@il-Recipients\' panel.'),

        'email-recipients-member-status-filter' => L::t('Select recipients by member status. Normally, conductors and soloists
are excluded from receiving mass-email. Please be careful when modifying the default selection!'),

        'emailtest' => L::t('Test the email-settings; try to connect to the SMTP-server and the IMAP-server in turn.'),

        'event-attachments-select' => L::t('Select-box with all project-events. You can select events as attachments to your email.'),

        'events-attachment' => L::t('Select calendar attachments from the associated project events.'),

        'executive-board-project' => L::t('Name of the pseudo-project listing the members of the executive board.'),

        'expert-mode' => L::t('Display additional ``expert\'\' settings. Despite the name you are
invited to have a look, but please do not change anything unless you know what your are doing. Thanks!'),

        'expert-operations' => L::t('For those who know what they are doing, which essentially means: don\'t.'),

        'extra-field-' => L::t('placeholder'),

        'extra-field-choices-groupofpeople' => L::t('Group of people, e.g. to define room-mates.'),

        'extra-field-choices-groupsofpeople' => L::t('Group of people with predefined group-names and a potentially
different maximal number of people fitting in the group. For example to define room-mates.'),

        'extra-field-choices-multiple' => L::t('Multiple choices, excluding each other.'),

        'extra-field-choices-parallel' => L::t('Multiple choices where, more than one option can be selected.'),

        'extra-field-choices-single' => L::t('Simple yes-no choice.'),

        'extra-field-general-simple' => L::t('General date field with the respective meaning.'),

        'extra-field-surcharge-groupofpeople' => L::t('E.g. to define double-room surcharges.'),

        'extra-field-surcharge-groupsofpeople' => L::t('Surcharge-group of people with predefined group-names and a potentially
different maximal number of people fitting in the group. Maybe this is completely useless ...'),

        'extra-field-surcharge-multiple' => L::t('Multiple choices, excluding each other. For the individual choices a potentially different amount of money may be charged.'),

        'extra-field-surcharge-parallel' => L::t('Multiple choice where more than one option can be selected. For the individual choices a potentially different amount of money may be charged.'),

        'extra-field-surcharge-single' => L::t('Simple yes-no choice which increases the project fees. Please fill also the "amount" field.'),

        'extra-fields-allowed-values' => array(
          'default' => L::t('Table with all admissible values for this multiple choice option.'),
          'placeholder' => L::t('In order to add a new option just enter its name here and hit enter or
just click somewhere else. Further attributes can be changed later (data-base key, label, data, context help)'),
          'key' => L::t('Please enter here a unique short non-nonsense key. You will no longer
be able to change this db-key once this option has be attached to a
musician. However, changing the display-label (just the field to the right) is always possible.'),
          'label' => L::t('Just the display-label shown in the select-boxes in the instrumentation table.'),
          'data' => L::t('For surcharge-items this is just the surcharge-amount associated with
the option. For other multi-choice items this is just one arbitrary
string. Please entry the surcharge amount for surcharge items here.'),
          'tooltip' => L::t('An extra-tooltip which can be associated to this specific option. A
help text in order to inform others what this option is about.'),
          'limit' => L::t('The maximum allowed number of people in a "group of people" field'),
          ),

        'extra-fields-allowed-values-single' => L::t('For a surcharge option, please enter here the surcharge amount
associated with this option.'),

        'extra-fields-default-multi-value' => L::t('Specify a default value for the custom field here. Leave blank if unsure.'),

        'extra-fields-default-single-value' => L::t('Specify a default value for the custom field here. Leave blank if unsure.'),

        'extra-fields-default-value' => L::t('Specify a default value for the custom field here. Leave blank if unsure.'),

        'extra-fields-delete-undelete' => L::t('Hit this button to delete or undelete each item. Note that items that
already have been associated with musicians in the data-base can no
longer be "really" deleted. Instead, an attempt to delete them will
just result in marking them as "inactive". Inactive items will be kept
until the end of the world (or this data-base application, whatever
happens to come earlier). Inactive buttons will no longer show up in
the instrumentation table, but inactive items can be "undeleted", just
but clicking this button again.'),

        'extra-fields-disabled' => L::t('Disable this extra field. This will not erase any data in the
data-base, but simply mark the field as unused and hide it from sight.'),

        'extra-fields-display-order' => L::t('Define the display priority. Larger values will move the item more to
the left or the top inside its table-tab.'),

        'extra-fields-encrypted' => L::t('Expert use: store encrypted values in the data-base. If unsure: <em>DON\'T</em>'),

        'extra-fields-extra-tab' => L::t('Extra-tab to group project-specific data which just didn\'t fit
somewhere else.'),

        'extra-fields-field-index' => L::t('Backwards-compatibility link into extra-data stored together with old
projects.'),

        'extra-fields-field-name' => L::t('Just the name for this option. Please keep the name as <em>short</em> as
possible, but try to be descriptive. If further explanations are
needed, then please enter those in the <strong>Tooltip</strong> field in the
<strong>Display</strong> tab.'),

        'extra-fields-maximum-group-size' => L::t('The maximum number of peopel allowed in the group.'),

        'extra-fields-new-tab' => L::t('Define a new table-tab. In order to do so, first deselect any
predefined tab in the select box above, then enter the new name. The
new tab-name will also be available as tab-option for other fields.'),

        'extra-fields-readers' => L::t('Members of these OwnCloud user-groups are allowed to view the
field. If left blank, every logged in user is allowed to view the
field.'),

        'extra-fields-show-data' => L::t('Each option has an optional data-entry attached to it. Normally, this
is only useful for surcharge options, where the "data-entry" just is
the extra-charge amount associated to the option. Still, if you feel a
need to view the data-items also for non-surcharge options, then just
click here.'),

        'extra-fields-show-deleted' => L::t('Options already attached to musicians can no longer be deleted in
order to prevent data-loss in the underlying data-base. It is even
possible to recover those options by checking this checkbox in order to make them visible and
clicking the "recover" button to the left of each deleted entry.'),

        'extra-fields-tab' => L::t('Define the table-tab this entry should be grouped with. It is also
possible to define new table-tabs. In order to do so, first deselect
any possible selected tab, and then enter the name of a new tab in the
input box below.'),

        'extra-fields-tooltip' => L::t('Optionally define a tool-tip (context-help) for the field. The tooltip
may contain HTML formatting.'),

        'extra-fields-type' => L::t('Data-type for the custom field. The most practical types are probably
yes-no and multiple-choice options. Extra-charge options can also be
defined, with the respective extra-charge amount tied to the option.'),

        'extra-fields-writers' => L::t('Members of these OwnCloud user-groups are allowed to change the
field. If left blank, every logged in user is allowed to change this field.'),

        'file-attachments-select' => L::t('Select-box with all currently uploaded attachments. Note that a file will only be attached to a message if it is also checked in this select box.'),

        'filter-visibility' => L::t('Toggle the initial display of the search-filters for data-base tables
in order to make the table view a little less crowded. Search-filters
can be reenabled at any time by clicking the ``Search\'\' button in
each individual table view.'),

        'further-settings' => L::t('Further personal settings, normally not needed use with care.'),

        'instrument-insurance-bill' => L::t('Generate a PDF with detailed records of the insured items and the
resulting insurance fee.'),

        'instruments-disabled' => L::t('Instruments which are already used by musicians or
projects cannot be deleted; instead "deleting" them flags them as "Disabled".'),

        'member-status' => L::t('A flag which indicates not so much social or functional status, but
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

        'musican-contact-tab' => L::t('Display name, pre-name, phone number, email, street-address.'),

        'musician-disabled' => L::t('Musicians which already paid something for the project cannot be
deleted in order not to loose track of the payments. Instead, they are
simply marked as "disabled" and normally are hidden from sight.'),

        'musician-instrument-insurance' => L::t('Opens a new table view with the insured items for the respective musician.'),

        'musician-miscinfo-tab' => L::t('Further information like birthday, a photo, date of last change.'),

        'musician-orchestra-tab' => L::t('Display name, pre-name, instruments, status, general remarks.'),

        'new-email-template' => L::t('Enter a short, no-nonsense name for the new template. Please omit spaces.'),

        'new-project-event' => L::t('Add a new event for the project. The event is added to the respective
calendar and will also be visible and editable through the calendar
app. It is also possible to subscribe to the calendars using a
suitable CalDAV client from your smartphone, tablet or desktop
computer. The link between an "ordinary" event in the web-calendar and
a project is maintained by attching the project name as "category" to
the event.'),

        'nothing' => L::t('nothing'),

        'owncloud-attachment' => L::t('Choose a file to attach from the files stored remotely on in the OwnCloud storage area.'),

        'ownclouddev-link' => L::t('Web-link to the current OwnCloud developer documentation.'),

        'payment-status' => L::t('Status of outstanding project fees:
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

        'phpmyadmin-link' => L::t('Link to the data-base administration tool for the underlying data-base. Swiss-army-knife-like.'),

        'phpmyadminoc-link' => L::t('Link to the documentation for the database-management tool.'),

        'pme-add' => L::t('  Click me to add a new
row to the current table.'),

        'pme-apply' => L::t('Saves the current values; the current input form will remain active.'),

        'pme-bulkcommit' => L::t('  Click me to add all selected musicians
to the selected project. All selected
musicians on all pages will be added.'),

        'pme-bulkcommit+' => L::t('  Click me to pre-select
all musicians on all pages
for the current project.
Please click the ``Add all\'\'-button to
actually add them.'),

        'pme-bulkcommit-' => L::t('  Click me to remove
all musicians on all pages
from the pre-selection for
the current project.'),

        'pme-bulkcommit-check' => L::t('  Check me to pre-select
this musician for the current
project. Please click the
 ``Add all\'\'-button to
actually add all selected
musicians.'),

        'pme-cancel' => array(
          'default' => L::t('Stop the current operation. Settings which already have been stored by
hitting an "Apply" button are maintained, though. You will be returned
to the previous view.'),
          'canceldelete' => L::t('Stop the current operation. You will be returned to the previous view.'),
          ),

        'pme-change' => L::t('Directs you to a form with input fields. From there you can return to
this form by means of the "Save" or "Back" resp. "Cancel" buttons.'),

        'pme-change-navigation' => array(
          'operation' => L::t('Einzelnen Datensatz anzeigen,
zeigt ein neues Formular mit
detaillierten Eingabefeldern
und Abbruchmöglichkeit.'),
          ),

        'pme-clear' => array(
          'sfn' => L::t('  Klick mich, um die
Sortierreihenfolge auf die
Voreinstellung zurückzusetzen.'),
          'sw' => L::t('  Klick mich, um den
aktuellen Filter zu löschen.'),
          ),

        'pme-copy-navigation' => array(
          'operation' => L::t('Einzelnen Datensatz kopieren,
zeigt ein neues Formular mit
detaillierten Eingabefeldern
und Abbruchmöglichkeit.'),
          ),

        'pme-debit-note' => L::t('Click me to export a CSV-table with the selected debit notes suitable for use with AQBanking command-line tool `aqbanking-cli\'. Please refer to the HOWTO in the wiki for further information. Clicking this button will also open the email dialog in order to inform the selected musicians about debiting their bank account.'),

        'pme-debit-note+' => L::t('Select all displayed debit-notes for export.'),

        'pme-debit-note-' => L::t('Deselect all displayed debit-notes from export selection.'),

        'pme-debit-note-check' => L::t('Select this debit note for debiting the project fees. In order to actually export the debit-note you have to hit the `Debit\' button above.'),

        'pme-delete-navigation' => array(
          'operation' => L::t('Einzelnen Datensatz löschen,
zeigt den aktuellen Datensatz zunächst an.
Gelöscht wird der erst nach einer
weiteren Bestätigung. Trotzdem:
VORSICHT!.'),
          ),

        'pme-email' => L::t('  Klick mich, um eine Em@il an die ausgewählten
Musiker zu versenden. Auf der folgenden Seite kann
die Auswahl dann noch modifiziert werden.
`ausgewält\' bedeutet: nicht
nur die auf der aktuellen
Anzeige-Seite, sondern
alle, die den Such-Kriterien
entsprechen.'),

        'pme-email+' => L::t('  Klick mich, um alle gerade
angezeigten Musiker zu der
Em@il-Auswahl hinzuzufügen.
`angezeigt\' bedeutet: nicht
nur die auf der aktuellen
Anzeige-Seite, sondern
alle, die den Such-Kriterien
entsprechen.'),

        'pme-email-' => L::t('  Klick mich, um alle gerade
angezeigten Musiker von der
Em@il-Auswahl zu entfernen'),

        'pme-email-check' => L::t('  Adressaten in potentielle
Massenmail Adressliste aufnehmen.
Die Adressaten kann man
vor dem Senden der Em@il noch
korrigieren.'),

        'pme-export-choice' => L::t('Export the visible part of the data-base to an office-format. The `Excel\'-export should produce useful input for either Libre- or OpenOffice or for the product of some well-known software-corporation with seat in Redmond, USA.'),

        'pme-export-csv' => L::t('Export in CSV-format using a semicolon as delimiter (Excel convention)'),

        'pme-export-excel' => L::t('Export as `full-featured\' Excel-2007 table, `.xslx\'.'),

        'pme-export-html' => L::t('Export as HTML page without navigation elements; can also be loaded into your office-programs.'),

        'pme-export-htmlexcel' => L::t('Export as HTML page, but set the file type to `spread-sheed\'. Should also be readable by standard office-programs as `Excel\'-table.'),

        'pme-filter' => L::t('Field for filter/search criteria.
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

        'pme-filter-negate' => L::t('Negate the filter, i.e. search for anything not matching the selected options.'),

        'pme-gotoselect' => L::t('Jumps directly to the given page'),

        'pme-hide' => array(
          'sw' => L::t('  Klick mich, um die
Suchkriterien zu verstecken.'),
          ),

        'pme-instrumentation-actions' => L::t('Some usefull convenience actions (click me for details!)'),

        'pme-more' => array(
          'moreadd' => L::t('Saves the current values and start to generate another new data-set.'),
          'morecopy' => L::t('Saves the current values and continues to make yet another copy of the source data-set.'),
          'morechange' => L::t('Saves the current values; the current input form will remain active.'),
          ),

        'pme-pagerowsselect' => L::t('Limits the number of rows per page to the given value. A "*" means to display all records on one large page.'),

        'pme-query' => L::t('  Klick mich, um die
aktuellen Suchkriterien anzuwenden. Suchkriterien
können in den Feldern eingegeben werden.
Als Platzhalter verwendet man `%%\'.'),

        'pme-reload' => array(
          'reloadview' => L::t('Refreshes the current view by reloading all data from the data-base.'),
          'reloadchange' => L::t('Discards all unsaved data and reloads all fields form the
data-base. Settings which already have been stored by hitting an
"Apply" button are maintained, though.'),
          ),

        'pme-save' => array(
          'default' => L::t('Saves the current values and returns to the previous view.'),
          'savedelete' => L::t('Deletes the current record and returns to the previous view.'),
          ),

        'pme-search' => array(
          'sw' => L::t('  Klick mich, um die
Suchkriterien anzuzeigen.'),
          ),

        'pme-showall-tab' => L::t('Simply blends in all the columns of the table as if all the tabs would be activated at the same time.'),

        'pme-sort' => L::t('  Klick mich, um das Sortieren
nach diesem Feld ein-
oder auszuschalten!'),

        'pme-sort-rvrt' => L::t('  Klick mich, um die Sortierreihenfolge umzukehren!'),

        'pme-transpose' => L::t('Transpose the displayed table; may be beneficial for tables with only a few rows but many columns!'),

        'pme-view-navigation' => array(
          'operation' => L::t('Einzelnen Datensatz anzeigen'),
          ),

        'project-action-debit-mandates' => L::t('Load a new page with all debit-mandates for project-fees'),

        'project-action-detailed-instrumentation' => L::t('Display all registered musicians for the selected project. The table
        shows project related details as well as all stored personal
        "information about the respective musician'),

        'project-action-email' => L::t('Opens the email-form for the project inside a dialog window.'),

        'project-action-events' => L::t('Open a dialog with all known
events associated to the project.
Events can be added and modified
as needed.'),

        'project-action-extra-fields' => L::t('Define extra-fields for the instrumentation table. E.g.: surcharge
fields for double-/single-room preference, room-mates and such.'),

        'project-action-files' => L::t('Change to the folder with project related files.'),

        'project-action-financial-balance' => L::t('Change to the folder with the financial balance
sheets for the project (only available after the project
has been ``closed\'\'.'),

        'project-action-instrumentation-numbers' => L::t('Display the desired instrumentaion numbers, i.e. how many musicians are already registered for each instrument group and how many are finally needed.'),

        'project-action-wiki' => L::t('Change to the DokuWiki-page for this project (if there is one)'),

        'project-actions' => L::t('Pull-down menu with entries to move on
to pages with the instrumentation, events, instrumentation numbers etc.'),

        'project-direct-debit-allowed' => L::t('Some people gave us debit mandates but still want to pay by bank-transfer. Uncheck in order to exclude the person from direct debits transfers.'),

        'project-extra-fee-summary' => L::t('Sum of the fees for all booked etra items.'),

        'project-finance-tab' => L::t('Everything related to project fees, surcharges, bank transfers, debit
mandates.'),

        'project-infopage' => L::t('Opens a dialog-window which gives access to all stored informations for the project.'),

        'project-instrumentation-tab' => L::t('Displays the columns directly related to the instrumentation for the project.'),

        'project-kind' => L::t('Either "temporary" -- the regular case -- or "permanent" -- the
exceptional case for "virtual pseudo projects". The latter in
particular includes the pseudo-project for the administative board and
the members of the registered orchestra association. Non-permanents
always have per-force the project-year attached to their name,
permanent "pseudo-projects" don\'t, as it does not make any sense.'),

        'project-metadata-tab' => L::t('Displays `meta-data\' like project fees, single/double room preferences, debit-mandates and the like.'),

        'project-name' => L::t('Please enter here a <b>SHORT</b> project name, rather a project tag. The software will try very hard to confine you to the following rules:
<dl>
<dt>BE SHORT, PLEASE</dt>
<dd>No more than 20 characters, s.v.p. Please: rather <b>MUCH</b> shorter.</dd>
<dt>NO SPACES, PLEASE</dt>
<dd>Please use "camel case" instead.</dd>
</dl>
Please <b>DO NOT TRY</b> to "work around" those "limitations. Just don\'t. Thanks.'),

        'project-name-yearattach' => L::t('Append the year to the name if checked.
Regardless of this checkbox any decimal digit will first be stripped from the end
of the project name before the year is added.'),

        'project-personaldata-tab' => L::t('Displays the personal data of the respective musicians, like address, email, date of birth if known, phone numbers.'),

        'project-total-fee-summary' => L::t('The accumulated total of all charges the participant has to pay.'),

        'project-web-article-add' => L::t('Add a new public web-page to the project by generating a new, empty concert announcement.'),

        'project-web-article-delete' => L::t('Delete the currently displayed web-page fromthe project. The web page will be "detached" from the project and moved to the trash-bin
"category" (folger) inside the CMS.'),

        'project-web-article-linkpage' => L::t('Link existing pages to the project. This can be used, for instance, in order to add a page to the project which has not been created by hitting the `+\'-button above, but was created directly in the CMS backend. When linking articles from the `trashbin\' category then those articles will automatically moved to the `preview\' category; this is some not-so-hidden undelete feature.z'),

        'project-web-article-linkpage-select' => L::t('Please select articles to link to the current project. The articles will be immediately added to the project if you select them. In order to remove the article, please use the `-\' button above.'),

        'project-web-article-unlinkpage' => L::t('Detach the currently displayed event announcement from the project. Primarily meant to provide means to undo erroneous linking of articles.'),

        'projectevents-button' => L::t('Open a dialog with all known
events associated to the project.
Events can be added and modified
as needed.'),

        'projectevents-delete' => L::t('Delete the event from the system
(no undo possible).'),

        'projectevents-deselect' => L::t('Exclude all events from
email-submission'),

        'projectevents-detach' => L::t('Detach the respective event
from the project, but do not
delete it from the calender.
The event can be reattached by
adding the project-name to its
categories.'),

        'projectevents-download' => L::t('Download the events as ICS file. In principle it is possible to import
the ICS file into the respective calendar apps of your smartphone,
tablet or desktop computer.'),

        'projectevents-edit' => L::t('Modify the event.'),

        'projectevents-newconcert' => L::t('Add a new concert-event to the project.'),

        'projectevents-newmanagement' => L::t('Add a private management event which is not exposed to the rest of the world.'),

        'projectevents-newother' => L::t('Add some other event to the project.'),

        'projectevents-newrehearsal' => L::t('Add a new rehearsal-event to the project.'),

        'projectevents-select' => L::t('Select all events for
email-submission'),

        'projectevents-selectevent' => L::t('Mark the respective event for being
sent by email as ICS-attachment per email.
Hitting the email button above the form
will open an Email form suitable for
sending the marked events to selected
recipients.'),

        'projectevents-sendmail' => L::t('Click to open an email-form
and send the selected events to
selected recipients.'),

        'projectsbalancefolder-force' => L::t('Force the re-creation of the folder where project balanaces are
stored.'),

        'projectsfolder-force' => L::t('Force the re-creation of the folder where project data is stored.'),

        'redaxo-archive' => L::t('Article category of the concert-archive inside the Redaxo CMS.'),

        'redaxo-preview' => L::t('Article category of the concert-previews inside the Redaxo CMS.'),

        'redaxo-rehearsals' => L::t('Article category of the rehearsals inside the Redaxo CMS.'),

        'redaxo-template' => L::t('Article category for templates inside the Redaxo CMS.'),

        'redaxo-trashbin' => L::t('Trashbin pseudo-category, articles deleted from within the
project-views are moved here.'),

        'register-musician' => L::t('Add the musician to the project. A new form will open were details like the instrument etc. can be adjustetd.'),

        'registration-mark' => L::t('This is checked for officially registered musicians, those, who have
sent us a signed registration form. It is left blannk otherwise.'),

        'save-as-template' => L::t('Activate this checkbox in order to save the current email message as
message template. If you leave this check-box unchecked, then messages
will be saved as draft. The difference between a draft and a template
is the following: draft messages will be deleted when the message is
actually sent out (and potentially "inactive" drafts will be purged
from the data-base after some time). Templates will never be
purged. Also, draft messages will be saved with all attachment and --
most important -- inlcuding the set of the currently selected
recipients. Message templates, in contrast, are saved with an empty recipient list, as should be.'),

        'save-email-message' => L::t('Save the currently active email message either as draft
(i.e. including recipients and attachments) or as message template
(without recipients and attachments). Message drafts will be deleted
after actually sending the message, and after some time of inactivity
(say a month or so), message templates are remembered
permanently. Please check the check-box to the left of this button in
order to store the message as template. Either templates or drafts can
also be "actively" deleted but clicking the delete button to the right
of this button.'),

        'save-email-template' => L::t('Save the current email for later re-usal in the data-base.
An email template can contain per-member substitutions with the syntax ${MEMBER::VARIABLE},
where VARIABLE is one of VORNAME, NAME, EMAIL, TELEFON_1, TELEFON_2, STRASSE, PLZ, STADT and LAND.
There is also one global (i.e. not per-member) substitution ${GLOABL::ORGANIZER} which is substituted
by the pre-names of the organizing committe in order to compose  greetings.'),

        'section-leader-mark' => L::t('This is checked for section-leaders and left blank otherwise.'),

        'select-email-template' => L::t('Select one of the email templates previously stored in the data-base.'),

        'select-stored-messages' => L::t('Select either a message draft or template as base for the current message.'),

        'send-mass-email' => L::t('Attempt to send the stuff you have composed out to your selection of
recipients. Please think thrice about it. In case of an error
additional diagnostic messages may (or may not ...) be available in
the `Debug\' tab'),

        'sepa-debit-mandate-active' => L::t('Used SEPA mandates are not deleted from the DB, but just flagged as
"inactive" if they expire or are manually pseudo-deleted.'),

        'sepa-instant-validation' => L::t('Toggle instant validation and automatic computation of derived bank account data. If instant validation is disabled, the final values will still be validated and an error message will appear if an error is detected. It is only possible to save of store the debit-mandate if instant validation is enabled.'),

        'sepa-mandate-expired' => L::t('This debit-mandate has not been used for more than %d month and
therefore is expired and cannot be used any longer. Pleae delete it
and contact the treasurer for further instructions.',
            array('Finance::SEPA_MANDATE_EXPIRE_MONTHS')
          ),

        'settings-button' => L::t('Personal application settings.'),

        'sharedfolder-force' => L::t('Force the re-creation of the root of the shared-folder hierarchy.'),

        'shareowner-force' => L::t('Re-create the share-owner.'),

        'show-disabled' => L::t('Some data-sets should better not be deleted because they have attached
important - e.g. financial - data attached to it. In this case a
"delete" simply marks the data-set as "disabled". Normally these
data-sets are hidden from the spectator. Checking this option unhides
these data-items.'),

        'show-tool-tips' => L::t('Toggle Tooltips'),

        'sourcecode-link' => L::t('Link to the source-code archives for the DB app.'),

        'sourcedocs-link' => L::t('Link to the source-code documentation for the DB app.'),

        'syncevents' => L::t('Recompute the link between projects and events, using the event-categories as primary key.'),

        'table-rows-per-page' => L::t('The initial number of rows per page when displaying data-base
tables. The actual number of rows per page can also changed later in
the individual table views.'),

        'test-cafevdb_dbpassword' => L::t('Test data-base passphrase.'),

        'test-dbpassword' => L::t('Check whether the data-base can be accessed with the given account
information and password. The password will only be stored in the
configuration storage if the test can be performed successfully.'),

        'test-linktarget' => L::t('Try to connect to the specified web-address, will open the web-address in another window or tab, depending your local browser preferences.'),

        'total-fee-summary' => L::t('Total amount the participant has to pay, perhaps followed by total amount paid, followed by the outstanding amount.'),

        'transfer-registered-instruments' => L::t('Add the instruments of the actually registered musicians to the instrument-table for the project.'),

        'upload-attachment' => L::t('Upload a file from your local computer as attachment. The file will be removed from the remote-system after the message has been sent.'),

        'wysiwyg-edtior' => L::t('Change to another WYSIWYG editor.'),


        );

    }
  }; // class toolTips

} // namespace

/*
 * Local Variables: ***
 * c-basic-offset: 2 ***
 * End: ***
 */
?>
