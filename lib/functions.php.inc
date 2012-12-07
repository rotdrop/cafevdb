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
    CAFEVDB_mySQL::query($query, $handle);

    //specify database
    $dbres = mysql_select_db($opts['db'], $handle);
  
    if (!$dbres) {
      CAFEVDB_Util::error('Unable to select '.$opts['db']);
    }
    return $handle;
  }

  public static function close($handle = false)
  {
    if ($handle) {
      mysql_close($handle);
    }
    // give a damn on errors
    return true;
  }

  public static function query($query, $handle = false, $die = true, $silent = false)
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

  public static function fetch(&$res, $type = MYSQL_ASSOC)
  {
    $result = mysql_fetch_array($res, $type);
    if (CAFEVDB_Util::debugMode()) {
      print_r($result);
    }
    return $result;
  }

  public static function escape($string, $handle = false)
  {
    if ($handle) {
      return mysql_real_escape_string($string, $handle);
    } else {
      return mysql_real_escape_string($string);
    }    
  }

  // Extract set or enum keys
  public static function multiKeys($table, $column, $handle)
  {
    // Build SQL Query  
    $query = "SHOW COLUMNS FROM $table LIKE '$column'";

    // Fetch the result or die
    $result = CAFEVDB_mySQL::query($query, $handle) or die("Couldn't execute query");

    $line = CAFEVDB_mySQL::fetch($result);

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


?>
