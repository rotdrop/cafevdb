<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Legacy\PME;

use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\DBALException;

use OCA\CAFEVDB\Database\Connection;
use OCA\CAFEVDB\Common\Util;

/**
 * Override phpMyEdit to use Doctrine DBAL.
 */
class PHPMyEdit extends \phpMyEdit
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var Connection */
  private $connection;

  private $defaultOptions;

  private $affectedRows = 0;
  private $errorCode = 0;
  private $errorInfo = null;
  private $overrideOptions;

  /**Override constructor, delay most of the actual work to the
   **execute() method.
   *
   * @param \OCA\CAFEVDB\Database\Connection $connection
   *
   * @param \OCA\CAFEVDB\Legacy\PME\IOptions $options
   *
   * We do also some construction thing s.t. add_operation() and
   * friends does something useful.
   */
  public function __construct(Connection $connection, IOptions $options)
  {
    $this->dbh = $connection;
    $this->l = l10n;

    $this->overrideOptions = [
      'dbh' => $this->connection,
      'dbp' => '',
      'execute' => false,
    ];
    $this->defaultOptions = $options->getArrayCopy();
    if (isset($options['cgi']['prefix']['sys'])) {
      $this->cgi = $option['cgi'];
      $this->operation = $this->get_sys_cgi_var('operation');
    }
    if (isset($options['options'])) {
      $this->options = $options['options'];
    }
    $this->labels = $this->make_language_labels($options['language']?:null);
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
  public function execute($opts)
  {
    $opts = Util::arrayMergeRecursive($this->defaultOptions, $opts, $this->overrideOptions);
    parent::__construct($opts); // oh oh
    parent::execute();
  }

  public function sql_connect() {
    // do nothing, we only work with already open connections.
  }

  public function sql_disconnect() {
    // do nothing, we only work with already open connections.
  }

  function resultValid($stmt)
  {
    return is_object($stmt);
  }

  function dbhValid() {
    return is_object($this->dbh);
  }

  function sql_fetch(&$stmt, $type = 'a')
  {
    if (!$this->resultValid($stmt)) {
      return false;
    }
    $type = $type === 'n' ? FetchMode::NUMERIC : FetchMode::ASSOCIATIVE;
    return $stmt->fetch(type);
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
    try {
      $stmt = $this->dbh->executeQuery($queryString);
      $this->affectedRows = $stmt->rowCount();
      $this->errorCode = $stmt->errorCode();
      $this->errorInfo = $stmt->errorInfo();
    } catch (DBALException $e) {
      $this->errorCode = $t->getCode();
      $this->errorInfo = $t->getMessage();
      return false;
    }
    return $stmt;
  }

  public function make_language_labels($lang)
  {
    if ($this->labels && (isset($this->currentLanguage) || $this->currentLanguage == $lang)) {
      return $this->labels;
    }
    $this->currentLanguage = $lang?:$this->get_server_var('HTTP_ACCEPT_LANGUAGE');
    return parent::make_language_labels($this->currentLanguage);
  }

  /**Decode the record idea from the CGI data, return -1 if none
   * found.
   */
  public function getCGIRecordId()
  {
    $key = 'rec';
    $recordId = $this->get_sys_cgi_var($key, -1);
    if ($recorId > 0) {
      return $recordId;
    }
    $opRecord = $this->get_sys_cgi_var('operation');
    $operation = parse_url($opRecord, PHP_URL_PATH);
    $opArgs    = [];
    parse_str(parse_url($opRecord, PHP_URL_QUERY), $opArgs);
    $recordKey = $this->cgi['prefix']['sys'].$key;
    $recordId = $opArgs[$recordKey]?: -1;
    return $recordId > 0 ? $recordId : -1;
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
