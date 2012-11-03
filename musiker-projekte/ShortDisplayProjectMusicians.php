<?php  

  echo "<h3>Besetzung Projekt $Projekt</h3>
<H4>Achtung: Musiker k&ouml;nnen hier entfernt werden, auch das Projekt-Instrument kann man &auml;ndern.
 Allerdings kann man Musiker <B>nicht</B> in andere Projekte verschieben. Um Daten (Tel. etc.) zu &auml;ndern, mu&szlig;
 man die detaillierte Ansicht ausw&auml;hlen (oder die Gesamt&uuml;bersicht &uuml;ber alle Musiker). Die Besetzungszahlen kann mit &uuml;ber den \"Instrumentation\"-Button oben ansehen (Ist-Besetzung) bzw. &auml;ndern (Soll-Besetzung).</H4>";

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
  include('config.php.inc');

  $opts['tb'] = 'Besetzungen';

  // Number of records to display on the screen
  // Value of -1 lists all records in a table
  $opts['inc'] = $RecordsPerPage;

  $opts['cgi']['persist'] = array('Projekt' => $Projekt,
                                  'ProjektId' => $ProjektId,
                                  'Action' => $CAFEV_action,
                                  'Table' => $opts['tb']);

  // Name of field which is the unique key
  $opts['key'] = 'Id';

  // Type of key field (int/real/string/date etc.)
  $opts['key_type'] = 'int';

  // Sorting field(s)
  $opts['sort_field'] = array('Sortierung','Reihung','-Stimmführer','MusikerId');

  // Options you wish to give the users
  // A - add,  C - change, P - copy, V - view, D - delete,
  // F - filter, I - initial sort suppressed
  $opts['options'] = 'ACPVDFM';

  // Number of lines to display on multiple selection filters
  $opts['multiple'] = '4';

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

  $opts['filters'] = "ProjektId = $ProjektId";

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

  $opts['fdd']['Id'] = array(
                             'name'     => 'Id',
                             'select'   => 'T',
                             'options'  => 'AVCPDR', // auto increment
                             'maxlen'   => 11,
                             'default'  => '0',
                             'sort'     => true
                             );
  $opts['fdd']['ProjektId'] = array(
                                    'name'     => 'ProjektId',
                                    'select'   => 'T',
                                    'options'  => 'AVCPDR', // auto increment
                                    'maxlen'   => 11,
                                    'sort'     => true,
                                    'values' => array(
                                                      'table' => 'Projekte',
                                                      'column' => 'Id',
                                                      'description' => 'Name',
                                                      'filters' => "Id = $ProjektId"
                                                      )
                                    );
  $opts['fdd']['MusikerId'] = array(
                                    'name'     => 'MusikerId',
                                    'select'   => 'T',
                                    'maxlen'   => 11,
                                    'sort'     => true,
                                    //'options'  => 'LFADV', // no change allowed
                                    'values' => array('table' => 'Musiker',
                                                      'column' => 'Id',
                                                      'description' => array('columns' => array('Name', 'Vorname'),
									     'divs' => array(', ')
							))
                                    );
  $opts['fdd']['Instrument'] = array('name'     => 'Instrument',
                                     'select'   => 'T',
                                     'maxlen'   => 12,
				     'values'   => array('table'   => 'Instrumente',
							 'column'  => 'Instrument',
							 'orderby' => '$table.Sortierung',
							 'description' => array('columns' => array('Instrument'))),
                                     'sort'     => true
                                     );
  //$opts['fdd']['Instrument']['values'] = $Instrumente;
  $opts['fdd']['Sortierung'] = array('name' => 'Orchester-Sortierung',
				     'select' => 'T',
				     'options' => 'VCPR',
				     'input' => 'V',
				     'sql' => '`PMEjoin3`.`Sortierung`',
				     'sort' => true);
  $opts['fdd']['Reihung'] = array('name' => 'Stimme',
				  'select' => 'N',
				  'maxlen' => '3',
				  'sort' => true);
  $opts['fdd']['Stimmführer'] = array('name' => ' &alpha;',
				      'options'  => 'LAVCPD',
				      'select' => 'T',
				      'maxlen' => '3',
				      'sort' => true,
				      'escape' => false);
  $opts['fdd']['Stimmführer']['values2'] = array('0' => ' ', '1' => '&alpha;');
  $opts['fdd']['Bemerkungen'] = array('name'     => 'Bemerkungen',
				      'select'   => 'T',
				      'maxlen'   => 65535,
 'css'      => array('postfix' => 'rem'),
				      'textarea' => array('html' => 'Editor',
							  'rows' => 5,
							  'cols' => 50),
				      'escape' => false,
				      'sort'     => true);
  $opts['fdd']['Unkostenbeitrag'] = $moneyopts;
  $opts['fdd']['Unkostenbeitrag']['name'] = "Unkostenbeitrag\n(Gagen negativ)";

  // Generate input fields for the extra columns
  foreach ($UserExtraFields as $field) {
    $name = sprintf('ExtraFeld%02d', $field['pos']);
    
    $opts['fdd']["$name"] = array('name' => $field['name'],
				  'select'   => 'T',
				  'maxlen'   => 65535,
				  'textarea' => array('html' => 'NoEditor',
						      'rows' => 2,
						      'cols' => 32),
				  'escape' => false,
				  'sort'     => true);
  }

  // Check whether the instrument is also mentioned in the musicians
  // data-base. Otherwise add id on request.
  $opts['triggers']['insert']['before']  = 'BesetzungFixProject.TIB.inc.php';
  $opts['triggers']['update']['before']  = 'BesetzungChangeInstrument.TUB.inc.php';

  // Now important call to phpMyEdit
  //require_once 'phpMyEdit.class.php';
  //new phpMyEdit($opts);
  //require_once 'extensions/phpMyEdit-mce-cal.class.php';
  //new phpMyEdit_mce_cal($opts);

  require_once 'pme/phpMyEdit.class.php';
  new phpMyEdit($opts);

?>