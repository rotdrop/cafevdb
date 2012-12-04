<?php

function CAFEVdebugMode()
{
  global $debug_query;

  if (isset($debug_query) && $debug_query) {
    return true;
  } else {
    return false;
  }
}

function CAFEVerror($msg, $die = true)
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

function CAFEVdebugMsg($msg)
{
  if (CAFEVdebugMode()) {
    CAFEVerror($msg, false);
  }
}

function CAFEVredirect($page, $proto = NULL, $host = NULL, $port = NULL, $uri = NULL) {
  
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
  if (CAFEVdebugMode()) {
    echo '<PRE>';
    print_r($_SERVER);
    echo '</PRE><HR/>';
    CAFEVerror('Redirect attempt to "'.$redirect.'"');
  } else {
    header($redirect);
  }
  exit;
}

function CAFEVcgiValue($key, $default=NULL)
{
  global $CAFEVcgiVars; // key => default array

  if (isset($_POST["$key"])) {
    return $_POST["$key"];
  } elseif (isset($_GET["$key"])) {
    return $_GET["$key"];
  } elseif (isset($CAFEVcgiVars["$key"])) {
    return $CAFEVcgiVars["$key"];
  } else {
    return $default;
  }
}

function CAFEVmyconnect($opts, $die = true)
{
  // Fetch the actual list of instruments, we will need it anyway
  $handle = mysql_connect($opts['hn'], $opts['un'], $opts['pw']);
  if ($handle === false) {
    CAFEVerror('Could not connect to data-base server: "'.@mysql_error().'"');
  }

  // Fucking shit
  $query = "SET NAMES 'utf8'";
  CAFEVmyquery($query, $handle);

  //specify database
  $dbres = mysql_select_db($opts['db'], $handle);
  
  if (!$dbres) {
    CAFEVerror('Unable to select '.$opts['db']);
  }
  return $handle;
}

function CAFEVmyclose($handle = false)
{
  if ($handle) {
    mysql_close($handle);
  }
  // give a damn on errors
  return true;
}

function CAFEVmyquery($query, $handle = false, $die = true, $silent = false)
{
  if (CAFEVdebugMode()) {
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
    CAFEVerror('mysql_query() failed: "'.$err.'"', $die);
  }
  return $result;
}

function CAFEVmyfetch(&$res, $type = MYSQL_ASSOC)
{
  $result = mysql_fetch_array($res, $type);
  if (CAFEVdebugMode()) {
    print_r($result);
  }
  return $result;
}

function CAFEVmyescape($string, $handle = false)
{
  if ($handle) {
    return mysql_real_escape_string($string, $handle);
  } else {
    return mysql_real_escape_string($string);
  }    
}

// Extract set or enum keys
function sql_multikeys($table, $column, $handle)
{
  // Build SQL Query  
  $query = "SHOW COLUMNS FROM $table LIKE '$column'";

  // Fetch the result or die
  $result = CAFEVmyquery($query, $handle) or die("Couldn't execute query");

  $line = CAFEVmyfetch($result);

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

function disableEnterSubmit()
{
  echo '<script type="text/javascript">
function stopRKey(evt) {
  var evt = (evt) ? evt : ((event) ? event : null);
  var node = (evt.target) ? evt.target : ((evt.srcElement) ? evt.srcElement : null);
  if ((evt.keyCode == 13) && (node.type=="text"))  {return false;}
}

document.onkeypress = stopRKey;
</script>';
}

function parseEmailListToArray($list)
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

function EmailEchoInvalid($kind, $email)
{
  echo '<HR/><H4>The '.$kind.' address "'.$email.'" seems to be invalid.
<p>
Please correct that first and then click on the "Send"-button again.
<P>
Unfortunately, attachments (if any) have to be specified again.
</H4>';
}

function EmailAddAddress($phpmailer, $address, $name = '')
{
  if (!$phpmailer->AddAddress($address, $name)) {
    EmailEchoInvalid('recipient', $address);
    return false;
  }
  return true;
}

function EmailAddCC($phpmailer, $address, $name = '')
{
  if (!$phpmailer->AddCC($address, $name)) {
    EmailEchoInvalid('"Cc:"', $address);
    return false;
  }
  return true;
}

function EmailAddBCC($phpmailer, $address, $name = '')
{
  if (!$phpmailer->AddBCC($address, $name)) {
    EmailEchoInvalid('"Bcc:"', $address);
    return false;
  }
  return true;
}

function EmailSetFrom($phpmailer, $address, $name = '')
{
  if ($phpmailer->SetFrom($address, $name) != true) {
    EmailEchoInvalid('"From:"', $address);
    return false;
  }
  return true;
}

function EmailAddReplyTo($phpmailer, $address, $name = '')
{
  if ($phpmailer->AddReplyTo($address, $name) != true) {
    EmailEchoInvalid('"ReplyTo:"', $address);
    return false;
  }
  return true;
}

// Maybe not needed.
function MailCallback($isSent, $to, $cc, $bcc, $subject, $body)
{


}

?>
