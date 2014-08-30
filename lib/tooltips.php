<?php
/**Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014 Claus-Justus Heine <himself@claus-justus-heine.de>
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

/** Tool-tips for the phpmyedit-forms.
 *
 */
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
      'blog-newentry' => L::t('Write a new bulletin entry.'),

      'blog-popup-clear' => L::t('Disable the pop-up function for this blog-note. The list of associated readers is maintained, so reenabling the pop-up function will still not present this note to users already on the reader-list.'),

      'blog-popup-set' => L::t('Place this note in a pop-up window after login. The window will only pop-up once, the list of readers is remembered.'),

      'blog-reader-clear' => L::t('Clear the list of readers of this note. Consequently, if this note is marked as popup, then it will pop-up again after clearing the list of readers.'),

      'blogentry-delete' => L::t('Delete the message and the message-thread depending on this message.'),

      'blogentry-edit' => L::t('Edit the bulletin entry; everyone is allowed to do so.'),

      'blogentry-lower' => L::t('Decrease the display priority; move the note closer to the bottom of the page.'),

      'blogentry-raise' => L::t('Increase the display priority; move the note closer to the top of the page.'),

      'blogentry-reply' => L::t('Write a follow-up to the bulletin entry.'),

      'blogentry-sticky' => L::t('Toggle the sticky marker; sticky notes are listed at the top of the list.'),

      'configrecheck' => L::t('Perform the configuration checks again. If all checks have been passed then you are led on to the ordinary entry page of the application.'),

      'emailtest' => L::t('Test the email-settings; try to connect to the SMTP-server and the IMAP-server in turn.'),

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

      'new-email-template' => L::t('Enter a short, no-nonsense name for the new template. Please omit spaces.'),

      'nothing' => L::t('nothing'),

      'owncloud-attachment' => L::t('Choose a file to attach from the files stored remotely on in the OwnCloud storage area.'),

      'pme-add' => L::t('  Click me to add a new
row to the current table.'),

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

      'pme-filter' => L::t('  Field for filter/search criteria.
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

      'pme-hide' => array(
        'sw' => L::t('  Klick mich, um die
Suchkriterien zu verstecken.'),
        ),

      'pme-query' => L::t('  Klick mich, um die
aktuellen Suchkriterien anzuwenden. Suchkriterien
können in den Feldern eingegeben werden.
Als Platzhalter verwendet man `%%\'.'),

      'pme-search' => array(
        'sw' => L::t('  Klick mich, um die
Suchkriterien anzuzeigen.'),
        ),

      'pme-sort' => L::t('  Klick mich, um das Sortieren
nach diesem Feld ein-
oder auszuschalten!'),

      'pme-sort-rvrt' => L::t('  Klick mich, um die Sortierreihenfolge umzukehren!'),

      'pme-transpose' => L::t('Transpose the displayed table; may be beneficial for tables with only a few rows but many columns!'),

      'pme-view-navigation' => array(
        'operation' => L::t('Einzelnen Datensatz anzeigen'),
        ),

      'project-actions' => L::t('Pull-down menu with entries to move on
to pages with the instrumentation, events, instrumentation numbers etc.'),

      'project-action-brief-instrumentation' => L::t('Open a page with the musicians registered for the respective project.'),

      'project-action-detailed-instrumentation' => L::t("Detailed display of all registered musicians for the selected project. The table will allow for modification of personal data like email, phone, address etc."),

      'project-action-events' => L::t('Open a dialog with all known
events associated to the project.
Events can be added and modified
as needed.'),

      'project-action-files' => L::t('Change to the folder with project related files.'),

      'project-action-financial-balance' => L::t('Change to the folder with the financial balance
sheets for the project (only available after the project
has been ``closed\'\'.'),

      'project-action-instrumentation-numbers' => L::t('Display the desired instrumentaion numbers, i.e. how many musicians are already registered for each instrument group and how many are finally needed.'),

      'project-action-wiki' => L::t('Change to the DokuWiki-page for this project (if there is one)'),

      'project-name-yearattach' => L::t('Append the year to the name if checked.
Regardless of this checkbox any decimal digit will first be stripped from the end
of the project name before the year is added.'),

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

      'register-musician' => L::t('Add the musician to the project. A new form will open were details like the instrument etc. can be adjustetd.'),

      'save-email-template' => L::t('Save the current email for later re-usal in the data-base.
An email template can contain per-member substitutions with the syntax ${MEMBER::VARIABLE},
where VARIABLE is one of VORNAME, NAME, EMAIL, TELEFON_1, TELEFON_2, STRASSE, PLZ, STADT and LAND.
There is also one global (i.e. not per-member) substitution ${GLOABL::ORGANIZER} which is substituted
by the pre-names of the organizing committe in order to compose  greetings.'),

      'select-email-template' => L::t('Select one of the email templates previously stored in the data-base.'),

      'syncevents' => L::t('Recompute the link between projects and events, using the event-categories as primary key.'),

      'test-dbpassword' => L::t('Check whether the data-base can be accessed with the given account
information and password. The password will only be stored in the
configuration storage if the test can be performed successfully.'),

      'test-linktarget' => L::t('Try to connect to the specified web-address, will open the web-address in another window or tab, depending your local browser preferences.'),

      'transfer-instruments' => L::t('Add the instruments of the actually registered musicians to the instrument-table for the project.'),

      'upload-attachment' => L::t('Upload a file from your local computer as attachment. The file will be removed from the remote-system after the message has been sent.'),

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
