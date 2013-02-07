<?php
/**@author Claus-Justus Heine
 * @copyright 2013 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

/**CamerataDB namespace to prevent name-collisions.
 */
namespace CAFEVDB
{

/**Support for internationalization.
 */
class L
{
  private static $l = false;

  /**Print the translated text.
   *
   * @param[in] $text Text to print, is finally passed to vsprintf().
   *
   * @param[in] $parameters Defaults to an empty array. @a $parameters
   * are passed on to vsprintf().
   *
   * @return The possibly translated message.
   */
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
    return self::$l->t($text, $parameters);
  }
};

/**Ajax specific support class. */
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

/**Supportclass for error handling.
 */
class Error
{
  private static $exceptionsActive = false;

  /**Switch error-handling via exceptions on and off by installing an
   * error-handler via set_error_handler().
   *
   * @param[in] $on Mixed. If @c true then errors will trigger
   * exceptions, if @c false then erros will not trigger exceptions,
   * if unspecified then report whether or not errors trigger
   * exceptions.
   *
   * @return @c true if errors are handled via exceptions, false
   * otherwise.
   */
  static public function exceptions($on = null) {
    if ($on === true) {
      if (!self::$exceptionsActive) {
        \set_error_handler('CAFEVDB\Error::exceptions_error_handler');
        self::$exceptionsActive = true;
      }
      return true;
    } else if ($on === false) {
      if (self::$exceptionsActive) {
        \restore_error_handler();
        self::$exceptionsActive = false;
      }
      return false;
    } else if ($on === null) {
      return self::$exceptionsActive;
    } else {
      throw new \InvalidArgumentException(L::t('Invalid argument value: `%s\'', array($on)));
    }
  }

  /**Error handler which redirects some errors into the exception
   * queue. Errors not inclued in error_reporting() do not result in
   * exceptions to be thrown.
   *
   */
  static public function exceptions_error_handler($severity, $message, $filename, $lineno) {

    // Support in particular @ constructs.
    if (!(\error_reporting() & $severity)) {
      // This error code is not included in error_reporting
      return;
    }

    if (ini_get('log_errors')) {
      $errmsg = sprintf("%s (level %d, file %s, line %d)",
                        $message, $severity, $filename, $lineno);
      error_log($errmsg, 0);
    }

    throw new \ErrorException($message, 0, $severity, $filename, $lineno);
  }
}

/**Utility class.
 */
class Util
{
  private static $inlineScripts = array();
  private static $externalScripts = array();

  /**Add some java-script external code (e.g. Google maps). Emit it
   * with emitExternalScripts().
   */
  public static function addExternalScript($script = '') 
  {
    self::$externalScripts[] = $script;
  }

  /**Dump all external java-script scripts previously add with
   * addExternalScript(). Each inline-script is wrapped into a separate
   * <script></script> element to make debugging easier.
   */
  public static function emitExternalScripts()
  {
    $scripts = '';
    foreach(self::$externalScripts as $script) {
      $scripts .=<<<__EOT__
<script type="text/javascript" src="$script"></script>

__EOT__;
    }
    self::$externalScripts = array(); // don't dump twice.

    return $scripts;
  }


  /**Add some java-script inline-code. Emit it with emitInlineScripts().
   */
  public static function addInlineScript($script = '') 
  {
    self::$inlineScripts[] = $script;
  }

  /**Dump all inline java-script scripts previously add with
   * addInlineScript(). Each inline-script is wrapped into a separate
   * <script></script> element to make debugging easier.
   */
  public static function emitInlineScripts()
  {
    $scripts = '';
    foreach(self::$inlineScripts as $script) {
      $scripts .=<<<__EOT__

<script type="text/javascript">
$script
</script>

__EOT__;
    }
    self::$inlineScripts = array(); // don't dump twice.

    return $scripts;
  }

  /**Format the right way (tm). */
  public static function strftime($format, $timestamp = NULL, $locale = NULL)
  {
    $oldlocale = setlocale(LC_TIME, 0);
    if ($locale) {
      setlocale(LC_TIME, $locale);
    }
    $result = strftime($format, $timestamp);

    setlocale(LC_TIME, $oldlocale);

    return $result;
  }
    
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

  /**Emit an error message or exception. If CAFEVDB\Error::exceptions()
   * returns @c true, then an exception is thrown, otherwise execution
   * is terminated by die(). Execution continues if $die = false. No
   * messages are printed, if @c $silent = @c true.
   *
   * @param[in] $msg String to print or to pass as exception message.
   *
   * @param[in] $die Terminate execution, either calling die() or by
   * throwing an exception.
   *
   * @param[in] $silent Do not print an error message. No effect if
   * exceptions are used for error handling.
   *
   * @return @c false (if the functin returns).
   */
  public static function error($msg, $die = true, $silent = false)
  {
    if (Error::exceptions()) {
      // $silent is not needed.
      if ($die) {
        throw new \Exception($msg);
      }
      return false;
    }
    $msg = '<HR/><PRE>
'.htmlspecialchars($msg).
      '</PRE><HR/>
';
    if ($die) {
      if ($silent) {
        die;
      } else {
        die($msg);
      }
    } else {
      if ($silent) {
        return $msg;
      }
      echo $msg;
      return false;
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
    //$text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8', false);
    //$title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8', false);    
    $text  = addslashes($text);
    $title = addslashes($title);
    echo "<script>\n";
    echo "OC.dialogs.alert('$text', '$title');\n";
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
function stopRKey(evt) {
  var evt = (evt) ? evt : ((event) ? event : null);
  var node = (evt.target) ? evt.target : ((evt.srcElement) ? evt.srcElement : null);
  if ((evt.keyCode == 13) && (node.type=="text"))  {return false;}
}

document.onkeypress = stopRKey;
</script>
';
  }
};

/**Support class to generate navigation buttons and the like.
 */
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
      .'<label>'
      .'<select '
      .'data-placeholder="'.L::t('Export Table').'" '
      .'class="pme-export" '
      .'id="pme-export-choice"'
      .'title="'.Config::toolTips('pme-export-choice').'" '
      .'name="export" >
  <option value=""></option>
  <option '
    .'title="'.Config::toolTips('pme-export-csv').'" '
    .'value="CSV">'.L::t('CSV Export').'</option>
  <option '
    .'title="'.Config::toolTips('pme-export-html').'" '
    .'value="HTML">'.L::t('HTML Export').'</option>
  <option '
    .'title="'.Config::toolTips('pme-export-excel').'" '
    .'value="EXCEL">'.L::t('Excel Export').'</option>
  <option '
    .'title="'.Config::toolTips('pme-export-htmlexcel').'" '
    .'value="SSML">'.L::t('HTML/Spreadsheet').'</option>
</select></label></span>';

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
      'L' => array('<<','<',
                   $button, 'add',
                   '>','>>',
                   'goto','rows_per_page'),
      'F' => array('<<','<',
                   $button, 'add',
                   '>','>>',
                   'goto','rows_per_page'),
      'A' => array('save','more','cancel'),
      'C' => array('save','more','cancel'),
      'P' => array('save', 'cancel'),
      'D' => array('save','cancel'),
      'V' => array('change','cancel')
      );
    $default_multi_buttons_no_B = array(
      'L' => array('<<','<',
                   'misc', $button, 'add',
                   '>','>>',
                   'goto','rows_per_page'),
      'F' => array('<<','<',
                   'misc', $button, 'add',
                   '>','>>',
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
  
  public static function buttonsFromArray($buttons)
  {
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
      $type  = isset($btn['type']) ? $btn['type'] : 'button';
      $name  = $btn['name'];
      $title = isset($btn['title']) ? $btn['title'] : $name;
      $style = isset($btn['style']) ? $btn['style'] : '';
      
      switch ($type) {
      case 'button':
        $html .= ''
          .'<button class="'.$btn['class'].'" title="'.$title.'"'
          .(isset($btn['id']) ? ' id="'.$btn['id'].'"' : '')
          .($style != '' ? ' style="'.$btn['style'].'"' : '')
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
        break;
      case 'input':
        if (isset($btn['image'])) {
          $style = 'background:url(\''.$btn['image'].'\') no-repeat center;'.$style;
          $name  = '';
        }                 
        $html .= ''
          .'<input type="button" class="'.$btn['class'].'" title="'.$title.'"'
          .(isset($btn['id']) ? ' id="'.$btn['id'].'"' : '')
          .($style != '' ? 'style="'.$style.'" ' : '')
          .(isset($btn['js']) ? ' '.$btn['js'].' ' : '')
          .'value="'.$name.'" '
          .'/>
';
        break;
      default:
        $html .= '<span>'.L::t('Error: Unknonwn Button Type').'</span>'."\n";
        break;
      }
      $html .= $between;
    }
    $html .= $post;
    return $html;
  }

  public static function button($id='projects', $project='', $projectId=-1)
  {
    if (is_array($id)) {
      return self::buttonsFromArray($id);
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
      $value = L::t('Instrumentation Numbers');
      $title = L::t('Display the desired instrumentaion numbers, i.e. how many musicians are already registered for each instrument group and how many are finally needed.');
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
      $title = strval(Config::toolTips('transfer-instruments'));
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

/**Support class for connecting to a mySQL database.
 */
class mySQL
{
  /**Connect to the server specified by @a $opts.
   *
   * @param[in] $opts Associative array with keys 'hn', 'un', 'pw' and
   * 'db' for "hostname", "username", "password" and
   * "database",repectively.
   *
   * @param[in] $die Bail out on error. Default is @c true. If @c
   * false then go on and return @c false in case of an error.
   *
   * @param[in] $silent If exception-based error handling is not in
   * effect, then control whehter something is printed to the standard
   * output channel.
   *
   * @return Mixed, @c false in case of error. Otherwise the data-base
   * handle.
   * @callgraph
   * @callergraph
   */
  public static function connect($opts, $die = true, $silent = false)
  {
    // Fetch the actual list of instruments, we will need it anyway
    $handle = @mysql_connect($opts['hn'], $opts['un'], $opts['pw']);
    if ($handle === false) {
      Util::error('Could not connect to data-base server: "'.@mysql_error().'"', $die, $silent);
      return false;
    }

    // Fucking shit
    $query = "SET NAMES 'utf8'";
    mySQL::query($query, $handle, $die, $silent);

    //specify database
    $dbres = @mysql_select_db($opts['db'], $handle);
  
    if (!$dbres) {
      Util::error('Unable to select '.$opts['db'], $die, $silent);
      return false;
    }
    return $handle;
  }

  /**Close the mySQL data-base connection previously opened by
   * self::connect().
   *
   * @param[in] $handle Database handle.
   *
   * @return @c true, always.
   */
  public static function close($handle = false)
  {
    if ($handle) {
      @mysql_close($handle);
    }
    // give a damn on errors
    return true;
  }

  public static function query($query, $handle = false, $die = false, $silent = false)
  {
    if (Util::debugMode()) {
      echo '<HR/><PRE>'.htmlspecialchars($query).'</PRE><HR/><BR>';
    }
    if ($handle) {
      if (!($result = @mysql_query($query, $handle))) {
        $err = @mysql_error($handle);
      }
    } else {
      if (!($result = @mysql_query($query))) {
        $err = @mysql_error();
      }
    }
    if (!$result) {
      Util::error('mysql_query() failed: "'.$err.'"', $die, $silent);
    }
    return $result;
  }

  public static function queryNumRows($querypart, $handle = false, $die = true, $silent = false)
  {
      $query = 'SELECT COUNT(*) '.$querypart;
      $result = self::query($query, $handle, $die, $silent);
      $result = self::fetch($result, MYSQL_NUM);
      if (isset($result[0])) {
        return $result[0];
      } else {
        return 0;
      }
  }

  public static function fetch(&$res, $type = MYSQL_ASSOC)
  {
    $result = @mysql_fetch_array($res, $type);
    if (Util::debugMode()) {
      print_r($result);
    }
    return $result;
  }

  public static function escape($string, $handle = false)
  {
    if ($handle) {
      return @mysql_real_escape_string($string, $handle);
    } else {
      return @mysql_real_escape_string($string);
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
