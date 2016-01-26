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
      $opts['sort_field'] = array('Broker', 'Id');

      // Number of records to display on the screen
      // Value of -1 lists all records in a table
      $opts['inc'] = -1;

      // Options you wish to give the users
      // A - add,  C - change, P - copy, V - view, D - delete,
      // F - filter, I - initial sort suppressed
      $opts['options'] = 'ACPVDF';
      $sort = false;

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
        'sort'     => $sort,
        );

      $opts['fdd']['InstrumentationId'] = array(
        'name'     => L::t('Musician'),
        'css'      => array('postfix' => ' instrumentation-id'),
        'values'   => array(
          'table'  => "SELECT b.Id, CONCAT(m.Vorname,' ',m.Name) AS Name
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
          'sql|LFVD' => 'IFNULL(`PMEjoin'.($receiptIdx+2).'`.`DueDate`,`PMEtable0`.`DateOfReceipt`)',
          )
        );

      $subjectIdx = count($opts['fdd']);
      $opts['fdd']['Subject'] = array(
        'name' => L::t('Subject'),
        'css'  => array('postfix' => ' subject'),
        'sql|LFVD' => 'IFNULL(`PMEjoin'.($subjectIdx + 1).'`.`Subject`,`PMEtable0`.`Subject`)',
        'select' => 'T',
        'textarea' => array('css' => ' subject-text',
                            'rows' => 4,
                            'cols' => 32),
        'display|LF' => array('popup' => 'data'),
        'escape' => true,
        'sort' => true
        );

      $debitIdIdx = count($opts['fdd']);
      $opts['fdd']['DirectDebitId'] =
        array_merge(Config::$opts['datetime'],
                    array('name' => L::t('Direct Debit Date'),
                           'input' => 'R',
                          'input|C' => 'H',
                          'options' => 'LCFVD',
                          'values' => array(
                            'table' => 'DirectDebits',
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

      $opts['triggers']['select']['data'] = function(&$pme, $op, $step, &$row) use ($debitIdIdx, $opts) {
        error_log('called '.$op.' '.$debitIdIdx);
        error_log('called '.print_r($row, true));
        if (!empty($row['qf'.$debitIdIdx])) {
          $pme->options = 'LVF';
          if ($op !== 'select' ) {
//            throw new \BadFunctionCallException(L::t('Payments resulting from direct debit transfers cannot be changed.'));
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

      $opts['execute'] = $this->execute;
      $this->pme = new \phpMyEdit($opts);

    }

  }; // class ProjectPayments

}

/*
 * Local Variables: ***
 * c-basic-offset: 2 ***
 * End: ***
 */

?>
