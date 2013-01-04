<?php

namespace CAFEVDB
{

class L
{
  private static $l = false;

  public static function t($text, $parameters = array())
  {
    if (self::$l === false) {
      self::$l = \OC_L10N::get('cafevdb');
 
      // If I omit the next line then the first call to $l->t()
      // generates a spurious new-line. Why?
      //
      // Mea Culpa: don't include a new-line after end tag
      //strval(self::$l->t('blah'));
    }
    return self::$l->t(strval($text), $parameters);
  }
};

class Ajax
{
  public static function bailOut($msg, $tracelevel = 1, $debuglevel = \OCP\Util::ERROR)
  {
    \OCP\JSON::error(array('data' => array('message' => $msg)));
    self::debug($msg, $tracelevel, $debuglevel);
    exit();
  }
  
  public static function debug($msg, $tracelevel = 0, $debuglevel = \OCP\Util::DEBUG)
  {
    if (PHP_VERSION >= "5.4") {
      $call = debug_backtrace(false, $tracelevel+1);
    } else {
      $call = debug_backtrace(false);
    }
    
    $call = $call[$tracelevel];
    if ($debuglevel !== false) {
      \OCP\Util::writeLog('contacts',
                          $call['file'].'. Line: '.$call['line'].': '.$msg,
                          $debuglevel);
    }
  }
};

class Util
{
  /**Return the locale. */
  public static function getLocale()
  {
    $lang = \OC_L10N::findLanguage(Config::APP_NAME);
    $locale = $lang.'_'.strtoupper($lang).'.UTF-8';
    return $locale;
  }

  /**Return the maximum upload file size. */
  public static function maxUploadSize($target = 'temporary')
  {
    $upload_max_filesize = \OCP\Util::computerFileSize(ini_get('upload_max_filesize'));
    $post_max_size = \OCP\Util::computerFileSize(ini_get('post_max_size'));
    $maxUploadFilesize = min($upload_max_filesize, $post_max_size);

    if ($target == 'owncloud') {
      $freeSpace = \OC_Filesystem::free_space('/');
      $freeSpace = max($freeSpace, 0);
      $maxUploadFilesize = min($maxUploadFilesize, $freeSpace);
    }
    return $maxUploadFilesize;
  }

  /**Check whether we are logged in.
   */
  public static function authorized() 
  {
    \OC_Util::checkLoggedIn();
    \OC_Util::checkAppEnabled(Config::APP_NAME);
  }

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

  public static function alert($text, $title, $cssid = false)
  {
    echo "<script>\n";
    echo "$.alert('$text', '$title');\n";
    if ($cssid !== false) {
      echo <<<__EOT__
$('#$cssid').append('<u>$title</u>'+'<br/>'+'$text'+'<br/>');
__EOT__;
    }
    echo "</script>\n";
    echo '<u>'.$title.'</u><br/>'.$text.'<br/>';
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

class Navigation
{
  /**Acutally rather a multi-select than a button, meant as drop-down
   * menu. Generates data which can be passed to prependTableButton()
   * below.
   */
  public static function tableExportButton()
  {
    $data = ''
      .'<span id="pme-export-block">'
      .'<select '
      .'data-placeholder="'.L::t('Export Table').'" '
      .'class="pme-export" '
      .'id="pme-export-choice"'
      .'name="export" >
  <option value=""></option>
  <option value="CSV">'.L::t('CSV Export').'</option>
  <option value="HTML">'.L::t('HTML Export').'</option>
  <option value="EXCEL">'.L::t('Excel Export').'</option>
</select></span>';

    $button = array('code' => $data);

    return $button;
  }

  /**Add a new button to the left of already registered phpMyEdit
   * buttons. This is a dirty hack. But so what. Only the L and F
   * (list and filter) views are augmented.
   *
   * @param[in] $button The new button.
   *
   * @param[in] $misc Whether or not to include the extra misc-button.
   *
   * @return Array suitable to be plugged in $opts['buttons'].
   */
  public static function prependTableButton($button, $misc = false)
  {
    // Cloned from phpMyEdit class:
    $default_buttons_no_B = array(
      'L' => array($button,
                   '<<','<','add','>','>>',
                   'goto','rows_per_page'),
      'F' => array($button,
                   '<<','<','add','>','>>',
                   'goto','rows_per_page'),
      'A' => array('save','more','cancel'),
      'C' => array('save','more','cancel'),
      'P' => array('save', 'cancel'),
      'D' => array('save','cancel'),
      'V' => array('change','cancel')
      );
	$default_multi_buttons_no_B = array(
      'L' => array($button,
                   '<<','<','misc','add','>','>>',
                   'goto','rows_per_page'),
      'F' => array($button,
                   '<<','<','misc','add','>','>>',
                   'goto','rows_per_page'),
      'A' => array('save','more','cancel'),
      'C' => array('save','more','cancel'),
      'P' => array('save', 'cancel'),
      'D' => array('save','cancel'),
      'V' => array('change','cancel')
      );
    
    $result = array();
    if (!$misc) {
      foreach ($default_buttons_no_B as $key => $value) {
        $result[$key] = array('up' => $value, 'down' => $value);
      }
    } else {
      foreach ($default_multi_buttons_no_B as $key => $value) {
        $result[$key] = array('up' => $value, 'down' => $value);
      }
    }    

    return $result;
  }
  

  public static function button($id='projects', $project='', $projectId=-1)
  {
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
        $name  = L::t($btn['name']);
        $title = isset($btn['title']) ? L::t($btn['title']) : $name;
        $html .= ''
          .'<button class="'.$btn['class'].'" title="'.$title.'"'
          .(isset($btn['id']) ? ' id="'.$btn['id'].'"' : '')
          .(isset($btn['style']) ? ' style="'.$btn['style'].'"' : '')
          .(isset($btn['js']) ? ' '.$btn['js'].' ' : '')
          .'>';
        if (isset($btn['image'])) {
          $html .= ''
            .'<img class="svg" '
            .(isset($btn['id']) ? ' id="'.$btn['id'].'-img" ' : ' ')
            .'src="'.$btn['image'].'" alt="'.$name.'" />';
        } else {
          $html .= $name;
        }
        $html .= '</button>
';
        $html .= $between;
      }
      $html .= $post;
      return $html;
    }

    $headervisibility = '<input type="hidden" name="headervisibility" '
      .'value="'.Util::cgiValue('headervisibility', 'expanded').'" />';

    $controlid = $id.'control';
    $form = '';
    switch ($id) {

    case 'projects':
      $value = L::t("View all Projects");
      $title = L::t("Overview over all known projects (start-page).");
      $form =<<<__EOT__
<form class="cafevdb-control" id="$controlid" method="post" action="?app=cafevdb">
  <input type="submit" value="$value" title="$title"/>
  <input type="hidden" name="Template" value="projects"/>
  $headervisibility
</form>

__EOT__;
      break;

    case 'all':
      $value = L::t("Display all Musicians");
      $title = L::t("Display all musicians stored in the data-base, with detailed facilities for filtering and sorting.");
      $form =<<<__EOT__
<form class="cafevdb-control" id="$controlid" method="post" action="?app=cafevdb">
  <input type="submit" value="$value" title="$title"/>
  <input type="hidden" name="Template" value="all-musicians"/>
  $headervisibility
</form>

__EOT__;
      break;

    case 'email':
      $title = L::t("Mass-email form, use with care. Mass-emails will be logged. Recipients will be specified by the Bcc: field in the header, so the recipients are undisclosed to each other.");
      $form =<<<__EOT__
<form class="cafevdb-control" id="$controlid" method="post" action="?app=cafevdb">
  <input type="submit" name="" value="Em@il" title="$title"/>
  <input type="hidden" name="Template" value="email"/>
  <input type="hidden" name="Project" value="$project"/>
  <input type="hidden" name="ProjectId" value="$projectId"/>
  $headervisibility
</form>

__EOT__;
      break;

    case 'emailhistory':
      $value = L::t("Email History");
      $title = L::t("Display all emails sent by our mass-email form.");
      $form =<<<__EOT__
<form class="cafevdb-control" id="$controlid" method="post" action="?app=cafevdb">
  <input type="submit" value="$value" title="$title"/>
  <input type="hidden" name="Template" value="email-history"/>
  <input type="hidden" name="Project" value="$project"/>
  <input type="hidden" name="ProjectId" value="$projectId"/>
  $headervisibility
</form>

__EOT__;
      break;

    case 'projectlabel':
      Config::init();
      $syspfx = Config::$pmeopts['cgi']['prefix']['sys'];
      $opname = $syspfx.'operation';
      $opwhat = 'View?'.$syspfx.'rec='.$projectId;
      $title = L::t("The currently active project.");
      $form =<<<__EOT__
<form class="cafevdb-control" id="$controlid" method="post" action="?app=cafevdb">
  <input type="submit" value="$project" title="$title"/>
  <input type="hidden" name="Template" value="projects"/>
  <input type="hidden" name="Project" value="$project"/>
  <input type="hidden" name="ProjectId" value="$projectId"/>
  <input type="hidden" name="$opname" value="$opwhat"/>
  $headervisibility
</form>

__EOT__;
      break;

    case 'detailed':
      $value = L::t("Detailed Instrumentation");
      $title = L::t("Detailed display of all registered musicians for the selected project. The table will allow for modification of personal data like email, phone, address etc.");
      $form =<<<__EOT__
<form class="cafevdb-control" id="$controlid" method="post" action="?app=cafevdb">
  <input type="submit" value="$value" title="$title"/>
  <input type="hidden" name="Template" value="detailed-instrumentation"/>
  <input type="hidden" name="Project" value="$project"/>
  <input type="hidden" name="ProjectId" value="$projectId"/>
  $headervisibility
</form>

__EOT__;
      break;

    case 'brief':
      $value = L::t("Brief Instrumentation");
      $title = L::t("Brief display of all registered musicians for the selected project. The table will allow for modification of project specific data, like the instrument, the project-fee etc.");
      $form =<<<__EOT__
<form class="cafevdb-control" id="$controlid" method="post" action="?app=cafevdb">
  <input type="submit" value="$value" title="$title"/>
  <input type="hidden" name="Template" value="brief-instrumentation"/>
  <input type="hidden" name="Project" value="$project"/>
  <input type="hidden" name="ProjectId" value="$projectId"/>
  $headervisibility
</form>

__EOT__;
      break;

    case 'instruments':
      $value = L::t("Add Instruments");
      $title = L::t("Display the list of instruments known by the data-base, possibly add new ones as needed.");
      $form =<<<__EOT__
<form class="cafevdb-control" id="$controlid" method="post" action="?app=cafevdb">
  <input type="submit" value="$value" title="$title"/>
  <input type="hidden" name="Template" value="instruments"/>
  <input type="hidden" name="Project" value="$project"/>
  <input type="hidden" name="ProjectId" value="$projectId"/>
  $headervisibility
</form>

__EOT__;
      break;

    case 'projectinstruments':
      $value = L::t("Instrumentation Numbers");
      $title = L::t("Display the desired instrumentaion numbers, i.e. how many musicians are already registered for each instrument group and how many are finally needed.");
      $form =<<<__EOT__
<form class="cafevdb-control" id="$controlid" method="post" action="?app=cafevdb">
  <input type="submit" name="" value="$value" title="$title"/>
  <input type="hidden" name="Template" value="project-instruments"/>
  <input type="hidden" name="Project" value="$project"/>
  <input type="hidden" name="ProjectId" value="$projectId"/>
  $headervisibility
</form>

__EOT__;
      break;

    case 'add':
      $value = L::t("Add more Musicians");
      $title = L::t("List of all musicians NOT registered for the selected project. Only through that table a new musician can enter a project. Look for a hyper-link Add_to_PROJECT");
      $form =<<<__EOT__
<form class="cafevdb-control" id="$controlid" method="post" action="?app=cafevdb">
  <input type="submit" value="$value" title="$title"/>
  <input type="hidden" name="Template" value="add-musicians"/>
  <input type="hidden" name="Project" value="$project"/>
  <input type="hidden" name="ProjectId" value="$projectId"/>
  $headervisibility
</form>

__EOT__;
      break;

    case 'transfer-instruments':
      $value = strval(L::t('Transfer Instruments from Musicians'));
      $title = strval(L::t(Config::toolTips('transfer-instruments')));
      $form =<<<__EOT__
<form class="cafevdb-control" id="$controlid" method="post" action="?app=cafevdb">
  <input type="submit" value="$value" title="$title" />
  <input type="hidden" name="ProjectId" value="$projectId" />
  <input type="hidden" name="Project"   value="$project" />
  <input type="hidden" name="Template"  value="project-instruments" />
  <input type="hidden" name="Action"    value="transfer-instruments" />
  $headervisibility
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

  public static function queryNumRows($querypart, $handle = false, $die = true, $silent = false)
  {
      $query = 'SELECT COUNT(*) '.$querypart;
      $result = self::query($query, $handle, $die, $silent);
      return self::fetch($result, MYSQL_NUM)[0];
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

/*
 * Local Variables: ***
 * c-basic-offset: 2 ***
 * End: ***
 */

} // namespace

?>
