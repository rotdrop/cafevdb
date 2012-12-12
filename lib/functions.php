<?php

namespace CAFEVDB;

class Util
{

  public static function debugMode()
  {
    if (Config::$debug_query) {
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
    if (Util::debugMode()) {
      Util::error($msg, false);
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
    if (Util::debugMode()) {
      echo '<PRE>';
      print_r($_SERVER);
      echo '</PRE><HR/>';
      Util::error('Redirect attempt to "'.$redirect.'"');
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
    } elseif (isset(Config::$cgiVars["$key"])) {
      return Config::$cgiVars["$key"];
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

class DummyTranslation
{
  public function t($msg) {
    return $msg;
  }
};

class Navigation
{
  private static $l = false;
  public static function setTranslation(&$l) {
    self::$l = $l;
  }
  public static function button($id='projects', $project='', $projectId=-1)
  {
    if (!self::$l) {
      self::$l = new DummyTranslation();
    }
    $l = self::$l;

    if (is_array($id)) {
      $buttons = $id;
      $pre = $post = $between = '';
      if (isset($buttons['pre'])) {
        $pre = $buttons['pre'];
        unset($buttons['pre']);
      }
      if (isset($buttons['post'])) {
        $post = $buttons['post'];
        unset($buttons['post']);
      }
      if (isset($buttons['between'])) {
        $between = $buttons['between'];
        unset($buttons['between']);
      }
      $html = $pre;
      foreach ($buttons as $key => $btn) {
        $title = isset($btn['title']) ? $l->t($btn['title']) : $btn['name'];
        $html .= ''
          .'<button class="'.$btn['class'].'" title="'.$title.'"'
          .(isset($btn['id']) ? ' id="'.$btn['id'].'"' : '')
          .(isset($btn['style']) ? ' style="'.$btn['style'].'"' : '')
          .'>';
        if (isset($btn['image'])) {
          $html .= '<img class="svg" src="'.$btn['image'].'" alt="'.$btn['name'].'" />';
        } else {
          $html .= $btn['name'];
        }
        $html .= '</button>
';
        $html .= $between;
      }
      $html .= $post;
      return $html;
    }

    $controlid = $id.'control';
    $form = '';
    switch ($id) {

    case 'projects':
      $value = $l->t("View all Projects");
      $title = $l->t("Overview over all known projects (start-page).");
      $form =<<<__EOT__
<form class="cafevdb-control" id="$controlid" method="post" action="?app=cafevdb">
  <input type="submit" value="$value" title="$title"/>
  <input type="hidden" name="Action" value="-1"/>
  <input type="hidden" name="Template" value="projects"/>
</form>

__EOT__;
      break;

    case 'all':
      $value = $l->t("Display all Musicians");
      $title = $l->t("Display all musicians stored in the data-base, with detailed facilities for filtering and sorting.");
      $form =<<<__EOT__
<form class="cafevdb-control" id="$controlid" method="post" action="?app=cafevdb">
  <input type="submit" value="$value" title="$title"/>
  <input type="hidden" name="Action" value="DisplayAllMusicians"/>
  <input type="hidden" name="Template" value="all-musicians"/>
</form>

__EOT__;
      break;

    case 'email':
      $form =<<<__EOT__
<form class="cafevdb-control" id="$controlid" method="post" action="?app=cafevdb">
  <input type="submit" name="" value="Em@il" title="Mass-email form, use with care. Mass-emails will be logged. Recipients will be specified by the Bcc: field in the header, so the recipients are undisclosed to each other."/>
  <input type="hidden" name="Action" value="Email"/>
  <input type="hidden" name="Template" value="email"/>
  <input type="hidden" name="Project" value="$project"/>
  <input type="hidden" name="ProjectId" value="$projectId"/>
</form>

__EOT__;
      break;

    case 'emailhistory':
      $form =<<<__EOT__
<form class="cafevdb-control" id="$controlid" method="post" action="?app=cafevdb">
  <input type="submit" name="" value="Email History" title="Display all emails sent by our mass-email form."/>
  <input type="hidden" name="Action" value="Email History"/>
  <input type="hidden" name="Template" value="email-history"/>
  <input type="hidden" name="Project" value="$project"/>
  <input type="hidden" name="ProjectId" value="$projectId"/>
</form>

__EOT__;
      break;

    case 'projectlabel':
      Config::init();
      $syspfx = Config::$pmeopts['cgi']['prefix']['sys'];
      $opname = $syspfx.'operation';
      $opwhat = 'View?'.$syspfx.'rec='.$projectId;
      $form =<<<__EOT__
<form class="cafevdb-control" id="$controlid" method="post" action="?app=cafevdb">
  <input type="submit" name="" value="$project" title="The currently active project."/>
  <input type="hidden" name="Action" value="-1"/>
  <input type="hidden" name="Template" value="projects"/>
  <input type="hidden" name="Project" value="$project"/>
  <input type="hidden" name="ProjectId" value="$projectId"/>
  <input type="hidden" name="$opname" value="$opwhat"/>
</form>

__EOT__;
      break;

    case 'detailed':
      $value = $l->t("Detailed Instrumentation");
      $title = $l->t("Detailed display of all registered musicians for the selected project. The table will allow for modification of personal data like email, phone, address etc.");
      $form =<<<__EOT__
<form class="cafevdb-control" id="$controlid" method="post" action="?app=cafevdb">
  <input type="submit" value="$value" title="$title"/>
  <input type="hidden" name="Action" value="DetailedInstrumentation"/>
  <input type="hidden" name="Template" value="detailed-instrumentation"/>
  <input type="hidden" name="Project" value="$project"/>
  <input type="hidden" name="ProjectId" value="$projectId"/>
</form>

__EOT__;
      break;

    case 'brief':
      $form =<<<__EOT__
<form class="cafevdb-control" id="$controlid" method="post" action="?app=cafevdb">
  <input type="submit" name="" value="Brief Instrumentation" title="Brief display of all registered musicians for the selected project. The table will allow for modification of project specific data, like the instrument, the project-fee etc."/>
  <input type="hidden" name="Action" value="BriefInstrumentation"/>
  <input type="hidden" name="Template" value="brief-instrumentation"/>
  <input type="hidden" name="Project" value="$project"/>
  <input type="hidden" name="ProjectId" value="$projectId"/>
</form>

__EOT__;
      break;

    case 'instruments':
      $value = $l->t("Add Instruments");
      $title = $l->t("Display the list of instruments known by the data-base, possibly add new ones as needed.");
      $form =<<<__EOT__
<form class="cafevdb-control" id="$controlid" method="post" action="?app=cafevdb">
  <input type="submit" value="$value" title="$title"/>
  <input type="hidden" name="Action" value="Instruments"/>
  <input type="hidden" name="Template" value="instruments"/>
  <input type="hidden" name="Project" value="$project"/>
  <input type="hidden" name="ProjectId" value="$projectId"/>
</form>

__EOT__;
      break;

    case 'projectinstruments':
      $value = $l->t("Instrumentation Numbers");
      $title = $l->t("Display the desired instrumentaion numbers, i.e. how many musicians are already registered for each instrument group and how many are finally needed.");
      $form =<<<__EOT__
<form class="cafevdb-control" id="$controlid" method="post" action="?app=cafevdb">
  <input type="submit" name="" value="$value" title="$title"/>
  <input type="hidden" name="Action" value="ProjectInstruments"/>
  <input type="hidden" name="Template" value="project-instruments"/>
  <input type="hidden" name="Project" value="$project"/>
  <input type="hidden" name="ProjectId" value="$projectId"/>
</form>

__EOT__;
      break;

    case 'add':
      $form =<<<__EOT__
<form class="cafevdb-control" id="$controlid" method="post" action="?app=cafevdb">
  <input type="submit" name="" value="Add more Musicians" title="List of all musicians <b>not</b> registered for the selected project. Only through that table a new musician can enter a project. Look for a hyper-link Add_to_PROJECT"/>
  <input type="hidden" name="Action" value="AddMusicians"/>
  <input type="hidden" name="Template" value="add-musicians"/>
  <input type="hidden" name="Project" value="$project"/>
  <input type="hidden" name="ProjectId" value="$projectId"/>
</form>

__EOT__;
      break;

    }

    return $form;
  }
};

class mySQL
{
  public static function connect($opts, $die = true)
  {
    // Fetch the actual list of instruments, we will need it anyway
    $handle = mysql_connect($opts['hn'], $opts['un'], $opts['pw']);
    if ($handle === false) {
      Util::error('Could not connect to data-base server: "'.@mysql_error().'"');
    }

    // Fucking shit
    $query = "SET NAMES 'utf8'";
    mySQL::query($query, $handle);

    //specify database
    $dbres = mysql_select_db($opts['db'], $handle);
  
    if (!$dbres) {
      Util::error('Unable to select '.$opts['db']);
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
    if (Util::debugMode()) {
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
      Util::error('mysql_query() failed: "'.$err.'"', $die);
    }
    return $result;
  }

  public static function fetch(&$res, $type = MYSQL_ASSOC)
  {
    $result = mysql_fetch_array($res, $type);
    if (Util::debugMode()) {
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
    $result = mySQL::query($query, $handle) or die("Couldn't execute query");
    $line = mySQL::fetch($result);

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


?>
