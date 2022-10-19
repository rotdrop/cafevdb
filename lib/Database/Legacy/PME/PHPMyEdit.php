<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Database\Legacy\PME;

use DateTime;
use Exception;
use ReflectionClass;
use RuntimeException;

use phpMyEdit as LegacyPHPMyEdit;

use OCP\IRequest;
use OCP\ILogger;
use OCP\IL10N;
use OCP\IDateTimeZone;
use OCP\IDateTimeFormatter;

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
class PHPMyEdit extends LegacyPHPMyEdit
{
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  /** @var Connection */
  private $connection;

  /** @var RequestParameterService */
  private $request;

  /** @var IDateTimeZone */
  private $dateTimeZone;

  /** @var IDateTimeFormatter */
  private $dateTimeFormatter;

  private $defaultOptions;

  private $affectedRows = 0;
  private $errorCode = 0;
  private $errorInfo = null;
  private $overrideOptions;

  private $debug;

  /**
   * @var array<int, array>
   *
   * One entry for each query of the following format:
   * ```
   * [
   *   'query' => STRING_OF_SQL_COMMANDS,
   *   'affectedRows' => NUM_ROWS,
   *   'errorCode' => ERROR_CODE,
   *   'errorInfo' => ERROR_INFO,
   *   'duration' => TIME_IN_MS
   * ]
   * ```
   */
  private $queryLog;

  private $disabledLogTable;

  /** @var array Overrides for the translation table. */
  private $labelOverride;

  /** @var string Hash of most recent query */
  private $queryHash;

  // phpcs:disable Squiz.Commenting.FunctionComment.MissingParamTag
  /**
   * Override constructor, delay most of the actual work to the
   * execute() method.
   *
   * @param EntityManager $entityManager
   *
   * @param IOptions $options
   *
   * We do also some construction thing s.t. add_operation() and
   * friends does something useful.
   */
  public function __construct(
    EntityManager $entityManager,
    IOptions $options,
    RequestParameterService $request,
    ILogger $logger,
    IL10N $l10n,
    IDateTimeZone $dateTimeZone,
    IDateTimeFormatter $dateTimeFormatter,
  ) {
    $this->entityManager = $entityManager;
    $this->connection = $this->entityManager->getConnection();
    if (empty($this->connection)) {
      $loggedIn = \OC::$server->query(\OCP\IUserSession::class)->isLoggedIn();
      throw new RuntimeException('Database connection is empty , user logged in: ' . (int)$loggedIn);
    }
    $this->dbh = $this->connection;
    $this->request = $request;
    $this->logger = $logger;
    $this->l = $l10n;
    $this->dateTimeZone = $dateTimeZone;
    $this->dateTimeFormatter = $dateTimeFormatter;
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
  // phpcs:enable

  /**
   * @param array $options
   *
   * @return void
   */
  public function setOptions(array $options):void
  {
    $options = $this->defaultOptions = Util::arrayMergeRecursive($this->defaultOptions, $options);

    if (isset($options['options'])) {
      $this->options = $options['options'];
    }

    $file = (new ReflectionClass(parent::class))->getFileName();

    // Creating directory variables
    $this->dir['root'] = dirname(realpath($file))
                       . (strlen(dirname(realpath($file))) > 0 ? '/' : '');
    $this->dir['lang'] = $this->dir['root'].'lang/';

    // Needs lang path
    $this->labels = $this->make_language_labels($options['language']?:null);

    if (isset($options['debug'])) {
      $this->debug = $options['debug'];
    }

    // after lang initialization
    if (isset($options['cgi']['prefix']['sys'])) {
      $this->cgi = $options['cgi'];
      $this->operation = $this->get_sys_cgi_var('operation');
      $this->determineOperation();
    }
  }

  /** @return void */
  protected function determineOperation():void
  {
    $this->saveadd              = $this->get_sys_cgi_var('saveadd');
    $this->moreadd              = $this->get_sys_cgi_var('moreadd');
    $this->applyadd             = $this->get_sys_cgi_var('applyadd');
    $this->canceladd    = $this->get_sys_cgi_var('canceladd');
    $this->savechange   = $this->get_sys_cgi_var('savechange');
    $this->morechange   = $this->get_sys_cgi_var('morechange');
    $this->cancelchange = $this->get_sys_cgi_var('cancelchange');
    $this->reloadchange = $this->get_sys_cgi_var('reloadchange');
    $this->savecopy             = $this->get_sys_cgi_var('savecopy');
    $this->applycopy    = $this->get_sys_cgi_var('applycopy');
    $this->cancelcopy   = $this->get_sys_cgi_var('cancelcopy');
    $this->savedelete   = $this->get_sys_cgi_var('savedelete');
    $this->canceldelete = $this->get_sys_cgi_var('canceldelete');
    $this->reloaddelete = $this->get_sys_cgi_var('reloaddelete');
    $this->reloadcopy   = $this->get_sys_cgi_var('reloadcopy');
    $this->cancelview   = $this->get_sys_cgi_var('cancelview');
    $this->reloadview   = $this->get_sys_cgi_var('reloadview');

    // determine the initial operation from the status of the submitted buttons

    // Save/More Button - database operations
    if ($this->label_cmp($this->saveadd, 'Save')
        || $this->label_cmp($this->savecopy, 'Save')) {
      $this->operation = $this->labels[$this->label_cmp($this->saveadd, 'Save') ? 'Add' : 'Copy'];
    } elseif ($this->label_cmp($this->applyadd, 'Apply')
            || $this->label_cmp($this->applycopy, 'Apply')) {
      $this->operation = $this->labels[$this->label_cmp($this->applyadd, 'Apply') ? 'Add' : 'Copy'];
    } elseif ($this->label_cmp($this->moreadd, 'More')) {
      $this->operation = $this->labels['Add']; // to force add operation
    } elseif ($this->label_cmp($this->savechange, 'Save')) {
      $this->operation = $this->labels['Change'];
    } elseif ($this->label_cmp($this->morechange, 'Apply')) {
      $this->operation = $this->labels['Change']; // to force change operation
    } elseif ($this->label_cmp($this->savedelete, 'Delete')) {
      $this->operation = $this->labels['Delete']; // force view operation.
    } elseif ($this->label_cmp($this->reloadview, 'Reload')) {
      $this->operation = $this->labels['View']; // force view operation.
    } elseif ($this->label_cmp($this->reloadchange, 'Reload')) {
      $this->operation = $this->labels['Change']; // to force change operation
    } elseif ($this->label_cmp($this->reloaddelete, 'Reload')) {
      $this->operation = $this->labels['Delete']; // to force delete operation
    } elseif ($this->label_cmp($this->reloadcopy, 'Reload')) {
      $this->operation = $this->labels['Copy']; // to force copy operation
    }

    $this->logDebug('NULL OPERATION ' . (int)$this->null_operation());
    $this->logDebug('COPY OPERATION ' . (int)$this->copy_operation());
    $this->logDebug('ADD OPERATION ' . (int)$this->add_operation());
    $this->logDebug('CHANGE OPERATION ' . (int)$this->change_operation());
    $this->logDebug('VIEW OPERATION ' . (int)$this->view_operation());
    $this->logDebug('LIST OPERATION ' . (int)$this->list_operation());
  }

  /** @return array */
  public function getOptions():array
  {
    return $this->defaultOptions;
  }

  /**
   * Enable or disable writing to the change-log table.
   *
   * @param bool $enable Flag value.
   *
   * @return void
   */
  public function setLogging(bool $enable):void
  {
    if ($enable) {
      if (empty($this->logtable)) {
        $this->logtable = $this->disabledLogTable;
      }
    } elseif (!empty($this->logtable)) {
      $this->disabledLogTable = $this->logtable;
      $this->logtable = null;
    }
  }

  /**
   * Enable or disabled debugging.
   *
   * @param bool $enable Flag value.
   *
   * @return void
   */
  public function setDebug(bool $enable):void
  {
    $this->debug = $enable;
    if (isset($this->defaultOptions['debug'])) {
      $this->defaultOptions['debug'] = $enable;
    }
  }

  /**
   * Forward transaction support of underlying Doctrine\Dbal
   * connection.
   *
   * @return void
   */
  public function beginTransaction():void
  {
    $this->entityManager->beginTransaction();
  }

  /**
   * Forward transaction support of underlying Doctrine\Dbal
   * connection.
   *
   * @return void
   */
  public function commit():void
  {
    $this->entityManager->commit();
  }

  /**
   * Forward transaction support of underlying Doctrine\Dbal
   * connection.
   *
   * @return void
   */
  public function rollBack():void
  {
    $this->entityManager->rollback();
  }

  /**
   * Override the given label text with the provided override
   * label. The given override will be translated by the standard
   * cloud translator.
   *
   * @param string $label
   *
   * @param string $override
   *
   * @return void
   */
  public function overrideLabel(string $label, string $override):void
  {
    $this->labelOverride[$label] = $this->l->t($override);
    $this->labels = array_merge($this->labels, $this->labelOverride);
  }

  /**
   * {@inheritdoc}
   *
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
   * {@inheritdoc}
   *
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
  public function export($cellFilter = false, $lineCallback = false, $css = 'noescape', $opts = [])
  {
    $opts = Util::arrayMergeRecursive($this->defaultOptions, $opts, $this->overrideOptions);
    if (isset($opts['debug'])) {
      $this->debug = $opts['debug'];
    }
    $opts['execute'] = false;
    parent::__construct($opts); // oh oh
    parent::export($cellFilter, $lineCallback, $css);
  }

  // phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

  /** {@inheritdoc} */
  public function sql_connect()
  {
    // do nothing, we only work with already open connections.
  }

  /** {@inheritdoc} */
  public function sql_disconnect()
  {
    // do nothing, we only work with already open connections.
  }

  /** {@inheritdoc} */
  public function resultValid(&$stmt)
  {
    return ($stmt instanceof ResultStatement);
    //return is_object($stmt);
  }

  /** {@inheritdoc} */
  public function dbhValid()
  {
    return ($this->dbh instanceof Connection);
    //return is_object($this->dbh);
  }

  /** {@inheritdoc} */
  public function sql_fetch(&$stmt, $type = 'a')
  {
    if (!$this->resultValid($stmt)) {
      return false;
    }
    $type = $type === 'n' ? FetchMode::NUMERIC : FetchMode::ASSOCIATIVE;
    $result = $stmt->fetch($type);
    // Work around bug https://jira.mariadb.org/browse/MDEV-27323
    if ($result !== false
        && $type == FetchMode::ASSOCIATIVE
        && $this->queryHash == $this->generatedQueryHash
        && !empty($this->columnAliases)) {
      $missingColumns = array_diff($this->columnAliases, array_keys($result));
      if (!empty($missingColumns)) {
        // We assume that this is caused by an optimization bug and that
        // either the corresponding qfNN_idx column or the corresponding
        // non-..._idx column is there and contains the correct data
        $this->logError('Potential MariaDB missing-columns bug (https://jira.mariadb.org/browse/MDEV-27323): ' . print_r($missingColumns, true));
        $postfix = '_idx';
        foreach ($missingColumns as $column) {
          $dataColumn = strrpos($column, $postfix) === false ? $column . $postfix : substr($column, -strlen($postfix));
          if (empty($result[$dataColumn])) {
            $this->logError('MariaDB bug https://jira.mariadb.org/browse/MDEV-27323): unable to reconstruct data for column ' . $column);
          }
          $result[$column] = $result[$dataColumn];
        }
      }
    }

    return $result;
  }

  /** {@inheritdoc} */
  public function sql_free_result(&$stmt)
  {
    if (!$this->resultValid($stmt)) {
      return false;
    }
    return $stmt->closeCursor();
  }

  /** {@inheritdoc} */
  public function sql_affected_rows()
  {
    if (!$this->dbhValid()) {
      return 0;
    }
    return $this->affectedRows;
  }

  /** {@inheritdoc} */
  public function sql_field_len(&$stmt, $field)
  {
    return 65535-1;
  }

  /** {@inheritdoc} */
  public function sql_insert_id()
  {
    if (!$this->dbhValid()) {
      return 0;
    }
    return $this->dbh->lastInsertId();
  }

  /** {@inheritdoc} */
  public function myquery($query, $line = 0, $debug = false)
  {
    $this->queryHash = md5($query);
    if ($debug || $this->debug) {
      $this->logInfo("DEBUG QUERY: ".$query, [], 2);
    }
    $logEntry = [
      'query' => $query,
    ];
    $startTime = hrtime(true); // [ns]
    try {
      $stmt = $this->dbh->executeQuery($query);
      $endTime = hrtime(true);
      $this->affectedRows = $stmt->rowCount();
      $this->errorCode = $stmt->errorCode();
      $this->errorInfo = $stmt->errorInfo();
      $logEntry['affectedRows'] = $this->affectedRows;
      $logEntry['errorCode'] = $this->errorCode;
      $logEntry['errorInfo'] = $this->errorInfo;
      $logEntry['duration'] = ($endTime - $startTime) / 1e6; // [ms]
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

  /** {@inheritdoc} */
  public function error($message, $additionalInfo = '')
  {
    $message .= !empty($additionalInfo) ? ' ('.$additionalInfo.')' : '';
    if (!empty($this->errorInfo)) {
      $message .= ': '.$this->errorInfo.' ('.$this->errorCode.')';
    }
    throw new Exception($message, $this->errorCode?:-1, $this->exception);
  }

  /** {@inheritdoc} */
  public function make_language_labels($lang)
  {
    if ($this->labels && (isset($this->currentLanguage) || $this->currentLanguage == $lang)) {
      return $this->labels;
    }
    $this->currentLanguage = $lang ?: $this->get_server_var('HTTP_ACCEPT_LANGUAGE');
    return parent::make_language_labels($this->currentLanguage);
  }

  /**
   * {@inheritdoc}
   *
   * Override parent::get_cgi_var to use RequestParameterService
   */
  public function get_cgi_var($name, $defaultValue = null)
  {
    if (isset($this) && isset($this->cgi['overwrite'][$name])) {
      return $this->cgi['overwrite'][$name];
    }

    $var = $this->request->getParam($name, $defaultValue);

    if ($var === null && isset($this->cgi['append'][$name])) {
      return $this->cgi['append'][$name];
    }
    return $var;
  }

  /**
   * {@inheritdoc}
   *
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
   *
   * @return array
   */
  public function getCGIRecordId():array
  {
    list(
      // 'operation' => $operation,
      'rec' => $rec,
      // 'groupby_rec' => $groupbyRec,
    ) = $this->recordIdFromRequest();

    return $rec ?? [];
  }

  /**
   * Get prefixed name for control variables.
   *
   * @param string $suffix
   *
   * @return string
   */
  public function cgiSysName(string $suffix = ''):string
  {
    return $this->cgi['prefix']['sys'].$suffix;
  }

  /**
   * Get prefixed name for data variables, i.e. table-field data.
   *
   * @param string $suffix
   *
   * @return string
   */
  public function cgiDataName(string $suffix = ''):string
  {
    return $this->cgi['prefix']['data'].$suffix;
  }

  /**
   * {@inheritdoc}
   *
   * Create a Unix time-stamp from user-input
   *
   * Strategy: if the user-input does not contain a time, then convert to UTC
   * time at midnight. Otherwise adjust by the time-zone offset of the user.
   */
  protected function makeTimeStampFromUser($userInput)
  {
    // Ok, the easiest way is probably to simply add the offset in seconds.
    $timeStamp = parent::makeTimeStampFromUser($userInput);

    if ($timeStamp % (24 * 60 * 60) == 0 && !preg_match("/\d{1,2}\:\d{1,2}/", $userInput)) {
      // assume date-only
      return $timeStamp;
    }

    // The following is wrong, as the user-input was treated as coming from UTC
    $timeZone = $this->dateTimeZone->getTimeZone();
    $dateTime = (new DateTime)->setTimezone($timeZone)->setTimestamp($timeStamp);

    $modTimeStamp = $timeStamp - $timeZone->getOffset($dateTime);
    $modDateTime = (new DateTime)->setTimezone($timeZone)->setTimestamp($modTimeStamp);

    $this->logDebug('ORIG / MOD ' . print_r($dateTime, true) . ' / ' . print_r($modDateTime, true));
    $this->logDebug('ORIG / MOD ' . $timeStamp . ' / ' . $modTimeStamp);

    if ($timeZone->getOffset($dateTime) != $timeZone->getOffset($modDateTime)) {
      $this->logError('Timezone adjustment for failed for user-input ' . $userInput);
    }

    return $modTimeStamp;
  }

  /**
   * {@inheritdoc}
   *
   * Create a Unix time-stamp from the data-base.
   *
   * Strategy: if the date/time value in the data base does contain a time,
   * then assume UTC and just convert to a time-stamp.
   *
   * If the value in the data-base does not contain a time then adjust to
   * 00:00 local-time by shifting with the time-zone offset of the local user.
   */
  protected function makeTimeStampFromDatabase($databaseValue)
  {
    $timeStamp = parent::makeTimeStampFromDatabase($databaseValue);

    if ($timeStamp % (24 * 60 * 60) != 0 || preg_match("/\d{1,2}\:\d{1,2}/", $databaseValue)) {
      // assume UTC with time-stamp
      return $timeStamp;
    }

    $timeZone = $this->dateTimeZone->getTimeZone();
    $dateTime = (new DateTime)->setTimezone($timeZone)->setTimestamp($timeStamp);

    $modTimeStamp = $timeStamp + $timeZone->getOffset($dateTime);
    $modDateTime = (new DateTime)->setTimezone($timeZone)->setTimestamp($modTimeStamp);

    $this->logDebug('ORIG / MOD ' . print_r($dateTime, true) . ' / ' . print_r($modDateTime, true));
    $this->logDebug('ORIG / MOD ' . $timeStamp . ' / ' . $modTimeStamp);

    if ($timeZone->getOffset($dateTime) != $timeZone->getOffset($modDateTime)) {
      $this->logError('Timezone adjustment failed for data-base input ' . $databaseValue);
    }

    return $modTimeStamp;
  }

  /**
   * {@inheritdoc}
   *
   * Create a time-string for user display from a data-base value
   */
  public function makeUserTimeString($k, $row)
  {
    $value = '';
    $data = $row["qf$k"."_timestamp"];
    switch ($data) {
      case '':
      case 0:
      case '0000-00-00':
        // Invalid date and time
        break;
      default:
        if (!is_numeric($data)) {
          // Convert whatever is contained in the timestamp field to
          // seconds since the epoch.
          $data = $this->makeTimeStampFromDatabase($data);
        }
        $timeStamp = intval($data);
        $dateFormat = $timeFormat = $this->fdd[$k]['datetimeformat'] ?? false;
        $dateFormat = $this->fdd[$k]['dateformat'] ?? false;
        $timeFormat = $this->fdd[$k]['timeformat'] ?? false;

        if (!empty($dateFormat) && !empty($timeFormat)) {
          if ($dateFormat === true && $timeFormat === true) {
            return $this->dateTimeFormatter->formatDateTime($timeStamp);
          } elseif ($timeFormat === true) {
            return $this->dateTimeFormatter->formatDateTime($timeStamp, $dateFormat);
          } else {
            return $this->dateTimeFormatter->formatDateTime($timeStamp, $dateFormat, $timeFormat);
          }
        } elseif (!empty($dateFormat)) {
          return $dateFormat === true
            ? $this->dateTimeFormatter->formatDate($timeStamp)
            : $this->dateTimeFormatter->formatDate($timeStamp, $dateFormat);
        } elseif (!empty($timeFormat)) {
          return $timeFormat === true
            ? $this->timeTimeFormatter->formatTime($timeStamp)
            : $this->timeTimeFormatter->formatTime($timeStamp, $timeFormat);
        } elseif (!empty($this->fdd[$k]['datemask'])) {
          return date($this->fdd[$k]['datemask'], $timeStamp);
        } elseif (!empty($this->fdd[$k]['strftimemask'])) {
          return strftime($this->fdd[$k]['strftimemask'], $timeStamp);
        }
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   *
   * Generate a data-base value from a timestamp.
   */
  protected function timestampToDatabase($timeStamp, $k)
  {
    $dateFormat = $timeFormat = $this->fdd[$k]['datetimeformat'] ?? false;
    $dateFormat = $this->fdd[$k]['dateformat'] ?? false;
    $timeFormat = $this->fdd[$k]['timeformat'] ?? false;
    if ($dateFormat !== false && $timeFormat === false) {
      $format = 'Y-m-d';
    } else {
      $format = 'Y-m-d H:i:s';
    }
    return date($format, $timeStamp);
  }

  /**
   * {@inheritdoc}
   *
   * "register" the date-time-format extension.
   */
  public function col_has_datemask($k)
  {
    return parent::col_has_datemask($k)
      || ($this->fdd[$k]['datetimeformat']
          ?? $this->fdd[$k]['dateformat']
          ?? $this->fdd[$k]['timeformat']
          ?? false);
  }

  /**
   * {@inheritdoc}
   *
   * Handle logging.
   */
  protected function logQuery($operation, $oldvals, $changed, $newvals)
  {
    switch ($operation) {
      case 'insert':
        if (empty($changed)) {
          return;
        }
        $query = sprintf(
          'INSERT INTO %s'
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
        $query = sprintf(
          'INSERT INTO %s'
          .' (updated, user, host, operation, tab, rowkey, col, oldval, newval)'
          .' VALUES (NOW(), "%s", "%s", "%s", "%s", "%s", "%s", "%s", "%s")',
          $this->logtable,
          $this->entityManager->getUserId(),
          addslashes($this->request->getRemoteAddress()),
          $operation,
          addslashes($this->tb),
          addslashes(implode(',', $this->rec)),
          addslashes(implode(',', $changed)),
          addslashes(serialize($changeSetOld)),
          addslashes(serialize($changeSetNew)));
        break;
      case 'delete':
        $query = sprintf(
          'INSERT INTO %s'
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

  /** {@inheritdoc} */
  public function doFetchToolTip($cssClassName, $name, $label = false)
  {
    if ($this->tooltips instanceof \OCA\CAFEVDB\Service\ToolTipsService) {
      $oldDebug = $this->tooltips->debug();
      $this->tooltips->debug(false);
    }
    $result = parent::doFetchToolTip($cssClassName, $name, $label);
    if ($this->tooltips instanceof \OCA\CAFEVDB\Service\ToolTipsService) {
      $this->tooltips->debug($oldDebug);
      if ($oldDebug) {
        $failedKeys = implode(';', $this->tooltips->getFailedKeys());
        if (empty($result)) {
          return '***DEBUG*** '.$this->l->t('Unknown Tooltip for css-classes "%1$s" and name "%2$s" requested, failed keys in detail: %3$s.', [ $cssClassName, $name, $failedKeys ]);
        } else {
          $result .= ' (' . $this->l->t('ToolTip-Key "%1$s", failed keys %2$s', [ $this->tooltips->getLastKey(), $failedKeys ]) . ')';
        }
      }
    }
    return $result;
  }

  /**
   * @return array The query-log array.
   *
   * @see PHPMyEdit::$queryLog
   */
  public function queryLog():array
  {
    return $this->queryLog;
  }

  /**
   * @return void
   *
   * @see PHPMyEdit::$queryLog
   */
  public function clearQueryLog():void
  {
    $this->queryLog = [];
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
