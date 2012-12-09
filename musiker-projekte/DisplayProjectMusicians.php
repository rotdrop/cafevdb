<?php

  echo "<h3>Besetzung Projekt $Projekt.</h3>";

  echo <<<__EOT__
<H4><ul>
<li><span style="color:red">Musiker entfernen:</span>
<span style="font-style:italic">"Short Display for $Projekt"</span>
<li><span style="color:red">Projekt-Daten</span>
<span style="font-style:italic">"Short Display for $Projekt"</span>
(Projekt-Instrument, Stimmführer, Projekt-Bemerkungen etc.)
<li><span style="color:red">Personen-Daten</span>
<span style="font-style:italic">diese Tabelle</span>
(Adresse, Email, Name etc.)
</ul>
</H4>
__EOT__;

  $ROopts = 'CLFPVR'; // read-only options for all project specific fields.

  /*
   * IMPORTANT NOTE: This generated file contains only a subset of huge amount
   * of options that can be used with phpMyEdit. To get information about all
   * features offered by phpMyEdit, check official documentation. It is available
   * online and also for download on phpMyEdit project management page:
   *
   * http://platon.sk/projects/main_page.php?project_id=5
   *
   * This file was generated by:
   *
   *                    phpMyEdit version: 5.7.1
   *       phpMyEdit.class.php core class: 1.204
   *            phpMyEditSetup.php script: 1.50
   *              generating setup script: 1.50
   */

  // MySQL host name, user name, password, database, and table
  include('config.php.inc');

  $opts['inc'] = $RecordsPerPage;

  $opts['tb'] = $Projekt . 'View';

  $opts['cgi']['persist'] = array('Projekt' => $Projekt,
                                  'ProjektId' => $ProjektId,
                                  'Action' => $CAFEV_action,
                                  'Table' => $opts['tb']);

  // Name of field which is the unique key
  $opts['key'] = 'MusikerId';

  // Type of key field (int/real/string/date etc.)
  $opts['key_type'] = 'int';

  // Sorting field(s)
  $opts['sort_field'] = array('Sortierung','Reihung','-Stimmführer','Name','Vorname');

  // Options you wish to give the users
  // A - add,  C - change, P - copy, V - view, D - delete,
  // F - filter, I - initial sort suppressed
  // This is a view, undeletable.
  $opts['options'] = 'CPVFM';

  // Number of lines to display on multiple selection filters
  $opts['multiple'] = '6';

  // Navigation style: B - buttons (default), T - text links, G - graphic links
  // Buttons position: U - up, D - down (default)
  //$opts['navigation'] = 'DB';

  // Display special page elements
  $opts['display'] = array(
                           'form'  => true,
                           'query' => true,
                           'sort'  => true,
                           'time'  => true,
                           'tabs'  => true
                           );

  // Set default prefixes for variables
  $opts['js']['prefix']               = 'PME_js_';
  $opts['dhtml']['prefix']            = 'PME_dhtml_';
  $opts['cgi']['prefix']['operation'] = 'PME_op_';
  $opts['cgi']['prefix']['sys']       = 'PME_sys_';
  $opts['cgi']['prefix']['data']      = 'PME_data_';

  //$opts['cgi']['append']['PME_sys_fl'] = 1;

  /* Get the user's default language and use it if possible or you can
     specify particular one you want to use. Refer to official documentation
     for list of available languages. */
//  $opts['language'] = $_SERVER['HTTP_ACCEPT_LANGUAGE'] . '-UTF8';

  /* Table-level filter capability. If set, it is included in the WHERE clause
     of any generated SELECT statement in SQL query. This gives you ability to
     work only with subset of data from table.

     $opts['filters'] = "column1 like '%11%' AND column2<17";
     $opts['filters'] = "section_id = 9";
     $opts['filters'] = "PMEtable0.sessions_count > 200";
  */

  /* Field definitions
   
     Fields will be displayed left to right on the screen in the order in which they
     appear in generated list. Here are some most used field options documented.

     ['name'] is the title used for column headings, etc.;
     ['maxlen'] maximum length to display add/edit/search input boxes
     ['trimlen'] maximum length of string content to display in row listing
     ['width'] is an optional display width specification for the column
     e.g.  ['width'] = '100px';
     ['mask'] a string that is used by sprintf() to format field output
     ['sort'] true or false; means the users may sort the display on this column
     ['strip_tags'] true or false; whether to strip tags from content
     ['nowrap'] true or false; whether this field should get a NOWRAP
     ['select'] T - text, N - numeric, D - drop-down, M - multiple selection
     ['options'] optional parameter to control whether a field is displayed
     L - list, F - filter, A - add, C - change, P - copy, D - delete, V - view
     Another flags are:
     R - indicates that a field is read only
     W - indicates that a field is a password field
     H - indicates that a field is to be hidden and marked as hidden
     ['URL'] is used to make a field 'clickable' in the display
     e.g.: 'mailto:$value', 'http://$value' or '$page?stuff';
     ['URLtarget']  HTML target link specification (for example: _blank)
     ['textarea']['rows'] and/or ['textarea']['cols']
     specifies a textarea is to be used to give multi-line input
     e.g. ['textarea']['rows'] = 5; ['textarea']['cols'] = 10
     ['values'] restricts user input to the specified constants,
     e.g. ['values'] = array('A','B','C') or ['values'] = range(1,99)
     ['values']['table'] and ['values']['column'] restricts user input
     to the values found in the specified column of another table
     ['values']['description'] = 'desc_column'
     The optional ['values']['description'] field allows the value(s) displayed
     to the user to be different to those in the ['values']['column'] field.
     This is useful for giving more meaning to column values. Multiple
     descriptions fields are also possible. Check documentation for this.
  */

  $opts['fdd']['Instrument'] = array(
                                     'name'     => 'Projekt-Instrument',
                                     'select'   => 'T',
                                     'maxlen'   => 36,
                                     'sort'     => true
                                     );
  $opts['fdd']['Instrument']['values'] = $Instrumente;
  $opts['fdd']['Instrument']['options'] = $ROopts;
  $opts['fdd']['Reihung'] = array('name' => 'Stimme',
                                  'options' => $ROopts,
				  'select' => 'N',
				  'maxlen' => '3',
				  'sort' => true);
  $opts['fdd']['Familie'] = array('name' => 'Intrumenten Familie',
				  'nowrap' => true,
				  'select' => 'M',
				  'maxlen' => 64,
				  'options'  => $ROopts,
				  'sort' => 'true');
  $opts['fdd']['Familie']['values'] = $InstrumentenFamilie;
  $opts['fdd']['Stimmführer'] = array('name' => ' &alpha;',
				      'options'  => $ROopts,
				      'select' => 'T',
				      'maxlen' => '3',
				      'escape' => false,
				      'sort' => true);
  $opts['fdd']['Stimmführer']['values2'] = array('0' => '', '1' => '&alpha;');
  $opts['fdd']['Sortierung'] = array('name'     => 'Orchester Sortierung',
				     'select'   => 'T',
				     'options'  => 'VCPR',
				     'maxlen'   => 8,
				     'default'  => '0',
				     'sort'     => true);
  $opts['fdd']['AlleInstrumente'] = array(
                                      'name'     => 'Alle Instrumente',
				      'options'  => 'AVCPD',
                                      'select'   => 'C',
                                      'maxlen'   => 136,
                                      'sort'     => true
                                      );
  $opts['fdd']['AlleInstrumente']['values'] = $Instrumente;


  $opts['fdd']['Name'] = array(
                               'name'     => 'Name',
                               'select'   => 'T',
                               'maxlen'   => 384,
                               'sort'     => true
                               );
  $opts['fdd']['Vorname'] = array(
                                  'name'     => 'Vorname',
                                  'select'   => 'T',
                                  'maxlen'   => 384,
                                  'sort'     => true
                                  );
  $opts['fdd']['Email'] = $emailopts;
  $opts['fdd']['Telefon'] = array(
                                  'name'     => 'Telefon',
				  'nowrap' => true,
                                  'select'   => 'T',
                                  'maxlen'   => 384,
                                  'sort'     => true
                                  );
  $opts['fdd']['Telefon2'] = array(
                                   'name'     => 'Telefon2',
				   'nowrap' => true,
                                   'select'   => 'T',
                                   'maxlen'   => 384,
                                   'sort'     => true
                                   );
  $opts['fdd']['Strasse'] = array(
                                  'name'     => 'Strasse',
				  'nowrap' => true,
                                  'select'   => 'T',
                                  'maxlen'   => 384,
                                  'sort'     => true
                                  );
  $opts['fdd']['Postleitzahl'] = array(
                                       'name'     => 'Postleitzahl',
                                       'select'   => 'T',
                                       'maxlen'   => 11,
                                       'sort'     => true
                                       );
  $opts['fdd']['Stadt'] = array(
                                'name'     => 'Stadt',
                                'select'   => 'T',
                                'maxlen'   => 384,
                                'sort'     => true
                                );
  $opts['fdd']['Land'] = array('name'     => 'Land',
                               'select'   => 'T',
                               'maxlen'   => 384,
                               'default'  => 'Deutschland',
                               'sort'     => true);
  $opts['fdd']['ProjektBemerkungen'] = array('name'     =>  'Bemerkungen ('.$Projekt.')',
					     'select'   => 'T',
                                             'options' => $ROopts,
					     'maxlen'   => 65535,
                                             'css'      => array('postfix' => 'rem'),
					     'textarea' => array('html' => 'Editor',
								 'rows' => 5,
								 'cols' => 50),
					     'escape' => false,
					     'sort'     => true
					     );
  $opts['fdd']['Unkostenbeitrag'] = $moneyopts;
  $opts['fdd']['Unkostenbeitrag']['name'] = "Unkostenbeitrag\n(Gagen negativ)";
  $opts['fdd']['Unkostenbeitrag']['options'] = $ROopts;


  // Generate input fields for the extra columns
  foreach ($UserExtraFields as $field) {
    $name = $field['name'];    
    $opts['fdd']["$name"] = array('name' => $name.' ('.$Projekt.')',
				  'select'   => 'T',
                                  'options' => $ROopts,
				  'maxlen'   => 65535,
				  'textarea' => array('html' => 'NoEditor',
						      'rows' => 2,
						      'cols' => 32),
				  'escape' => false,
				  'sort'     => true);
  }

  $opts['fdd']['Sprachpräferenz'] = array('name'     => 'Spachpräferenz',
                                          'select'   => 'T',
                                          'maxlen'   => 128,
                                          'default'  => 'Deutsch',
                                          'sort'     => true,
                                          'values'   => $Languages);

  $opts['fdd']['Geburtstag'] = $globopts['fdd']['Geburtstag'];
  $opts['fdd']['Status'] = array(
                                 'name'     => 'Status',
                                 'select'   => 'T',
                                 'css'      => array('postfix' => 'rem'),
                                 'maxlen'   => 384,
                                 'sort'     => true
                                 );
  $opts['fdd']['Bemerkung'] = array(
                                    'name'     => 'Allgemeine Bemerkungen',
                                    'select'   => 'T',
                                    'maxlen'   => 65535,
                                    'css'      => array('postfix' => 'rem'),
                                    'textarea' => array('html' => 'Editor',
                                                        'rows' => 5,
                                                        'cols' => 50),
                                    'escape' => false,
                                    'sort'     => true
                                    );

  $opts['fdd']['MusikerId'] = array(
                             'name'     => 'MusikerId',
                             'select'   => 'T',
                             'options'  => 'LAVCPDR', // auto increment
                             'maxlen'   => 11,
                             'default'  => '0',
                             'sort'     => true
                             );

  $opts['fdd']['Aktualisiert'] = $calopts;
  $opts['fdd']['Aktualisiert']['name'] = 'Aktualisiert';
  $opts['fdd']['Aktualisiert']['default'] = date("Y-m-d H:i:s");
  $opts['fdd']['Aktualisiert']['nowrap'] = true;
  $opts['fdd']['Aktualisiert']['options'] = 'LAVCPDR'; // Set by update trigger.

// No need to check for the project-instrument any longer, as it can
//no longer be changed here.
//$opts['triggers']['update']['before'][0]  = 'BesetzungChangeInstrument.TUB.inc.php';
  $opts['triggers']['update']['before'][1]  = 'RemoveUnchanged.TUB.php.inc';
  $opts['triggers']['update']['before'][2]  = 'UpdateProjectMusicianTimestamp.TUB.php.inc';

  // Now important call to phpMyEdit
  //require_once 'phpMyEdit.class.php';
  //new phpMyEdit($opts);
  //require_once 'extensions/phpMyEdit-mce-cal.class.php';
  //new phpMyEdit_mce_cal($opts);
  require_once 'pme/phpMyEdit.class.php';
  new phpMyEdit($opts);

?>






