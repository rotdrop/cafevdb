<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016 Claus-Justus Heine <himself@claus-justus-heine.de>
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

  /** Helper class for displaying projects.
   */
  class ProjectPayments
  {
    const CSS_PREFIX = 'cafevdb-page';
    const TABLE = 'ProjectPayments';
    const DEBIT_NOTES = 'DebitNotes';
    const DEBIT_NOTE_DATA = 'DebitNoteData';
    private $scope;
    private $pme;
    private $pme_bare;
    private $execute;
    private $projectId;
    private $projectName;

    public function __construct($execute = true)
    {
      $this->execute = $execute;
      $this->pme = false;
      $this->pme_bare = false;

      $projectId = Util::cgiValue('ProjectId', false);
      $projectName = Util::cgiValue('ProjectName', false);

      if (empty($projectId) && !empty($projectName)) {
        $projectId = Projects::fetchId($projectName);
      } else if (empty($projectName) && !empty($projectId)) {
        $projectName = Projects::fetchName($projectId);
      }

      $this->projectId = $projectId;
      $this->projectName = $projectName;

      Config::init();
    }

    public function deactivate()
    {
      $this->execute = false;
    }

    public function activate()
    {
      $this->execute = true;
    }

    public function execute()
    {
      if ($this->pme) {
        $this->pme->execute();
      }
    }

    public function navigation($enable)
    {
      $this->pme_bare = !$enable;
    }

    public function shortTitle()
    {
      return L::t('Payments for project "%s"', array($this->projectName));
    }

    public function headerText()
    {
      return $this->shortTitle();
    }

    public function display()
    {
      global $debug_query;
      $debug_query = Util::debugMode('query');

      if (Util::debugMode('request')) {
        echo '<PRE>';
        /* print_r($_SERVER); */
        print_r($_POST);
        echo '</PRE>';
      }

      $projectId = $this->projectId;
      $projectName = $this->projectName;

      if (empty($projectName) || empty($projectId)) {
        throw new \InvalidArgumentException('ProjectId and/or name not given.');
      }

      // Inherit a bunch of default options
      $opts = Config::$pmeopts;

      $opts['cgi']['persist'] = array(
        'Template' => 'project-payments',
        'DisplayClass' => 'ProjectPayments',
        'ClassArguments' => array());

      $opts['cgi']['persist']['ProjectName'] = $this->projectName;
      $opts['cgi']['persist']['ProjectId']   = $this->projectId;

      $opts['tb'] = self::TABLE;

      // Name of field which is the unique key
      $opts['key'] = 'Id';

      // Type of key field (int/real/string/date etc.)
      $opts['key_type'] = 'int';

      // Sorting field(s)
      $opts['sort_field'] = array('Id');

      // Number of records to display on the screen
      // Value of -1 lists all records in a table
      $opts['inc'] = -1;

      // Options you wish to give the users
      // A - add,  C - change, P - copy, V - view, D - delete,
      // F - filter, I - initial sort suppressed
      $opts['options'] = 'ACPVDF';

      // Number of lines to display on multiple selection filters
      $opts['multiple'] = '6';

      // Navigation style: B - buttons (default), T - text links, G - graphic links
      // Buttons position: U - up, D - down (default)
      // $opts['navigation'] = 'UG';

      // Display special page elements
      $opts['display'] =  array_merge($opts['display'],
                                      array(
                                        'form'  => true,
                                        'query' => true,
                                        'sort'  => true,
                                        'time'  => true,
                                        'tabs'  => false
                                        ));

      $idIdx = 0;
      $opts['fdd']['Id'] = array(
        'name'     => 'Id',
        'select'   => 'T',
        'options'  => 'AVCPDR', // auto increment
        'maxlen'   => 11,
        'default'  => '0',
        'sort'     => true,
        );

      $instrumentationIdx = count($opts['fdd']);
      $opts['fdd']['InstrumentationId'] = array(
        'name'     => L::t('Musician'),
        'css'      => array('postfix' => ' instrumentation-id'),
        'values'   => array(
          'table'  => "SELECT b.ProjektId AS ProjectId, b.Id, CONCAT(b.Id,': ',m.Vorname,' ',m.Name) AS Name
  FROM Besetzungen b
  LEFT JOIN Musiker m
  ON b.MusikerId = m.Id
  WHERE b.ProjektId = ".$projectId."
  ORDER BY m.Vorname ASC, m.Name ASC",
          'column' => 'Id',
          'description' => 'Name',
          'join'   => '$join_table.Id = $main_table.InstrumentationId'
          ),
        'select'   => 'T',
        'maxlen'   => 40,
        'sort'     => true,
        );

      $opts['fdd']['Amount'] = Config::$opts['money'];
      $opts['fdd']['Amount']['name'] = L::t('Amount');

      $receiptIdx = count($opts['fdd']);
      $opts['fdd']['DateOfReceipt'] = array_merge(
        Config::$opts['date'],
        array(
          'name' => L::t('Date of Receipt'),
          )
        );

      $subjectIdx = count($opts['fdd']);
      $opts['fdd']['Subject'] = array(
        'name' => L::t('Subject'),
        'css'  => array('postfix' => ' subject hide-subsequent-lines'),
        'select' => 'T',
        'textarea' => array('css' => ' subject-text',
                            'rows' => 4,
                            'cols' => 32),
        'display|LF' => array('popup' => 'data'),
        'escape' => true,
        'sort' => true
        );

      $debitIdIdx = count($opts['fdd']);
      $opts['fdd']['DebitNoteId'] =
        array_merge(Config::$opts['datetime'],
                    array('name' => L::t('Direct Debit Date'),
                           'input' => 'R',
                          'input|C' => 'H',
                          'options' => 'LCFVD',
                          'values' => array(
                            'table' => self::DEBIT_NOTES,
                            'column' => 'Id',
                            'description' => 'DateIssued',
                            ),
                          'sort' => true));

      $opts['fdd']['MandateReference'] = array(
        'name' => L::t('Mandate Reference'),
        'input'  => 'R',
        'options' => 'LFVD',
        'css'  => array('postfix' => ' mandate-reference'),
        'input' => 'R',
        'select' => 'T',
        'escape' => true,
        'sort' => true
        );

      $opts['fdd']['DebitMessageId'] = array(
        'name' => L::t('Message-ID'),
        'input'  => 'R',
        'options' => 'LFVD',
        'css'  => array('postfix' => ' message-id hide-subsequent-lines'),
        'input' => 'R',
        'select' => 'T',
        'escape' => true,
        'sort' => true,
        'tooltip' => Config::toolTips('debit-note-email-message-id'),
        'display|LF' => array('popup' => 'data'),
        );

      $opts['triggers']['select']['data'] = function(&$pme, $op, $step, &$row) use ($debitIdIdx, $opts) {
        //error_log('called '.$op.' '.$debitIdIdx);
        //error_log('called '.print_r($row, true));
        if (!empty($row['qf'.$debitIdIdx])) {
          $pme->options = 'LVF';
          if ($op !== 'select' ) {
            throw new \BadFunctionCallException(
              L::t('Payments resulting from direct debit transfers cannot be changed.'),
              PageLoader::PME_ERROR_READONLY
              );
          }
        } else {
          $pme->options = $opts['options'];
        }
      };
      $opts['triggers']['update']['data'] = $opts['triggers']['select']['data'];

      $opts['triggers']['update']['before'][]  = 'CAFEVDB\Util::beforeAnythingTrimAnything';
      $opts['triggers']['insert']['before'][]  = 'CAFEVDB\Util::beforeAnythingTrimAnything';

      $opts['filters'] = 'PMEjoin'.$instrumentationIdx.'.ProjectId = '.$projectId;

      $opts['execute'] = $this->execute;
      $this->pme = new \phpMyEdit($opts);

    }

    /**Just return all associated payments. */
    public static function payments($projectId, $full = true, $handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $query = "SELECT ".($full ? "*" : "`Id`")."
  FROM ".self::TABLE." p
  LEFT JOIN Besetzungen b
    ON b.Id = p.InstrumentationId
  WHERE b.ProjektId = $projectId";

      $result = false;
      $qResult = mySQL::query($query, $handle);
      if ($qResult !== false) {
        $result = array();
        while ($row = mySQL::fetch($qResult)) {
          $result[] = $row;
        }
        mySQL::freeResult($qResult);
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $result;
    }

    /**Just return all associated payments. */
    public static function participantPayments($instrumentationId, $full = true, $handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $query = "SELECT ".($full ? "*" : "`Id`")."
  FROM ".self::TABLE." p
  WHERE p.InstrumentationId = $instrumentationId";

      error_log($query);

      $result = false;
      $qResult = mySQL::query($query, $handle);
      if ($qResult !== false) {
        $result = array();
        while ($row = mySQL::fetch($qResult)) {
          $result[] = $row;
        }
        mySQL::freeResult($qResult);
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $result;
    }

    /**Just return all associated payments. */
    public static function musicianPayments($musicianId, $full = true, $handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $query = "SELECT ".($full ? "*" : "p.`Id`")."
  FROM ".self::TABLE." p
  LEFT JOIN Besetzungen b
    ON b.Id = p.InstrumentationId
  WHERE b.MusikerId = $musicianId";

      //error_log($query);

      $result = false;
      $qResult = mySQL::query($query, $handle);
      if ($qResult !== false) {
        $result = array();
        while ($row = mySQL::fetch($qResult)) {
          $result[] = $row;
        }
        mySQL::freeResult($qResult);
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $result;
    }

    /**Fetch the debit-note payments for the given time interval and
     * sum them up, grouped by instrumentation id.
     *
     * @param[in] string $debitJob One out of membership-fee,
     * insurance, amount, deposit, remaining.
     *
     * @param[in] string $startDate Start-date in a format understood by mySql
     *
     * @param[in] string $endDate End-date in a format understood by mySql
     *
     * @param[in] resource Data-base handle.
     *
     * @return array(array('InstrumentationId' => ID,
     *                     'TotalAmountPaid' => AMOUNT));
     *
     */
    public static function totalsByDebitType($debitJob, $startDate = null, $endDate = null, $handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $query = "SELECT p.InstrumentationId, SUM(p.Amount) AS TotalAmountPaid
  FROM `".self::TABLE."` p
  LEFT JOIN `".self::DEBIT_NOTES."` d
    ON p.DebitNoteId = d.Id
  WHERE d.Job = '".$debitJob."'";

      if (!empty($startDate)) {
        $query .= " AND p.DateOfReceipt >= '".$startDate."'";
      }
      if (!empty($endDate)) {
        $query .= " AND p.DateOfReceipt <= '".$endDate."'";
      }

      $query .= " GROUP BY p.InstrumentationId";
      $query .= " ORDER BY p.InstrumentationId ASC";

      $result = false;
      $qResult = mySQL::query($query, $handle);
      if ($qResult !== false) {
        $result = array();
        while ($row = mySQL::fetch($qResult)) {
          $result[$row['InstrumentationId']] = $row;
        }
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $result;
    }

    /**Fetch the debit-note payments identified by the debit-note's id */
    public static function debitNotePayments($debitNoteId, $handle = false)
    {
      $rows = mySQL::fetchRows(
        self::TABLE, 'DebitNoteId = '.$debitNoteId, 'InstrumentationId ASC', $handle);

      return $rows;
    }

    /**Remove all payments for the associated debit-note. This
     * functions bails out if this is attempted with a debit note
     * which already has been submitted to the bank.
     */
    public static function deleteDebitNotePayments($debitNoteId, $handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $debitNote = DebitNotes::debitNote($debitNoteId, $handle);
      if ($debitNote === false) {
        if ($ownConnection) {
          mySQL::close($handle);
        }
        throw new \RuntimeException(L::t('Debit-note with id %d does not seem to exists.',
                                         array($debitNoteId)));
        return false;
      }
      if (!empty($debitNote['SubmitDate'])) {
        if ($ownConnection) {
          mySQL::close($handle);
        }
        throw new \InvalidArgumentException(
          L::t('Debit note with id %d has already been submitted and therefore will
not be deleted.',
               array($debitNoteId)));
        return false;
      }

      $payments = self::debitNotePayments($debitNoteId, $handle);
      if (empty($payments)) {
        if ($ownConnection) {
          mySQL::close($handle);
        }
        return false; // so what
      }

      $failed = 0;
      foreach($payments as $payment) {
        $query = "DELETE FROM `".self::TABLE."`
 WHERE `Id` = ".$payment['Id']." AND `DebitNoteId` = ".$debitNoteId;
        if (mySQL::query($query, $handle) !== false) {
          mySQL::logDelete(self::TABLE, 'Id', $payment, $handle);
        } else {
          ++$failed;
        }
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $failed === 0;
    }

    public static function recordDebitNotePayments($debitNoteId, $debitNotes, $dueStamp, $handle = false)
    {
      $dueDate = date('Y-m-d', $dueStamp);

      $ids = array();

      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      foreach($debitNotes as $debitNote) {
        $payment = array(
          'InstrumentationId' => $debitNote['InstrumentationId'],
          'Amount' => $debitNote['amount'],
          'DateOfReceipt' => $dueDate,
          'Subject' => implode("\n", $debitNote['purpose']),
          'DebitNoteId' => $debitNoteId,
          'MandateReference' => $debitNote['mandateReference']
          );
        if (mySQL::insert(self::TABLE, $payment, $handle) === false) {
          throw new \Exception(mySQL::error($handle));
          mySQL::close($handle);
          return false;
        }

        $id = mySQL::newestIndex($handle);

        mySQL::logInsert(self::TABLE, $id, $payment, $handle);

        $ids[] = $id;
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $ids;
    }

    public static function recordDebitNoteData($debitNoteId, $fileName, $mimeType, $exportData,
                                               $handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $debitNoteData = array(
        'DebitNoteId' => $debitNoteId,
        'FileName' => $fileName,
        'MimeType' => $mimeType,
        'Data' => Config::encrypt($exportData)
        );

      if (mySQL::insert(self::DEBIT_NOTE_DATA, $debitNoteData, $handle) === false) {
        throw new \Exception(mySQL::error($handle));
        if ($ownConnection) {
          mySQL::close($handle);
        }
        return false;
      }

      $dataId = mySQL::newestIndex($handle);

      $debitNoteData['Data'] = 'ENCRYPTED';
      mySQL::logInsert(self::DEBIT_NOTE_DATA, $dataId, $debitNoteData, $handle);

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $dataId;
    }


    public static function recordDebitNotes($projectId, $job,
                                            $timeStamp, $submissionStamp, $dueStamp,
                                            $calObjIds,
                                            $handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $dateIssued = date('Y-m-d H:i:s', $timeStamp);
      $submission = date('Y-m-d', $submissionStamp);
      $dueDate = date('Y-m-d', $dueStamp);

      $debitNoteFields = array(
        'ProjectId' => $projectId,
        'DateIssued' => $dateIssued,
        'SubmissionDeadline' => $submission,
        'DueDate' => $dueDate,
        'Job' => $job,
        'SubmissionEvent' => $calObjIds[0],
        'SubmissionTask' => $calObjIds[1],
        'DueEvent' => $calObjIds[2],
        );

      if (mySQL::insert(self::DEBIT_NOTES, $debitNoteFields, $handle) === false) {
        throw new \Exception(mySQL::error($handle));
        if ($ownConnection) {
          mySQL::close($handle);
        }
        return false;
      }
      $debitNoteId = mySQL::newestIndex($handle);

      mySQL::logInsert(self::DEBIT_NOTES, $debitNoteId, $debitNoteFields, $handle);

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $debitNoteId;
    }

  }; // class ProjectPayments
}

/*
 * Local Variables: ***
 * c-basic-offset: 2 ***
 * End: ***
 */

?>
