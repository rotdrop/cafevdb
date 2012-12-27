<?php

namespace CAFEVDB
{

/** Tool-tips for the phpmyadmin-forms.
 *
 */
class Tooltips
{
  static function pmeToolTips()
  {
    return 
    array(
      'transfer-instruments' => 'Add the instruments of the actually registered musicians to the instrument-table for the project.',
      'register-musician' => 'Add the musician to the project. A new form will open were details like the instrument etc. can be adjustetd.',
      'pme-transpose' => 'Transpose the displayed table; may be beneficial for tables with only a few rows but many columns!',
      'projectinstrumentation-button' => 'Open a page with the musicians registered for the respective project.',
      'projectevents-button' => 'Open a dialog with all known
events associated to the project.
Events can be added and modified
as needed.',
      'syncevents' => 'Recompute the link between projects and events, using the event-categories as primary key.',
      'projectevents-selectevent' => 'Mark the respective event for being
sent by email as ICS-attachment per email.
Hitting the email button above the form
will open an Email form suitable for
sending the marked events to selected
recipients.',
      'projectevents-newconcert' => 'Add a new concert-event to the project.',
      'projectevents-newrehearsal' => 'Add a new rehearsal-event to the project.',
      'projectevents-newother' => 'Add some other event to the project.',

      'projectevents-newmanagement' => 'Add a private management event which is not exposed to the rest of the world.',

      'projectevents-sendmail' => 'Click to open an email-form
and send the selected events to
selected recipients.',
      'projectevents-select' => 'Select all events for
email-submission',
      'projectevents-deselect' => 'Exclude all events from
email-submission',
      'projectevents-edit' => 'Modify the event.',
      'projectevents-delete' => 'Delete the event from the system
(no undo possible).',
    'projectevents-detach' => 'Detach the respective event
from the project, but do not
delete it from the calender.
The event can be reattached by
adding the project-name to its
categories.',

      'pme-sort' => '  Klick mich, um das Sortieren
nach diesem Feld ein-
oder auszuschalten!',

      'pme-sort-rvrt' => '  Klick mich, um die Sortierreihenfolge umzukehren!',

      'pme-misc' => '  Klick mich, um eine Em@il an die ausgewählten
Musiker zu versenden. Auf der folgenden Seite kann
die Auswahl dann noch modifiziert werden.
"angezeigt" bedeutet: nicht
nur die auf der aktuellen
Anzeige-Seite, sondern
alle, die den Such-Kriterien
entsprechen.',

      'pme-misc+' => '  Klick mich, um alle gerade
angezeigten Musiker zu der
Em@il-Auswahl hinzuzufügen.
"angezeigt" bedeutet: nicht
nur die auf der aktuellen
Anzeige-Seite, sondern
alle, die den Such-Kriterien
entsprechen.',

      'pme-misc-' => '  Klick mich, um alle gerade
angezeigten Musiker von der
Em@il-Auswahl zu entfernen',

      'pme-clear' => array('sfn' => '  Klick mich, um die
Sortierreihenfolge auf die
Voreinstellung zurückzusetzen.',
                           'sw' => '  Klick mich, um den
aktuellen Filter zu löschen.'),

      'pme-hide' => array('sw' => '  Klick mich, um die
Suchkriterien zu verstecken.'),

      'pme-search' => array('sw' => '  Klick mich, um die
Suchkriterien anzuzeigen.'),

      'pme-query' => '  Klick mich, um die
aktuellen Suchkriterien anzuwenden. Suchkriterien
können in den Feldern eingegeben werden.
Als Platzhalter verwendet man "%".',

      'pme-filtertext' => '  Feld für Suchkriterien.
Als Platzhalter verwendet man "%", z.B. "%Ste%an"
beim Vornamen. Bei numerischen Feldern ist links
eine Auswahlbox mit Vergleichsoperationen.',

      'pme-misc-check' => '  Click mich, um mich
Deinem Massenmail-Vorhaben
hinzuzufügen. Schäme Dich!',

      'pme-view-navigation' => array('operation' => 'Einzelnen Datensatz anzeigen'),
      'pme-change-navigation' => array('operation' => 'Einzelnen Datensatz anzeigen,
zeigt ein neues Formular mit
detaillierten Eingabefeldern
und Abbruchmöglichkeit.'),
      'pme-copy-navigation' => array('operation' => 'Einzelnen Datensatz kopieren,
zeigt ein neues Formular mit
detaillierten Eingabefeldern
und Abbruchmöglichkeit.'),
      'pme-delete-navigation' => array('operation' =>'Einzelnen Datensatz löschen,
zeigt den aktuellen Datensatz zunächst an.
Gelöscht wird der erst nach einer
weiteren Bestätigung. Trotzdem:
VORSICHT!.'),
      'pme-misc-check' => '  Adressaten in potentielle
Massenmail Adressliste aufnehmen.
Kann man (glücjlicherweise) 
vor dem Senden der Em@il noch
korrigieren. Trotzdem:
Think thrice about it.',
        

      'nothing' => 'nothing' // comma stop
    );
  }
}; // class toolTips

}

/*
 * Local Variables: ***
 * c-basic-offset: 2 ***
 * End: ***
 */

?>