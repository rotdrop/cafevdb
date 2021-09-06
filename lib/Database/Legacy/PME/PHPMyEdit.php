<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Database\Legacy\PME;

use \OCP\IRequest;
use \OCP\ILogger;
use \OCP\IL10N;

use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Driver\ResultStatement;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\FetchMode;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\DBALException;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Connection;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Common\Util;

/**
 * Override phpMyEdit to use OCA\CAFEVDB\Wrapped\Doctrine DBAL.
 */
class PHPMyEdit extends \phpMyEdit
{
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  /** @var Connection */
  private $connection;

  /** @var RequestParameterService */
  private $request;

  private $defaultOptions;

  private $affectedRows = 0;
  private $errorCode = 0;
  private $errorInfo = null;
  private $overrideOptions;

  private $debug;
  private $queryLog;

  private $disabledLogTable;

  /** @var array
   * Overrides for the translation table.
   */
  private $labelOverride;

  /**
   * Override constructor, delay most of the actual work to the
   * execute() method.
   *
   * @param \OCA\CAFEVDB\Database\Connection $connection
   *
   * @param \OCA\CAFEVDB\Database\Legacy\PME\IOptions $options
   *
   * We do also some construction thing s.t. add_operation() and
   * friends does something useful.
   */
  public function __construct(
    EntityManager $entityManager
    , IOptions $options
    , RequestParameterService $request
    , ILogger $logger
    , IL10N $l10n
  )
  {
    $this->entityManager = $entityManager;
    $this->connection = $this->entityManager->getConnection();
    if (empty($this->connection)) {
      throw new \Exception("empty");
    }
    $this->dbh = $this->connection;
    $this->request = $request;
    $this->logger = $logger;
    $this->l = $l10n;
    $this->debug = false;

    $this->overrideOptions = [
      'dbh' => $this->connection,
      'dbp' => '',
      'execute' => false,
    ];
    $this->defaultOptions = [
      'language' => locale_get_primary_language($this->l->getLanguageCode()),
    ];
    $this->setOptions($options->getArrayCopy());
    $this->labelOverride = [];
    $this->queryLog = [];
  }

  public function setOptions(array $options)
  {
    $options = $this->defaultOptions = Util::arrayMergeRecursive($this->defaultOptions, $options);

    if (isset($options['cgi']['prefix']['sys'])) {
      $this->cgi = $options['cgi'];
      $this->operation = $this->get_sys_cgi_var('operation');
    }
    if (isset($options['options'])) {
      $this->options = $options['options'];
    }

    $file = (new \ReflectionClass(parent::class))->getFileName();

    // Creating directory variables
    $this->dir['root'] = dirname(realpath($file))
                       . (strlen(dirname(realpath($file))) > 0 ? '/' : '');
    $this->dir['lang'] = $this->dir['root'].'lang/';

    // Needs lang path
    $this->labels = $this->make_language_labels($options['language']?:null);
    if (isset($options['debug'])) {
      $this->debug = $options['debug'];
    }
  }

  public function getOptions()
  {
    return $this->defaultOptions;
  }

  /**
   * Enable or disable writing to the change-log table.
   *
   * @param bool $enable Flag value.
   */
  public function setLogging(bool $enable)
  {
    if ($enable) {
      if (empty($this->logtable)) {
        $this->logtable = $this->disabledLogTable;
      }
    } else if (!empty($this->logtable)) {
      $this->disabledLogTable = $this->logtable;
      $this->logtable = null;
    }
  }

  /**
   * Enable or disabled debugging.
   *
   * @param bool $enable Flag value.
   */
  public function setDebug(bool $enable)
  {
    $this->debug = $enable;
    if (isset($this->defaultOptions['debug'])) {
      $this->defaultOptions['debug'] = $enable;
    }
  }

  /**
   * Forward transaction support of underlying Doctrine\Dbal
   * connection.
   */
  public function beginTransaction()
  {
    $this->connection->beginTransaction();
  }

  /**
   * Forward transaction support of underlying Doctrine\Dbal
   * connection.
   */
  public function commit()
  {
    $this->connection->commit();
  }

  /**
   * Forward transaction support of underlying Doctrine\Dbal
   * connection.
   */
  public function rollBack()
  {
    $this->connection->rollBack();
  }

  /**
   * Override the given label text with the provided override
   * label. The given override will be translated by the standard
   * cloud translator.
   */
  public function overrideLabel($label, $override)
  {
    $this->labelOverride[$label] = $this->l->t($override);
  }

  /**
   * Call phpMyEdit::execute() with the given options. This function
   * actually calls the constructor of the base-class and overrides
   * its options with the given argument.
   *
   * @param array $opts
   *
   * @bug Calling a CTOR elsewhere is really bad programming style.
   */
  public function execute($opts = [])
  {
    $opts = Util::arrayMergeRecursive($this->defaultOptions, $opts, $this->overrideOptions);
    if (isset($opts['debug'])) {
      $this->debug = $opts['debug'];
    }
    parent::__construct($opts); // oh oh
    $this->labels = array_merge($this->labels, $this->labelOverride);
    parent::execute();
  }


  /**
   * Quick and dirty general export. On each cell a call-back
   * function is invoked with the html-output of that cell.
   *
   * This is just like list_table(), i.e. only the chosen range of
   * data is displayed and in html-display order.
   *
   * @param $cellFilter $line[] = Callback($i, $j, $celldata)
   *
   * @param $lineCallback($i, $line)
   *
   * @param $css CSS-class to pass to cellDisplay().
   */
  function export($cellFilter = false, $lineCallback = false, $css = 'noescape', $opts = [])
  {
    $opts = Util::arrayMergeRecursive($this->defaultOptions, $opts, $this->overrideOptions);
    if (isset($opts['debug'])) {
      $this->debug = $opts['debug'];
    }
    $opts['execute'] = false;
    parent::__construct($opts); // oh oh
    parent::export($cellFilter, $lineCallback, $css);
  }

  public function sql_connect() {
    // do nothing, we only work with already open connections.
  }

  public function sql_disconnect() {
    // do nothing, we only work with already open connections.
  }

  function resultValid(&$stmt)
  {
    return ($stmt instanceof ResultStatement);
    //return is_object($stmt);
  }

  function dbhValid() {
    return ($this->dbh instanceof Connection);
    //return is_object($this->dbh);
  }

  function sql_fetch(&$stmt, $type = 'a')
  {
    if (!$this->resultValid($stmt)) {
      return false;
    }
    $type = $type === 'n' ? FetchMode::NUMERIC : FetchMode::ASSOCIATIVE;
    return $stmt->fetch($type);
  }

  function sql_free_result(&$stmt)
  {
    if (!$this->resultValid($stmt)) {
      return false;
    }
    return $stmt->closeCursor();
  }

  function sql_affected_rows()
  {
    if (!$this->dbhValid()) {
      return 0;
    }
    return $this->affectedRows;
  }

  function sql_field_len(&$stmt, $field)
  {
    return 65535-1;
  }

  function sql_insert_id()
  {
    if (!$this->dbhValid()) {
      return 0;
    }
    return $this->dbh->lastInsertId();
  }

  function myquery($query, $line = 0, $debug = false)
  {
    if (false && ($debug || $this->debug)) {
      $line = intval($line);
      echo '<h4>MySQL query at line ',$line,'</h4>',htmlspecialchars($query),'<hr size="1" />',"\n";
    }
    if ($debug || $this->debug) {
      $this->logInfo("DEBUG QUERY: ".$query, [], 2);
    }
    $logEntry = [
      'query' => $query,
    ];
    $startTime = hrtime(true);
    try {
      $stmt = $this->dbh->executeQuery($query);
      $endTime = hrtime(true);
      $this->affectedRows = $stmt->rowCount();
      $this->errorCode = $stmt->errorCode();
      $this->errorInfo = $stmt->errorInfo();
      $logEntry['affectedRows'] = $this->affectedRows;
      $logEntry['errorCode'] = $this->errorCode;
      $logEntry['errorInfo'] = $this->errorInfo;
      $logEntry['duration'] = ($endTime - $startTime) / 1e6;
      $this->queryLog[] = $logEntry;
    } catch (DBALException $t) {
      $endTime = hrtime(true);
      $this->logException($t);
      throw $t;
      $this->exception = $t;
      $this->errorCode = $t->getCode();
      $this->errorInfo = $t->getMessage();
      $logEntry['affectedRows'] = $this->affectedRows;
      $logEntry['errorCode'] = $this->errorCode;
      $logEntry['errorInfo'] = $this->errorInfo;
      $logEntry['duration'] = ($endTime - $startTime) / 1e6;
      $this->queryLog[] = $logEntry;
      return false;
    }
    return $stmt;
  }

  function error($message, $additional_info = '')
  {
    $message .= !empty($additional_info) ? ' ('.$additional_info.')' : '';
    if (!empty($this->errorInfo)) {
      $message .= ': '.$this->errorInfo.' ('.$this->errorCode.')';
    }
    throw new \Exception($message, $this->errorCode?:-1, $this->exception);
  }

  public function make_language_labels($lang)
  {
    if ($this->labels && (isset($this->currentLanguage) || $this->currentLanguage == $lang)) {
      return $this->labels;
    }
    $this->currentLanguage = $lang ?: $this->get_server_var('HTTP_ACCEPT_LANGUAGE');
    return parent::make_language_labels($this->currentLanguage);
  }

  /**
   * Override parent::get_cgi_var to use RequestParameterService
   */
  public function get_cgi_var($name, $default_value = null)
  {
    if (isset($this) && isset($this->cgi['overwrite'][$name])) {
      return $this->cgi['overwrite'][$name];
    }

    $var = $this->request->getParam($name, $default_value);

    if ($var === null && isset($this->cgi['append'][$name])) {
      return $this->cgi['append'][$name];
    }
    return $var;
  }

  /**
   * Add an entry to the underlying persistent CGI values. The
   * settings will overwrite all previous settings of the same name.
   */
  public function addPersistentCgi($name, $value = null)
  {
    if (is_array($name) && $value === null) {
      foreach ($name as $key => $value) {
        $this->addPersistentCgi($key, $value);
      }
    } else {
      $this->cgi['persist'] .= '&' . http_build_query([ $name => $value ]);
    }
  }

  /**
   * Decode the record idea from the CGI data, return -1 if none
   * found.
   */
  public function getCGIRecordId()
  {
    $key = 'rec';
    $recordId = $this->get_sys_cgi_var($key, []);
    if (!empty($recordId)) {
      return $recordId;
    }
    $opRecord = $this->get_sys_cgi_var('operation');
    $operation = parse_url($opRecord, PHP_URL_PATH);
    $opArgs    = [];
    parse_str(parse_url($opRecord, PHP_URL_QUERY), $opArgs);
    $recordKey = $this->cgi['prefix']['sys'].$key;
    return $opArgs[$recordKey]??[];
  }

  /**
   * Get prefixed name for control variables.
   */
  public function cgiSysName($suffix = '')
  {
    return $this->cgi['prefix']['sys'].$suffix;
  }

  /**
   * Get prefixed name for data variables, i.e. table-field data.
   */
  public function cgiDataName($suffix = '')
  {
    return $this->cgi['prefix']['data'].$suffix;
  }

  /*
   * Handle logging
   */
  protected function logQuery($operation, $oldvals, $changed, $newvals)
  {
    switch($operation) {
    case 'insert':
      if (empty($changed)) {
        return;
      }
      $query = sprintf('INSERT INTO %s'
                       .' (updated, user, host, operation, tab, rowkey, col, oldval, newval)'
                       .' VALUES (NOW(), "%s", "%s", "%s", "%s", "%s", "", "", "%s")',
                       $this->logtable,
                       $this->entityManager->getUserId(),
                       addslashes($this->request->getRemoteAddress()),
                       $operation,
                       addslashes($this->tb),
                       addslashes(implode(',', $this->rec)),
                       addslashes(serialize($newvals)));
      break;
    case 'update':
      if (empty($changed)) {
        return;
      }
      $changeSetOld = [];
      $changeSetNew = [];
      foreach ($changed as $key) {
        $changeSetOld[$key] = $oldvals[$key];
        $changeSetNew[$key] = $newvals[$key];
      }
      $query = sprintf('INSERT INTO %s'
                       .' (updated, user, host, operation, tab, rowkey, col, oldval, newval)'
                       .' VALUES (NOW(), "%s", "%s", "%s", "%s", "%s", "%s", "%s", "%s")',
                       $this->logtable,
                       $this->entityManager->getUserId(),
                       addslashes($this->request->getRemoteAddress()),
                       $operation,
                       addslashes($this->tb),
                       addslashes(implode(',',$this->rec)),
                       addslashes(implode(',',$changed)),
                       addslashes(serialize($changeSetOld)),
                       addslashes(serialize($changeSetNew)));
      break;
    case 'delete':
      $query = sprintf('INSERT INTO %s'
                       .' (updated, user, host, operation, tab, rowkey, col, oldval, newval)'
                       .' VALUES (NOW(), "%s", "%s", "%s", "%s", "%s", "", "%s", "")',
                       $this->logtable,
                       $this->entityManager->getUserId(),
                       addslashes($this->request->getRemoteAddress()),
                       $operation,
                       addslashes($this->tb),
                       addslashes(implode(',', $this->rec)),
                       addslashes(serialize($oldvals)));
      break;
    }
    if (!empty($query)) {
      $this->myquery($query, __LINE__);
    }
  }

  function doFetchToolTip($css_class_name, $name, $label = false)
  {
    if ($this->tooltips instanceof \OCA\CAFEVDB\Service\ToolTipsService) {
      $oldDebug = $this->tooltips->debug();
      $this->tooltips->debug(false);
    }
    $result = parent::doFetchToolTip($css_class_name, $name, $label);
    if ($this->tooltips instanceof \OCA\CAFEVDB\Service\ToolTipsService) {
      $this->tooltips->debug($oldDebug);
      if (empty($result) && $oldDebug) {
        return '***DEBUG*** '.$this->l->t('Unknown Tooltip for css-classes "%1$s" and name "%2$s" requested.', [ $css_class_name, $name ]);
      }
    }
    return $result;
  }

  public function queryLog()
  {
    return $this->queryLog;
  }

  public function clearQueryLog()
  {
    $this->queryLog = [];
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
