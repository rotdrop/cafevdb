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
  class DebitNotes
  {
    const CSS_PREFIX = 'cafevdb-page';
    const TABLE = 'DebitNotes';
    const DATA_TABLE = 'DebitNoteData';
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
      return L::t('Debit-notes for project "%s"', array($this->projectName));
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
      $projectMode = $projectId > 0;

      if (empty($projectName) || empty($projectId)) {
        throw new \InvalidArgumentException('ProjectId and/or name not given.');
      }

      // Inherit a bunch of default options
      $opts = Config::$pmeopts;

      $opts['cgi']['persist'] = array(
        'Template' => 'project-payments',
        'DisplayClass' => 'DebitNotes',
        'ClassArguments' => array());

      $opts['cgi']['persist']['ProjectName'] = $this->projectName;
      $opts['cgi']['persist']['ProjectId']   = $this->projectId;

      $opts['tb'] = self::TABLE;

      // Name of field which is the unique key
      $opts['key'] = 'Id';

      // Type of key field (int/real/string/date etc.)
      $opts['key_type'] = 'int';

      // Sorting field(s)
      $opts['sort_field'] = array('-SubmissionDeadline', '-DateIssued');

      // Number of records to display on the screen
      // Value of -1 lists all records in a table
      $opts['inc'] = -1;

      // Options you wish to give the users
      // A - add,  C - change, P - copy, V - view, D - delete,
      // F - filter, I - initial sort suppressed
      //$opts['options'] = 'VDF';
      $opts['options'] = 'CDFLV';

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
        'name'     => L::t('Id'),
        'select'   => 'T',
        'input'    => 'R',
        'input|AP' => 'RH',
        'options'  => 'AVCPD',
        'maxlen'   => 11,
        'default'  => '0', // auto increment
        'sort'     => true
        );

      $projectIdIdx = count($opts['fdd']);
      $opts['fdd']['ProjectId'] = array(
        'name' => L::t('Project'),
        'input' => ($projectMode ? 'H' : ''),
        'select' => 'D',
        'values' => array(
          'table' => 'Projekte',
          'column' => 'Id',
          'description' => 'Name',
          'groups' => 'Jahr',
          'orderby' => '$table.`Jahr` DESC'
          ),
        'sort' => true,
        );

      $opts['fdd']['DateIssued'] = array_merge(
        Config::$opts['datetime'],
        array(
          'name' => L::t('Time Created'),
          'input' => 'R',
          'tooltip' => Config::toolTips('debit-note-creation-time'),
          )
        );

      $opts['fdd']['SubmissionDeadline'] = array_merge(
        Config::$opts['date'],
        array(
          'name' => L::t('Submission Deadline'),
          'input' => 'R',
          'tooltip' => Config::toolTips('debit-note-submission-deadline'),
          )
        );

      $submitIdx = count($opts['fdd']);
      $opts['fdd']['SubmitDate'] = array_merge(
        Config::$opts['date'],
        array(
          'name' => L::t('Date of Submission'),
          'tooltip' => Config::toolTips('debit-note-date-of-submission'),
          )
        );

      $opts['fdd']['DueDate'] = array_merge(
        Config::$opts['date'],
        array(
          'name' => L::t('Due Date'),
          'input' => 'R',
          'tooltip' => Config::toolTips('debit-note-due-date'),
          )
        );

      $jobIdx = count($opts['fdd']);
      $opts['fdd']['Job'] = array(
        'name' => L::t('Kind'),
        'css'  => array('postfix' => ' debit-note-job'),
        'input' => 'R',
        'select' => 'D',
        'sort' => true,
        'values2' => array(
          'deposit' => L::t('deposit'),
          'remaining' => L::t('remaining'),
          'amount' => L::t('amount'),
          'insurance' => L::t('insurance'),
          'membership-fee' => L::t('membership-fee'),
          ),
        );

      $opts['fdd']['Actions'] = array(
        'name'  => L::t('Actions'),
        'css'   => array('postfix' => ' debit-note-actions'),
        'input' => 'VR',
        'sql' => '`PMEtable0`.`Id`',
        'sort'  => false,
        'php' => function($value, $op, $field, $fds, $fdd, $row, $recordId)
        use ($projectIdIdx, $jobIdx)
        {
          $post = array(
            'DebitNoteId' => $recordId,
            'requesttoken' => \OC_Util::callRegister(),
            'ProjectId' => $row['qf'.$projectIdIdx.'_idx'],
            'ProjectName' => $row['qf'.$projectIdIdx]
            );
          $postEmail = array(
            'EmailTemplate' => self::emailTemplate($row['qf'.$jobIdx])
            );
          $actions = array(
            'download' => array(
              'label' =>  L::t('download'),
              'post' => json_encode($post),
              'title' => Config::toolTips('debit-notes-download')
              ),
            'announce' => array(
              'label' => L::t('announce'),
              'post'  => json_encode(array_merge($post, $postEmail)),
              'title' => Config::toolTips('debit-notes-announce')
              ),
            );
          $html = '';
          foreach($actions as $key => $action) {
            $html .=<<<__EOT__
<li class="nav tooltip-left inline-block ">
  <a class="nav {$key}"
     href="#"
     data-post='{$action['post']}'
     title="{$action['title']}">
{$action['label']}
  </a>
</li>
__EOT__;
          }
          return $html;
        },
        );

      $opts['triggers']['update']['before'][]  = 'CAFEVDB\Util::beforeAnythingTrimAnything';
      $opts['triggers']['insert']['before'][]  = 'CAFEVDB\Util::beforeAnythingTrimAnything';
      $opts['triggers']['delete']['before'][]  = 'CAFEVDB\DebitNotes::removeDebitNote';

      $opts['triggers']['select']['data'][] = function(&$pme, $op, $step, &$row) use ($submitIdx, $opts)  {
        if (empty($row['qf'.$submitIdx])) {
          $pme->options = $opts['options'];
        } else {
          $pme->options = 'LFV';
        }
        return true;
      };

      if ($projectMode) {
        $opts['filters'] = '`PMEtable0`.`ProjectId` = '.$projectId;
      }

      $opts['execute'] = $this->execute;
      $this->pme = new \phpMyEdit($opts);
    }

    /** phpMyEdit calls the trigger (callback) with the following arguments:
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
    public static function removeDebitNote(&$pme, $op, $step, $oldvals, &$changed, &$newvals)
    {
      if ($op !== 'delete') {
        return false;
      }

      if (empty($oldvals['Id'])) {
        return false;
      }

      if (!empty($oldvals['SubmitDate'])) {
        return false;
      }

      $debitNoteId = $oldvals['Id'];

      $result = true;

      // remove all associated payments
      $result = ProjectPayments::deleteDebitNotePayments($debitNoteId, $pme->dbh);

      // remove all the data (one item, probably)
      $result = self::deleteDebitNoteData($debitNoteId, $pme->dbh);

      try {
        // remove the associated OwnCloud events and task.
        $result = \OC_Calendar_Object::delete($oldvals['SubmissionEvent']);
      } catch (\Exception $e) {}

      try {
        $result = \OC_Calendar_Object::delete($oldvals['DueEvent']);
      } catch (\Exception $e) {}

      try {
        $result = Util::postToRoute('tasks.tasks.deleteTask',
                                    array('taskID' => $oldvals['SubmissionTask']));
      } catch (\Exception $e) {}

      return true;
    }

    /**Fetch the debit-note identified by its id. */
    public static function debitNote($debitNoteId, $handle = false)
    {
      $rows = mySQL::fetchRows(
        self::TABLE, 'Id = '.$debitNoteId, false /* sort */, $handle);

      if (count($rows) !== 1) {
        return false;
      }

      return $rows[0];
    }

    /**Delete the data associated to a given debit-note*/
    private static function deleteDebitNoteData($debitNoteId, $handle)
    {
      $rows = mySQL::fetchRows(
        self::DATA_TABLE, 'DebitNoteId = '.$debitNoteId, 'FileName ASC', $handle);
      $failed = 0;
      foreach($rows as $row) {
        // remove the associated data
        $query = "DELETE FROM `".self::DATA_TABLE."`
  WHERE `Id` = ".$row['Id']." AND `DebitNoteId` = ".$debitNoteId;
        if (mySQL::query($query, $handle) !== false) {
          mySQL::logDelete(self::DATA_TABLE, 'Id', $row, $handle);
        } else {
          ++$failed;
        }
      }
      return $failed === 0;
    }


    /**Fetch the debit-note data identified by the debit-note's id */
    public static function debitNoteData($debitNoteId, $handle = false)
    {
      $rows = mySQL::fetchRows(
        self::DATA_TABLE, 'DebitNoteId = '.$debitNoteId, 'FileName ASC', $handle);

      $encKey = Config::getEncryptionKey();
      foreach($rows as &$row) {
        $row['Data'] = Config::decrypt($row['Data'], $encKey);
      }

      return $rows;
    }

    /**Return the name for the default email-template for the given job-type. */
    public static function emailTemplate($debitNoteJob)
    {
      switcH($debitNoteJob) {
      case 'remaining':
        return L::t('DebitNoteAnnouncementProjectRemaining');
      case 'amount':
        return L::t('DebitNoteAnnouncementProjectAmount');
      case 'deposit':
        return L::t('DebitNoteAnnouncementProjectDeposit');
      case 'insurance':
        return L::t('DebitNoteAnnouncementInsurance');
      case 'membershipt-fee':
        return L::t('DebitNoteAnnouncementMembershipFee');
      default:
        return L::t('DebitNoteAnnouncementUnknown');
      }
    }

  }; // class DebitNotes
}

/*
 * Local Variables: ***
 * c-basic-offset: 2 ***
 * End: ***
 */

?>
