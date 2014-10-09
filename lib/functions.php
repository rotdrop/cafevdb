<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014 Claus-Justus Heine <himself@claus-justus-heine.de>
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
 * You should have received a copy of the GNU Lesser General Public
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
    return (string)self::$l->t($text, $parameters);
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
      \OCP\Util::writeLog(Config::APP_NAME,
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
        // \register_shutdown_function('CAFEVDB\Error::fatal_error_handler');
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

  /**Try to catch fatal errors as well. */
  static public function fatal_error_handler()
  {
    $error = error_get_last();
    if ($error["type"] == E_ERROR) {
      // Nope, that does not work. Maybe write an email to somewhere ...
      self::exceptions_error_handler($error["type"], $error["message"], $error["file"], $error["line"]);
    }
  }
  
  /**Error handler which redirects some errors into the exception
   * queue. Errors not inclued in error_reporting() do not result in
   * exceptions to be thrown.
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
};

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
  public static function strftime($format, $timestamp = null, $tz = null, $locale = null)
  {
    $oldtz     = date_default_timezone_get();
    if ($tz) {
      date_default_timezone_set($tz);
    }
    
    $oldlocale = setlocale(LC_TIME, 0);
    if ($locale) {
      setlocale(LC_TIME, $locale);
    }
    $result = strftime($format, $timestamp);

    setlocale(LC_TIME, $oldlocale);
    date_default_timezone_set($oldtz);

    return $result;
  }
    
  /**Return the locale. */
  public static function getLocale()
  {
    $lang = \OC_L10N::findLanguage(Config::APP_NAME);
    $locale = $lang.'_'.strtoupper($lang).'.UTF-8';
    return $locale;
  }

  /**Return the timezone, from the calendar app. */
  public static function getTimezone()
  {
    return \OC_Calendar_App::getTimezone();
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

  /**Return @c true if the respective debug output setting is enabled.
   */
  public static function debugMode($key = 'general')
  {
    Config::init();

    return Config::$expertmode && Config::$debug[$key];    
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
        @ob_end_clean();
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
    //$text  = addslashes($text);
    //$title = addslashes($title);
    $class = $cssid === false ? '' : ' '.$cssid;
    echo '<input type="hidden" class="alertdata'.$class.
      '" name="'.htmlspecialchars($title).'" value="'.htmlspecialchars($text).'">'."\n";
    echo '<div class="alertblock'.$class.' cafevdb-error"><span class="title">'.$title.'</span><div class="text">'.$text.'</div></div>';
  }

  public static function redirect($page, $proto = null, $host = null, $port = null, $uri = null) {
  
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

  /**Emit a page header alowing for a cache duration of $minutes many minutes
   */
  public static function cacheHeader($minutes)
  {
    return;
    $exp_gmt = gmdate("D, d M Y H:i:s", time() + $minutes * 60) ." GMT";
    $mod_gmt = gmdate("D, d M Y H:i:s", getlastmod()) ." GMT";
    
    header("Expires: " . $exp_gmt, false);
    header("Last-Modified: " . $mod_gmt, false);
    header("Cache-Control: private, max-age=" . $minutes * 60, false);
    // Speziell für MSIE 5
    header("Cache-Control: pre-check=" . $minutes * 60, false);
  }  

  /**Get the post- or get-value from the request, or from the config
   * space if a default value exists.
   *
   * @param[in] $key The key (i.e. name) for the value.
   *
   * @param[in] $default Default value
   *
   * @param[in] $allowEmpty If true, an empty string is not an allowed
   *                value and null is returned, unless the default has
   *                been explicitly set to the empty string.
   */
  public static function cgiValue($key, $default=null, $allowEmpty = true)
  {
    $value = $default;
    if (isset($_POST["$key"])) {
      $value = $_POST["$key"];
    } elseif (isset($_GET["$key"])) {
      $value = $_GET["$key"];
    } elseif (isset(Config::$cgiVars["$key"])) {
      $value = Config::$cgiVars["$key"];
    }
    if (!$allowEmpty && !is_null($default) && $value == '') {
      $value = $default;
    }
    return $value;
  }

  /**Search $_POST and $_GET for $pattern; return a subset of the
   * respective array which contains the matchign $key => $value
   * pairs.
   */
  public static function cgiKeySearch($pattern)
  {
    $source = isset($_POST) ? $_POST : (isset($_GET) ? $_GET : Config::$cgiVars);

    $keys = preg_grep($pattern, array_keys($source));
    
    $result = array();
    foreach ($keys as $key) {
      $result[$key] = $source[$key];
    }
    
    return $result;
  }

  /** Compose an arry from all CGI data starting with PME_data_, or
   * more precisely: with Config::$pmeopts['cgi']['prefix']['data'];
   *
   * @param[in] $prefix If set the parameter overrides the default
   *                    prefix.
   */
  public static function getPrefixCGIData($prefix = null)
  {
    if (!isset($prefix)) {
      Config::init();
      $prefix = Config::$pmeopts['cgi']['prefix']['data'];
    }

    $result = array();
    foreach (array($_POST, $_GET) as $source) {
      if ($source) {
        foreach ($_POST as $key => $value) {
          if (strpos($key, $prefix) === 0) {
            $outKey = substr($key, strlen($prefix));
            $result[$outKey] = $source[$key];
          }
        }
      }
    }

    return $result;
  }

  /**Decode the record idea from the CGI data, return -1 if none
   * found.
   */
  public static function getCGIRecordId($prefix = null)
  {
    if (!isset($prefix)) {
      Config::init();
      $prefix = Config::$pmeopts['cgi']['prefix']['sys'];
    }
    $recordKey = $prefix.'rec';
    $recordId  = self::cgiValue($recordKey, -1);
    $opreq     = self::cgiValue($prefix.'operation');
    $op        = parse_url($opreq, PHP_URL_PATH);
    $opargs    = array();
    parse_str(parse_url($opreq, PHP_URL_QUERY), $opargs);
    if ($recordId < 0 && isset($opargs[$recordKey]) && $opargs[$recordKey] > 0) {
      $recordId = $opargs[$recordKey];
    }

    return $recordId > 0 ? $recordId : -1;
  }  
  
  public static function composeURL($location)
  {
    // Assume an absolute location w.r.t. to SERVERROOT
    if ($location[0] == '/') {
      $location = \OC_Helper::makeURLAbsolute($location);
    }
    return $location;
  }

  /**Try to verify a given location up to some respect ...
   *
   * @param[in] $location Either an "absolute" path relative to the
   * server root, starting with '/', or a valid HTML URL.
   */
  public static function URLIsValid($location)
  {
    $location = self::composeURL($location);
    
    \OCP\Util::writeLog(Config::APP_NAME, "Checking ".$location, \OC_LOG::DEBUG);

    // Don't try to access it if it is not a valid URL
    if (filter_var($location, FILTER_VALIDATE_URL) === false) {
      return false;
    }
    
    return true;
  }

  public static function entifyString($string)
  {
    if (defined("ENT_XHTML")) {
      return htmlentities($string, ENT_QUOTES|ENT_XHTML, 'UTF-8');
    } else {
      return htmlentities($string, ENT_QUOTES, 'UTF-8');
    }
  }

  /** phpMyEdit calls the triggers (callbacks) with the following arguments:
   *
   * @param[in] $pme The phpMyEdit instance
   *
   * @param[in] $op The operation, 'insert', 'update' etc.
   *
   * @param[in] $step 'before' or 'after'
   *
   * @param[in] $oldvals Self-explanatory.
   *
   * @param[in,out] &$changed Set of changed fields, may be modified by the callback.
   *
   * @param[in,out] &$newvals Set of new values, which may also be modified.
   *
   * @return boolean. If returning @c false the operation will be terminated
   */
  public static function beforeUpdateRemoveUnchanged($pme, $op, $step, $oldvals, &$changed, &$newvals)
  {
    // TODO: can be handle more efficiently with the PHP array_...() functions.
    foreach ($newvals as $key => $value) {
      if (array_search($key, $changed) === false) {
        unset($newvals["$key"]);
      }
    }

    return count($newvals) > 0;
  }

};

/**Support class to generate navigation buttons and the like.
 */
class Navigation
{
  const DISABLED = 1;
  const SELECTED = 2;

  /**Wrapper around core htmlspecialchars; avoid double encoding,
   * standard options.
   */
  public static function enc($string, $double_encode = false)
  {
    return htmlspecialchars($string, ENT_COMPAT|ENT_HTML401, 'UTF-8', $double_encode);
  }

  /**Emit select options
   *
   * @param $options Array with option tags:
   *
   * value => option value
   * name  => option name
   * flags => optional or bit-wise or of self::DISABLED, self::SELECTED
   * title => optional title
   * label => optional label
   * group => optional option group
   * groupClass => optional css, only taken into account on group-change
   *
   * Optional fields need not be present.
   */
  public static function selectOptions($options)
  {
    $result = '';
    $indent = '';
    if (!is_array($options) || count($options) == 0) {
      return $result;
    }
    $oldGroup = isset($options[0]['group']) ? self::enc($options[0]['group']) : false;
    if ($oldGroup) {
      $groupClass = isset($options[0]['groupClass']) ? ' class="'.$options[0]['groupClass'].'"' : '';
      $result .= '<optgroup label="'.$oldGroup.'"'.$groupClass.'>
';
      $indent = '  ';
    }
    foreach($options as $option) {
      $flags = isset($option['flags']) ? $option['flags'] : 0;
      $disabled = $flags & self::DISABLED ? ' disabled="disabled"' : '';
      $selected = $flags & self::SELECTED ? ' selected="selected"' : '';
      $label    = isset($option['label']) ? ' label="'.self::enc($option['label']).'"' : '';
      $title    = isset($option['title']) ? ' title="'.self::enc($option['title']).'"' : '';
      $group = isset($option['group']) ? self::enc($option['group']) : false;
      if ($group != $oldGroup) {
        $result .= '</optgroup>
';
        $oldGroup = $group;
        $indent = '';
        if ($group) {
          $groupClass = isset($option['groupClass']) ? ' class="'.$option['groupClass'].'"' : '';
          $result .= '<optgroup label="'.$group.'"'.$groupClass.'>
';
          $indent = '  ';
        }
      }
      $result .= $indent.'<option value="'.self::enc($option['value']).'"'.
        $disabled.$selected.$label.$title.
        '>'.
        self::enc($option['name']).
        '</option>
';
    }
    return $result;
  }
  

  /**Recursively emit hidden input elements to represent the given
   * data. $value may be a nested array.
   */
  public static function persistentCGI($key, $value = false)
  {
    if (is_array($key)) {
      $result = '';
      foreach ($key as $subkey => $subval) {
        $result .= self::persistentCGI($subkey, $subval);
      }
      return $result;
    } else if (is_array($value)) {
      $result = '';
      foreach($value as $subkey => $subval) {
        $result .= self::persistentCGI($key.'['.$subkey.']', $subval)."\n";
      }
      return $result;
    } else {
      return '<input type="hidden" name="'.$key.'" value="'.htmlspecialchars($value).'"/>'."\n";
    }
  }
  

  /**Acutally rather a multi-select than a button, meant as drop-down
   * menu. Generates data which can be passed to prependTableButton()
   * below.
   */
  public static function tableExportButton()
  {
    $data = ''
      .'<span id="pme-export-block" class="pme-export-block">'
      .'<label>'
      .'<select '
      .'data-placeholder="'.L::t('Export Table').'" '
      .'class="pme-export-choice" '
      .'id="pme-export-choice" '
      .'title="'.Config::toolTips('pme-export-choice').'" '
      .'name="export" >
  <option value=""></option>
  <option '
    .'title="'.Config::toolTips('pme-export-excel').'" '
    .'value="EXCEL">'.L::t('Excel Export').'</option>
  <option '
    .'title="'.Config::toolTips('pme-export-htmlexcel').'" '
    .'value="SSML">'.L::t('HTML/Spreadsheet').'</option>
  <option '
    .'title="'.Config::toolTips('pme-export-csv').'" '
    .'value="CSV">'.L::t('CSV Export').'</option>
  <option '
    .'title="'.Config::toolTips('pme-export-html').'" '
    .'value="HTML">'.L::t('HTML Export').'</option>
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
  public static function prependTableButton($button, $misc = false, $all = false)
  {
    // Cloned from phpMyEdit class:
    if (!$misc) {
      $default_buttons_no_B = array(
        'L' => array('<<', '<',
                     $button, 'add',
                     '>', '>>',
                     'goto', 'rows_per_page','reload'),
        'F' => array('<<', '<',
                     $button, 'add',
                     '>', '>>',
                     'goto', 'rows_per_page','reload'),
        'A' => array('save', 'apply', 'more', 'cancel'),
        'C' => array('save', 'more', 'cancel'),
        'P' => array('save', 'apply', 'cancel'),
        'D' => array('save', 'cancel'),
        'V' => array('change', 'cancel', 'reload')
        );
    } else {
      $default_buttons_no_B = array(
        'L' => array('<<','<',
                     'misc', $button, 'add',
                     '>','>>',
                     'goto','rows_per_page','reload'),
        'F' => array('<<','<',
                     'misc', $button, 'add',
                     '>','>>',
                     'goto','rows_per_page','reload'),
        'A' => array('save', 'apply', 'more', 'cancel'),
        'C' => array('save', 'more', 'cancel'),
        'P' => array('save', 'apply', 'cancel'),
        'D' => array('save', 'cancel'),
        'V' => array('change', 'cancel', 'reload')
        );
    }

    $result = array();
    foreach ($default_buttons_no_B as $key => $value) {
      if ($all && stristr("ACPDV", $key) !== false) {
        array_unshift($value, $button);
      }
      $upValue = array();
      $downValue = array();
      foreach ($value as $oneButton) {
        if (isset($button['code'])) {
          $upValue[] = preg_replace('/id="([^"]*)"/', 'id="$1-up"', $oneButton);
          $downValue[] = preg_replace('/id="([^"]*)"/', 'id="$1-down"', $oneButton);
        } else {
          $upValue[]   = $oneButton;
          $downValue[] = $oneButton;
        }
      }
      $result[$key] = array('up' => $upValue, 'down' => $downValue);
    }

    return $result;
  }

  /**Take any dashed lower-case string and convert to camel-acse.
   *
   * @param $sting the string to convert.
   *
   * @param $capitalizeFirstCharacter self explaining.
   */
  public static function dashesToCamelCase($string, $capitalizeFirstCharacter = false) 
  {    
    $str = str_replace(' ', '', ucwords(str_replace('-', ' ', $string)));
    
    if (!$capitalizeFirstCharacter) {
      $str[0] = strtolower($str[0]);
    }
    
    return $str;
  }

  /**Take an camel-case string and convert to lower-case with dashes
   * between the words. First letter may or may not be upper case.
   */
  public static function camelCaseToDashes($string)
  {
    return strtolower(preg_replace('/([A-Z])/', '-$1', lcfirst($string)));
  }

  public static function buttonsFromArray($buttons)
  {
    return self::htmlTagsFromArray($buttons);
  }
  
  /**Generate some html tags. Up to now only buttons and option
   * elements.
   */
  public static function htmlTagsFromArray($tags)
  {
    // Global setup, if any
    $pre = $post = $between = '';
    if (isset($tags['pre'])) {
      $pre = $tags['pre'];
      unset($tags['pre']);
    }
    if (isset($tags['post'])) {
      $post = $tags['post'];
      unset($tags['post']);
    }
    if (isset($tags['between'])) {
      $between = $tags['between'];
      unset($tags['between']);
    }

    // Per element setup
    $html = $pre;
    foreach ($tags as $key => $tag) {
      $type  = isset($tag['type']) ? $tag['type'] : 'button';
      $name  = $tag['name'];
      $value = ' value="'.htmlspecialchars((isset($tag['value']) ? $tag['value'] : $name)).'"';
      $title = ' title="'.(isset($tag['title']) ? $tag['title'] : $name).'"';
      $id    = isset($tag['id']) ? ' id="'.$tag['id'].'"' : '';
      $class = ' class="'.$tag['class'].'"';
      $data = '';
      if (isset($tag['data'])) {
        $dataArray = $tag['data'];
        if (!is_array($dataArray)) {
          $dataArray = array('value' => $dataArray);
        }
        foreach ($dataArray as $key => $dataValue) {
          $key = self::camelCaseToDashes($key);
          $data .= ' data-'.$key.'="'.htmlspecialchars($dataValue).'"';
        }
      }
      switch ($type) {
      case 'resetbutton':
      case 'submitbutton':
      case 'button':
        if ($type == 'resetbutton') {
          $buttonType = 'reset';
        } else if ($type == 'submitbutton') {
          $buttonType = 'submit';
        } else {
          $buttonType = 'button';
        }
        $style = isset($tag['style']) ? ' style="'.$tag['style'].'"' : '';
        $html .= ''
          .'<button type="'.$buttonType.'" '.$class.$value.$title.$data.$id.$style.'>';
        if (isset($tag['image'])) {
          $html .= ''
            .'<img class="svg" '
            .(isset($tag['id']) ? ' id="'.$tag['id'].'-img" ' : ' ')
            .'src="'.$tag['image'].'" alt="'.$name.'" />';
        } else {
          $html .= $name;
        }
        $html .= '</button>
';
        break;
      case 'input':
        if (isset($tag['image'])) {
          $style = 'background:url(\''.$tag['image'].'\') no-repeat center;'.$style;
          $name  = '';
        }                 
        $style = isset($tag['style']) ? ' style="'.$tag['style'].'"' : '';
        $name  = $name != '' ? ' name="'.htmlspecialchars($name).'"' : '';
        $html .= ''
          .'<input type="button" '.$class.$value.$title.$data.$id.$style.$name.'/>
';
        break;
      case 'option':
        $selected = '';
        if (isset($tag['selected']) && $tag['selected'] !== false) {
          $selected = ' selected="selected"';
        }
        $html .= ''
          .'<option '.$class.$value.$title.$data.$id.$style.$selected.'>'.$name.'</option>
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
<form class="cafevdb-control" id="$controlid" method="post" action="">
  <input type="submit" name="Projects" value="$value" title="$title"/>
  <input type="hidden" name="Template" value="projects"/>
  $headervisibility
</form>

__EOT__;
      break;

    case 'all':
      $value = L::t("Display all Musicians");
      $title = L::t("Display all musicians stored in the data-base, with detailed facilities for filtering and sorting.");
      $form =<<<__EOT__
<form class="cafevdb-control" id="$controlid" method="post" action="">
  <input type="submit" value="$value" title="$title"/>
  <input type="hidden" name="Template" value="all-musicians"/>
  $headervisibility
</form>

__EOT__;
      break;

    case 'email':
      $title = L::t("Mass-email form, use with care. Mass-emails will be logged. Recipients will be specified by the Bcc: field in the header, so the recipients are undisclosed to each other.");
      $form =<<<__EOT__
<form class="cafevdb-control" id="$controlid" method="post" action="">
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
<form class="cafevdb-control" id="$controlid" method="post" action="">
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
      $opclass  = 'pme-view';
      $title = L::t("The currently active project.");
      $form =<<<__EOT__
<form class="cafevdb-control" id="$controlid" method="post" action="">
  <input type="submit" name="Project" value="$project" title="$title"/>
  <input type="hidden" name="DisplayClass" value="Projects"/>
  <input type="hidden" name="Template" value="projects"/>
  <input type="hidden" name="Project" value="$project"/>
  <input type="hidden" name="ProjectId" value="$projectId"/>
  <input type="hidden" name="$opname" value="$opwhat" class="$opclass"/>
  $headervisibility
</form>

__EOT__;
      break;

    case 'detailed':
      $value = L::t("Instrumentation");
      $title = L::t("Detailed display of all registered musicians for the selected project. The table will allow for modification of personal data like email, phone, address etc.");
      $form =<<<__EOT__
<form class="cafevdb-control" id="$controlid" method="post" action="">
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
<form class="cafevdb-control" id="$controlid" method="post" action="">
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
<form class="cafevdb-control" id="$controlid" method="post" action="">
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
<form class="cafevdb-control" id="$controlid" method="post" action="">
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
<form class="cafevdb-control" id="$controlid" method="post" action="">
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
<form class="cafevdb-control" id="$controlid" method="post" action="">
  <input type="submit" value="$value" title="$title" />
  <input type="hidden" name="ProjectId" value="$projectId" />
  <input type="hidden" name="Project"   value="$project" />
  <input type="hidden" name="Template"  value="project-instruments" />
  <input type="hidden" name="Action"    value="transfer-instruments" />
  $headervisibility
</form>

__EOT__;
      break;

    case 'insurances':
      $value = L::t("Insurances");
      $title = L::t("Display a table with an overview about the current state of the member's instrument insurances.");
      $form =<<<__EOT__
<form class="cafevdb-control" id="$controlid" method="post" action="">
  <input type="submit" value="$value" title="$title"/>
  <input type="hidden" name="Template" value="instrument-insurance"/>
  $headervisibility
</form>

__EOT__;
      break;

    case 'debitmandates':
      $value = L::t("Debit Mandates");
      $title = L::t("Display a table with an overview over all SEPA debit mandates.");
      $form =<<<__EOT__
<form class="cafevdb-control" id="$controlid" method="post" action="">
  <input type="submit" value="$value" title="$title"/>
  <input type="hidden" name="Template" value="sepa-debit-mandates"/>
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
  const DATEMASK = "Y-m-d H:i:s"; /**< Something understood by mySQL. */

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
   *
   * @bug The entire MySQL misery needs to be converted to the new PDO stuff.
   */
  public static function connect($opts, $die = true, $silent = false)
  {
    // Open a new connection to the given data-base.
    $handle = @mysql_connect($opts['hn'], $opts['un'], $opts['pw'], true);
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

  public static function error($handle = false)
  {
    if ($handle !== false) {
      return @mysql_error($handle);
      
    } else {
      return @mysql_error();
    }
  }

  public static function query($query, $handle = false, $die = false, $silent = false)
  {
    if (false && Util::debugMode()) {
      // NOPE, emit stuff before headers are sent.
      echo '<HR/><PRE>'.htmlspecialchars($query).'</PRE><HR/><BR>';
    }
    if ($handle) {
      if (($result = @mysql_query($query, $handle)) === false) {
        $err = @mysql_error($handle);
      }
    } else {
      if (($result = @mysql_query($query)) === false) {
        $err = @mysql_error();
      }
    }
    if ($result === false) {
      Util::error('mysql_query() failed: "'.$err.'", query: "'.$query.'"', $die, $silent);
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

  public static function changedRows($handle = false, $die = true, $silent = false)
  {
    return mysql_affected_rows($handle);
  }

  public static function newestIndex($handle = false, $die = true, $silent = false)
  {
    return mysql_insert_id($handle);
  }

  public static function fetch(&$res, $type = MYSQL_ASSOC)
  {
    $result = @mysql_fetch_array($res, $type);
    if (Util::debugMode('query')) {
      //print_r($result);
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

  /**Generate a select from a join from a descriptive array structure.
   *
   * $joinStructure = array(
   *   'JoinColumnName' => array(
   *     'table' => TABLE,
   *     'column' => ORIGINALNAME,
   *     'join' => array(
   *       'type' => 'INNER'|'LEFT' (don't know if OUTER and RIGHT could work ...)
   *       'condition' => STRING sql condition, must be there on first joined field
   *     ),
   *  ...
   *
   * Example:
   * 
   * $viewStructure = array(
   *   'MusikerId' => array(
   *     'table' => 'Musiker',
   *     'column' => 'Id',
   *     // join condition need not be here
   *     'join' => array('type' => 'INNER')
   *     ),
   *   'Instrument' => array(
   *     'table' => 'Besetzungen',
   *     'column' => true,
   *     'join' => array(
   *       'type' => 'INNER',
   *       // one and only one of the fields need to provide the join conditions,
   *       'condition' => ('`Musiker`.`Id` = `Besetzungen`.`MusikerId` '.
   *                     'AND '.
   *                     $projectId.' = `Besetzungen`.`ProjektId`')
   *       ),                                  
   *     ),
   *
   * The left-most join table is always the table of the first element
   * from $joinStructure.
   */
  public static function generateJoinSelect($joinStructure)
  {
    $bt = '`';
    $dot = '.';
    $ind = '  ';
    $nl = '
';
    $firstTable = reset($joinStructure);
    if ($firstTable == false) {
      return false;
    } else {
      $firstTable = $firstTable['table'];
    }
    $join = $ind.'FROM '.$bt.$firstTable.$bt.$nl;
    $select = 'SELECT'.$nl;
    foreach($joinStructure as $joinColumn => $joinedColumn) {
      $table = $joinedColumn['table'];
      if (!isset($joinedColumn['column']) || $joinedColumn['column'] === true) {
        $name = $joinColumn;
        $as = '';
      } else {
        $name = $joinedColumn['column'];
        $as = ' AS '.$bt.$joinColumn.$bt;
      }
      $select .= 
        $ind.$ind.
        $bt.$joinedColumn['table'].$bt.$dot.$bt.$name.$bt.
        $as.
        ','.$nl;
      if (isset($joinedColumn['join']['condition'])) {
        $table = $joinedColumn['table'];
        $type = $joinedColumn['join']['type'];
        $cond = $joinedColumn['join']['condition'];
        $join .=
          $ind.$ind.
          $type.' JOIN '.$bt.$table.$bt.$nl.
          $ind.$ind.$ind.'ON '.$cond.$nl;
      }
    }
    return rtrim($select, "\n,").$nl.$join;
  }
};

/*
 * Local Variables: ***
 * c-basic-offset: 2 ***
 * End: ***
 */

} // namespace

?>
