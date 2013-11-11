<?php

/**CamerataDB namespace to prevent name-collisions.
 */
namespace CAFEVDB
{

/** Tool-tips for the phpmyedit-forms.
 *
 */
class Tooltips
{
  static function pmeToolTips()
  {
    return 
      array(
        'blogentry-raise' => L::t('Increase the display priority; move the note closer to the top of the page.'),
        'blogentry-lower' => L::t('Decrease the display priority; move the note closer to the bottom of the page.'),
        'blog-newentry' => L::t('Write a new bulletin entry.'),
        'blogentry-delete' => L::t('Delete the message and the message-thread depending on this message.'),
        'blogentry-reply' => L::t('Write a follow-up to the bulletin entry.'),
        'blogentry-edit' => L::t('Edit the bulletin entry; everyone is allowed to do so.'),
        'blogentry-sticky' => L::t('Toggle the sticky marker; sticky notes are listed at the top of the list.'),
        'configrecheck' => L::t('Perform the configuration checks again. If all checks have been passed then you are led on to the ordinary entry page of the application.'),
        'test-dbpassword' => L::t('Check whether the data-base can be accessed with the given account
information and password. The password will only be stored in the
configuration storage if the test can be performed successfully.'),
        'select-email-template' => L::t('Select one of the email templates previously stored in the data-base.'),
        'new-email-template' => L::t('Enter a short, no-nonsense name for the new template. Please omit spaces.'),
        'save-email-template' => L::t('Save the current email for later re-usal in the data-base.
An email template can contain per-member substitutions with the syntax ${MEMBER::VARIABLE},
where VARIABLE is one of VORNAME, NAME, EMAIL, TELEFON_1, TELEFON_2, STRASSE, PLZ, STADT and LAND.
There is also one global (i.e. not per-member) substitution ${GLOABL::ORGANIZER} which is substituted
by the pre-names of the organizing committe in order to compose  greetings.'),
        'emailtest' => L::t('Test the email-settings; try to connect to the SMTP-server and the IMAP-server in turn.'),
        'test-linktarget' => L::t('Try to connect to the specified web-address, will open the web-address in another window or tab, depending your local browser preferences.'),
        'pme-export-csv' => L::t('Export in CSV-format using a semicolon as delimiter (Excel convention)'),
        'pme-export-html' => L::t('Export as HTML page without navigation elements; can also be loaded into your office-programs.'),
        'pme-export-excel' => L::t('Export as `full-featured\' Excel-2007 table, `.xslx\'.'),
        'pme-export-htmlexcel' => L::t('Export as HTML page, but set the file type to `spread-sheed\'. Should also be readable by standard office-programs as `Excel\'-table.'),
        'pme-export-choice' => L::t('Export the visible part of the data-base to an office-format. The `Excel\'-export should produce useful input for either Libre- or OpenOffice or for the product of some well-known software-corporation with seat in Redmond, USA.'),
        'owncloud-attachment' => L::t('Choose a file to attach from the files stored remotely on in the OwnCloud storage area.'),
        'upload-attachment' => L::t('Upload a file from your local computer as attachment. The file will be removed from the remote-system after the message has been sent.'),
        'transfer-instruments' => L::t('Add the instruments of the actually registered musicians to the instrument-table for the project.'),
        'register-musician' => L::t('Add the musician to the project. A new form will open were details like the instrument etc. can be adjustetd.'),
        'pme-transpose' => L::t('Transpose the displayed table; may be beneficial for tables with only a few rows but many columns!'),
        'projectinstrumentation-button' => L::t('Open a page with the musicians registered for the respective project.'),
        'projectevents-button' => L::t('Open a dialog with all known
events associated to the project.
Events can be added and modified
as needed.'),
        'syncevents' => L::t('Recompute the link between projects and events, using the event-categories as primary key.'),
        'projectevents-selectevent' => L::t('Mark the respective event for being
sent by email as ICS-attachment per email.
Hitting the email button above the form
will open an Email form suitable for
sending the marked events to selected
recipients.'),
        'projectevents-newconcert' => L::t('Add a new concert-event to the project.'),
        'projectevents-newrehearsal' => L::t('Add a new rehearsal-event to the project.'),
        'projectevents-newother' => L::t('Add some other event to the project.'),

        'projectevents-newmanagement' => L::t('Add a private management event which is not exposed to the rest of the world.'),

        'projectevents-sendmail' => L::t('Click to open an email-form
and send the selected events to
selected recipients.'),
        'projectevents-select' => L::t('Select all events for
email-submission'),
        'projectevents-deselect' => L::t('Exclude all events from
email-submission'),
        'projectevents-edit' => L::t('Modify the event.'),
        'projectevents-delete' => L::t('Delete the event from the system
(no undo possible).'),
        'projectevents-detach' => L::t('Detach the respective event
from the project, but do not
delete it from the calender.
The event can be reattached by
adding the project-name to its
categories.'),

        'pme-sort' => L::t('  Klick mich, um das Sortieren
nach diesem Feld ein-
oder auszuschalten!'),

        'pme-sort-rvrt' => L::t('  Klick mich, um die Sortierreihenfolge umzukehren!'),

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

        'pme-add' => L::t('  Click me to add a new
row to the current table.'),

        'pme-clear' => array('sfn' => L::t('  Klick mich, um die
Sortierreihenfolge auf die
Voreinstellung zurückzusetzen.'),
                             'sw' => L::t('  Klick mich, um den
aktuellen Filter zu löschen.')),

        'pme-hide' => array('sw' => L::t('  Klick mich, um die
Suchkriterien zu verstecken.')),

        'pme-search' => array('sw' => L::t('  Klick mich, um die
Suchkriterien anzuzeigen.')),
                              
        'pme-query' => L::t('  Klick mich, um die
aktuellen Suchkriterien anzuwenden. Suchkriterien
können in den Feldern eingegeben werden.
Als Platzhalter verwendet man `%%\'.'),

        'pme-filtertext' => L::t('  Feld für Suchkriterien.
Als Platzhalter verwendet man `%%\', z.B. `%%Ste%%an\'
beim Vornamen. Bei numerischen Feldern ist links
eine Auswahlbox mit Vergleichsoperationen.'),
        
        'pme-view-navigation' => array('operation' => L::t('Einzelnen Datensatz anzeigen')),
        'pme-change-navigation' => array('operation' => L::t('Einzelnen Datensatz anzeigen,
zeigt ein neues Formular mit
detaillierten Eingabefeldern
und Abbruchmöglichkeit.')),
        'pme-copy-navigation' => array('operation' => L::t('Einzelnen Datensatz kopieren,
zeigt ein neues Formular mit
detaillierten Eingabefeldern
und Abbruchmöglichkeit.')),
        'pme-delete-navigation' => array('operation' => L::t('Einzelnen Datensatz löschen,
zeigt den aktuellen Datensatz zunächst an.
Gelöscht wird der erst nach einer
weiteren Bestätigung. Trotzdem:
VORSICHT!.')),

        'nothing' => L::t('nothing') // comma stop
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