<?php

require_once('functions.php.inc');

/**Generate an associative array of extra-fields. The key is the
 * field-name, the value the number of the extra-field in the
 * Besetzungen-table. We fetch and parse the "ExtraFelder"-field from
 * the "Projekte"-table. The following rules apply:
 *
 * - "ExtraFelder" contains a comma-seprarated field list of the form
 *   FIELD1[:NR1] ,     FIELD2[:NR2] etc.
 *
 * - the explicit association in square brackets is optional, if
 *   omitted than NR is the position of the token in the "ExtraFields"
 *   value. Of course, the square-brackets must not be present, they
 *   have the meaning: "hey, this is optional".
 *
 * Note: field names must be unique.
 */
function ProjektExtraFelder($ProjektId, $handle = false)
{
  CAFEVdebugMsg(">>>>ProjektExtraFelder: Id = $ProjektId");

  $query = 'SELECT `ExtraFelder` FROM `Projekte` WHERE `Id` = '.$ProjektId;
  $result = CAFEVmyquery($query, $handle);
  
  // Get the single line
  $line = CAFEVmyfetch($result) or CAFEVerror("Couldn't fetch the result for '".$query."'");
  
  if (CAFEVdebugMode()) {
    print_r($line);
  }
  
  if ($line['ExtraFelder'] == '') {
    return array();
  } else {
    CAFEVdebugMsg("Extras: ".$line['ExtraFelder']);
  }
  
  // Build an array of name - size pairs
  $tmpfields = explode(',',$line['ExtraFelder']);
  if (CAFEVdebugMode()) {
    print_r($tmpfields);
  }
  $fields = array();
  $fieldno = 1; // This time we start at ONE _NOT_ ZERO
  foreach ($tmpfields as $value) {
    $value = trim($value);
    $value = explode(':',$value);
    $fields[] = array('name' => $value[0],
		      'pos' => isset($value[1]) ? $value[1] : $fieldno);
    ++$fieldno;
  }

  CAFEVdebugMsg("<<<<ProjektExtraFelder");

  return $fields;
}

function fetchProjects($handle = false)
{
  $query = 'SELECT `Name` FROM `Projekte` WHERE 1';
  $result = CAFEVmyquery($query, $handle);

  $projects = array();
  while ($line = CAFEVmyfetch($result)) {
    $projects[] = $line['Name'];
  }

  return $projects;
}

function ProjektFetchName($ProjektId, $handle = false)
{
  $query = 'SELECT `Name` FROM `Projekte` WHERE `Id` = '.$ProjektId;
  $result = CAFEVmyquery($query, $handle);

  // Get the single line
  $line = CAFEVmyfetch($result) or CAFEVerror("Couldn't fetch the result for '".$query."'");

  return $line['Name'];
}

/**Make sure the "Besetzungen"-table has enough extra fields. All
 * extra-fields are text-fields.
 *
 */
function ProjektCreateExtraFelder($ProjektId, $handle = false)
{
  CAFEVdebugMsg(">>>> ProjektCreateExtraFelder");

  // Fetch the extra-fields.
  $extra = ProjektExtraFelder($ProjektId, $handle);
  if (CAFEVdebugMode()) {
    print_r($extra);
  }

  /* Then walk the table and simply execute one "ALTER TABLE"
   * statement for each field, ignoring the result, but we check later
   * for a possible error.
   */

  foreach ($extra as $field) {
    // forget about $name, not an issue here.  

    $query = sprintf(
'ALTER TABLE `Besetzungen`
   ADD `ExtraFeld%02d` TEXT
   CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL',
$field['pos']);
    $result = @CAFEVmyquery($query, $handle, false, true); // ignore the result, be silent
  }

  // Now make sure we have it ...
  $query = "SHOW COLUMNS FROM `Besetzungen` LIKE 'ExtraFeld%'";
  $result = CAFEVmyquery($query, $handle);

  // See what we got ...
  $fields = array();
  while ($row = CAFEVmyfetch($result)) {
    if (CAFEVdebugMode()) {
      print_r($row);
    }
    $fields[] = $row['Field'];
  }
  if (CAFEVdebugMode()) {
    print_r($fields);
  }

  foreach ($extra as $field) {
    $name = sprintf('ExtraFeld%02d', $field['pos']);
    CAFEVdebugMsg("Check ".$name);
    if (array_search($name, $fields) === false) {
      CAFEVerror('Extra-Field '.$field['pos'].' not Found in Table Besetzungen');
    }
  }

  CAFEVdebugMsg("<<<< ProjektCreateExtraFelder");

  return true; // if someone cares
}

// Create a sensibly sorted view, fit for being exported via
// phpmyadmin. Take all extra-fields into account, add them at end.
function ProjektCreateView($ProjektId, $Projekt = false, $handle = false)
{
  CAFEVdebugMsg(">>>> ProjektCreateView");

  if (! $Projekt) {
    // Get the name
    $Projekt = ProjektFetchName($ProjektId, $handle);
  }

  // Make sure all extra-fields exist
  ProjektCreateExtraFelder($ProjektId, $handle);

  // Fetch the extra-fields
  $extra = ProjektExtraFelder($ProjektId, $handle);

  // "Extra"'s will be added at end. Generate a suitable "SELECT"
  // string for that. Ordering of field in the table is just the
  // ordering in the "$extra" table.
  $extraquery = '';
  CAFEVdebugMsg(">>>> ProjektCreateView before extra");
  foreach ($extra as $field) {
    $extraquery .= sprintf(', `Besetzungen`.`ExtraFeld%02d` AS `'.$field['name'].'`', $field['pos']);
  }
  CAFEVdebugMsg(">>>> ProjektCreateView after extra");

  // Now do all the stuff, do not forget the proper sorting to satisfy
  // all dummies on earth
  $sqlquery = 'CREATE OR REPLACE VIEW `'.$Projekt.'View` AS
 SELECT
   `Musiker`.`Id` AS `MusikerId`,
   `Besetzungen`.`Instrument`,`Besetzungen`.`Reihung`,
   `Besetzungen`.`Stimmführer`,`Instrumente`.`Familie`,`Instrumente`.`Sortierung`,
    `Name`,`Vorname`,
   `Email`,`Telefon`,`Telefon2`,
   `Strasse`,`Postleitzahl`,`Stadt`,`Land`,
   `Besetzungen`.`Unkostenbeitrag`,
   `Besetzungen`.`Bemerkungen` AS `ProjektBemerkungen`'.
    ($extraquery != '' ? $extraquery : '').','
.' `Instrumente` AS `AlleInstrumente`,`Sprachpräferenz`,`Geburtstag`,
   `Status`,`Musiker`.`Bemerkung`,`Aktualisiert`';

  // Now do the join
  $sqlquery .= ' FROM `Musiker`
   JOIN `Besetzungen`
     ON `Musiker`.`Id` = MusikerId AND '.$ProjektId.'= `ProjektId`
   LEFT JOIN `Instrumente`
     ON `Besetzungen`.`Instrument` = `Instrumente`.`Instrument`';

  // And finally force a sensible default sorting:
  // 1: sort on the natural orchestral ordering defined in Instrumente
  // 2: sort (reverse) on the Stimmfuehrer attribute
  // 3: sort on the sur-name
  // 4: sort on the pre-name
  $sqlquery .= 'ORDER BY `Instrumente`.`Sortierung` ASC,
 `Besetzungen`.`Reihung` ASC,
 `Besetzungen`.`Stimmführer` DESC,
 `Musiker`.`Name` ASC,
 `Musiker`.`Vorname` ASC';
 
  CAFEVmyquery($sqlquery, $handle);

  return true;
}

?>