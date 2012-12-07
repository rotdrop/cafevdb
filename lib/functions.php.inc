<?php

class CAFEVDB_Util
{

  public static function debugMode()
  {
    if (CAFEVDB_Config::$debug_query) {
      return true;
    } else {
      return false;
    }
  }

  public static function error($msg, $die = true)
  {
    $msg = '<HR/><PRE>
'.htmlspecialchars($msg).
      '</PRE><HR/>
';
    if ($die) {
      die($msg);
    } else {
      echo $msg;
    }
  }

  public static function debugMsg($msg)
  {
    if (CAFEVDB_Util::debugMode()) {
      CAFEVDB_Util::error($msg, false);
    }
  }

  public static function redirect($page, $proto = NULL, $host = NULL, $port = NULL, $uri = NULL) {
  
    /* Redirect auf eine andere Seite im aktuell angeforderten Verzeichnis */
    if (!$proto) {
      if(isset($_SERVER['HTTPS'])) {
        $proto = 'https';
      } else {
        $proto = 'http';
      }
    }
    if (!$host) {
      $host  = $_SERVER['HTTP_HOST'];
    }
    if (!$port) {
      if (isset($_SERVER['SERVER_PORT'])) {
        $port = $_SERVER['SERVER_PORT'];
      }
      if (($proto == 'http' && $port == 80) ||
          ($proto == 'https' && $port == 443)) {
        $port = '';
      }
    }
    if ($port) {
      $port = ':'.$port;
    }
    if (!$uri) {
      $uri   = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
    }
    $redirect = "Location: $proto://$host$port$uri/$page";
    if (CAFEVDB_Util::debugMode()) {
      echo '<PRE>';
      print_r($_SERVER);
      echo '</PRE><HR/>';
      CAFEVDB_Util::error('Redirect attempt to "'.$redirect.'"');
    } else {
      header($redirect);
    }
    exit;
  }

  public static function cgiValue($key, $default=NULL)
  {
    if (isset($_POST["$key"])) {
      return $_POST["$key"];
    } elseif (isset($_GET["$key"])) {
      return $_GET["$key"];
    } elseif (isset(CAFEVDB_Config::$cgiVars["$key"])) {
      return CAFEVDB_Config::$cgiVars["$key"];
    } else {
      return $default;
    }
  }

  public static function disableEnterSubmit()
  {
    echo '<script type="text/javascript">
public static function stopRKey(evt) {
  var evt = (evt) ? evt : ((event) ? event : null);
  var node = (evt.target) ? evt.target : ((evt.srcElement) ? evt.srcElement : null);
  if ((evt.keyCode == 13) && (node.type=="text"))  {return false;}
}

document.onkeypress = stopRKey;
</script>';
  }
};

class CAFEVDB_mySQL
{
  public static function myconnect($opts, $die = true)
  {
    // Fetch the actual list of instruments, we will need it anyway
    $handle = mysql_connect($opts['hn'], $opts['un'], $opts['pw']);
    if ($handle === false) {
      CAFEVDB_Util::error('Could not connect to data-base server: "'.@mysql_error().'"');
    }

    // Fucking shit
    $query = "SET NAMES 'utf8'";
    CAFEVDB_Util::myquery($query, $handle);

    //specify database
    $dbres = mysql_select_db($opts['db'], $handle);
  
    if (!$dbres) {
      CAFEVDB_Util::error('Unable to select '.$opts['db']);
    }
    return $handle;
  }

  public static function myclose($handle = false)
  {
    if ($handle) {
      mysql_close($handle);
    }
    // give a damn on errors
    return true;
  }

  public static function myquery($query, $handle = false, $die = true, $silent = false)
  {
    if (CAFEVDB_Util::debugMode()) {
      echo '<HR/><PRE>'.htmlspecialchars($query).'</PRE><HR/><BR>';
    }
    if ($handle) {
      if (!($result = @mysql_query($query, $handle))) {
        $err = @mysql_error($handle);
      }
    } else {
      if (!($result = mysql_query($query))) {
        $err = @mysql_error();
      }
    }
    if (!$result && (!$silent || $die)) {
      CAFEVDB_Util::error('mysql_query() failed: "'.$err.'"', $die);
    }
    return $result;
  }

  public static function myfetch(&$res, $type = MYSQL_ASSOC)
  {
    $result = mysql_fetch_array($res, $type);
    if (CAFEVDB_Util::debugMode()) {
      print_r($result);
    }
    return $result;
  }

  public static function myescape($string, $handle = false)
  {
    if ($handle) {
      return mysql_real_escape_string($string, $handle);
    } else {
      return mysql_real_escape_string($string);
    }    
  }

  // Extract set or enum keys
  public static function sqlMultikeys($table, $column, $handle)
  {
    // Build SQL Query  
    $query = "SHOW COLUMNS FROM $table LIKE '$column'";

    // Fetch the result or die
    $result = CAFEVDB_Util::myquery($query, $handle) or die("Couldn't execute query");

    $line = CAFEVDB_Util::myfetch($result);

    $set = $line['Type'];

    if (strcasecmp(substr($set,0,3),'set') == 0) {
      $settype = 'set';
    } elseif (strcasecmp(substr($set,0,4),'enum') == 0) {
      $settype = 'enum';
    } else {
      return null;
    }

    $set = substr($set,strlen($settype)+2,strlen($set)-strlen($settype)-strlen("();")-1); // Remove "set(" at start and ");" at end

    return preg_split("/','/",$set); // Split into an array
  }
};

/** Helper functions for email.
 */
class CAFEVDB_Email
{
  /** Split a comma separated address list into an array.
   */
  public static function parseAddrListToArray($list)
  {
    $t = str_getcsv($list);

    foreach($t as $k => $v) {
      if (strpos($v,',') !== false) {
        $t[$k] = '"'.str_replace(' <','" <',$v);
      }
    }

    foreach ($t as $addr) {
      if (strpos($addr, '<')) {
        preg_match('!(.*?)\s?<\s*(.*?)\s*>!', $addr, $matches);
        $emails[] = array(
                          'email' => $matches[2],
                          'name' => $matches[1]
                          );
      } else {
        $emails[] = array(
                          'email' => $addr,
                          'name' => ''
                          );
      }
    }

    return $emails;
  }

  /** Issue an error message.
   */
  public static function echoInvalid($kind, $email)
  {
    echo '<HR/><H4>The '.$kind.' address "'.$email.'" seems to be invalid.
<p>
Please correct that first and then click on the "Send"-button again.
<P>
Unfortunately, attachments (if any) have to be specified again.
</H4>';
  }

  public static function addAddress($phpmailer, $address, $name = '')
  {
    if (!$phpmailer->AddAddress($address, $name)) {
      EmailEchoInvalid('recipient', $address);
      return false;
    }
    return true;
  }

  public static function addCC($phpmailer, $address, $name = '')
  {
    if (!$phpmailer->AddCC($address, $name)) {
      EmailEchoInvalid('"Cc:"', $address);
      return false;
    }
    return true;
  }

  public static function addBCC($phpmailer, $address, $name = '')
  {
    if (!$phpmailer->AddBCC($address, $name)) {
      EmailEchoInvalid('"Bcc:"', $address);
      return false;
    }
    return true;
  }

  public static function setFrom($phpmailer, $address, $name = '')
  {
    if ($phpmailer->SetFrom($address, $name) != true) {
      EmailEchoInvalid('"From:"', $address);
      return false;
    }
    return true;
  }

  public static function addReplyTo($phpmailer, $address, $name = '')
  {
    if ($phpmailer->AddReplyTo($address, $name) != true) {
      EmailEchoInvalid('"ReplyTo:"', $address);
      return false;
    }
    return true;
  }

  // Maybe not needed.
  public static function callback($isSent, $to, $cc, $bcc, $subject, $body)
  {


  }
}; // CAFEVDB_Email

/** Helper class for displaying projects.
 */
class CAFEVDB_Projects
{
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
  public static function extraFields($ProjektId, $handle = false)
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

  public static function fetchName($ProjektId, $handle = false)
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
  public static function createExtraFields($ProjektId, $handle = false)
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
  public static function createView($ProjektId, $Projekt = false, $handle = false)
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

}; // class CAFEVDB_Projects

?>
