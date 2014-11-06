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

  /**Display all or selected musicians.
   */
  class SepaDebitMandates
    extends Instrumentation
  {
    const CSS_PREFIX = 'cafevdb-page';
    const RATE_TABLE = 'InsuranceRates';
    const MEMBER_TABLE = 'SepaDebitMandates';
    const TAXES = 0.19;
    protected $broker;
    protected $brokerNames;
    protected $scope;
    protected $scopeNames;
    protected $accessory;
    protected $accessoryNames;

    function __construct($execute = true) {
      parent::__construct($execute);
    }

    public function shortTitle()
    {
      if ($this->deleteOperation()) {
        return L::t('Remove this Debit-Mandate?');
      } else if ($this->viewOperation()) {
        if ($this->projectId > 0 && $this->projectName != '') {
          return L::t('Debit-Mandate for %s', array($this->projectName));
        } else {
          return L::t('Debit-Mandate');
        }
      } else if ($this->changeOperation()) {
        return L::t('Change this Debit-Mandate');
      }
      if ($this->projectId > 0 && $this->projectName != '') {
        return L::t('Overview over all SEPA Debit Mandates for %s',
                    array($this->projectName));
      } else {
        return L::t('Overview over all SEPA Debit Mandates');
      }    
    }

    public function headerText()
    {
      return $this->shortTitle();
    }

    /**Display the list of all musicians. If $projectMode == true,
     * filter out all musicians present in $projectId and add a
     * hyperlink which will add the Musician to the respective project.
     */
    public function display()
    {
      global $debug_query;
      $debug_query = Util::debugMode('query');

      $template        = $this->template;
      $projectName     = $this->projectName;
      $projectId       = $this->projectId;
      $recordsPerPage  = $this->recordsPerPage;
      $opts            = $this->opts;
      $musicianId      = $this->musicianId;

      $opts['tb'] = 'SepaDebitMandates';

      // Number of records to display on the screen
      // Value of -1 lists all records in a table
      $opts['inc'] = $recordsPerPage;

      $opts['cgi']['persist'] = array(
        'ProjectName' => $projectName,
        'ProjectId' => $projectId,
        'MusicianId' => $musicianId,
        'Template' => 'sepa-debit-mandates',
        'Table' => $opts['tb'],
        'DisplayClass' => 'SepaDebitMandates',
        'requesttoken' => \OCP\Util::callRegister()
        );

      // Name of field which is the unique key
      $opts['key'] = 'id';

      // Type of key field (int/real/string/date etc.)
      $opts['key_type'] = 'int';

      // Sorting field(s)
      $opts['sort_field'] = array('Broker','GeographicalScope','MusicianId','Accessory');

      // Options you wish to give the users
      // A - add,  C - change, P - copy, V - view, D - delete,
      // F - filter, I - initial sort suppressed
      $opts['options'] = 'CVDFM';
      $opts['misc']['css']['major'] = 'debit-note';
      $opts['misc']['css']['minor'] = 'debit-note tipsy-nw';
      $opts['labels']['Misc'] = L::t('Debit');
      
      // Number of lines to display on multiple selection filters
      $opts['multiple'] = '5';

      // Navigation style: B - buttons (default), T - text links, G - graphic links
      // Buttons position: U - up, D - down (default)
      //$opts['navigation'] = 'DB';

      $export = Navigation::tableExportButton();
      $opts['buttons'] = Navigation::prependTableButton($export, true);

      // Display special page elements
      $opts['display'] =  array_merge($opts['display'],
                                      array(
                                        'form'  => true,
                                        'query' => true,
                                        'sort'  => true,
                                        'time'  => true,
                                        'tabs'  => false
                                        ));

      // Set default prefixes for variables
      $opts['js']['prefix']               = 'PME_js_';
      $opts['dhtml']['prefix']            = 'PME_dhtml_';
      $opts['cgi']['prefix']['operation'] = 'PME_op_';
      $opts['cgi']['prefix']['sys']       = 'PME_sys_';
      $opts['cgi']['prefix']['data']      = 'PME_data_';

      /* Get the user's default language and use it if possible or you can
         specify particular one you want to use. Refer to official documentation
         for list of available languages. */
      //  $opts['language'] = $_SERVER['HTTP_ACCEPT_LANGUAGE'] . '-UTF8';

      /* Table-level filter capability. If set, it is included in the WHERE clause
         of any generated SELECT statement in SQL query. This gives you ability to
         work only with subset of data from table.

         $opts['filters'] = "column1 like '%11%' AND column2<17";
         $opts['filters'] = "section_id = 9";
         $opts['filters'] = "PMEtable0.sessions_count > 200";
      */

      $junctor = '';
      if ($musicianId > 0) {
        $opts['filters'] = $junctor."`PMEtable0`.`musicianId` = ".$musicianId;
        $junctor = " AND ";
      }
      if ($projectId > 0) {
        $opts['filters'] =
          $junctor.
          "(".
          "`PMEtable0`.`projectId` = ".$projectId.
          " OR ".
          "(".
          "`PMEtable0`.`projectId` = ".Config::getValue('memberTableId').
          " AND ".
          "(".
          "SELECT COUNT(*) FROM `Besetzungen` ".
          "  WHERE `MusikerId` = `PMEtable0`.`musicianId` AND `ProjektId` = ".$projectId.
          ")".
          ")".
          ")";
        $junctor = " AND ";
      }

      /* Field definitions
   
         Fields will be displayed left to right on the screen in the order in which they
         appear in generated list. Here are some most used field options documented.

         ['name'] is the title used for column headings, etc.;
         ['maxlen'] maximum length to display add/edit/search input boxes
         ['trimlen'] maximum length of string content to display in row listing
         ['width'] is an optional display width specification for the column
         e.g.  ['width'] = '100px';
         ['mask'] a string that is used by sprintf() to format field output
         ['sort'] true or false; means the users may sort the display on this column
         ['strip_tags'] true or false; whether to strip tags from content
         ['nowrap'] true or false; whether this field should get a NOWRAP
         ['select'] T - text, N - numeric, D - drop-down, M - multiple selection
         ['options'] optional parameter to control whether a field is displayed
         L - list, F - filter, A - add, C - change, P - copy, D - delete, V - view
         Another flags are:
         R - indicates that a field is read only
         W - indicates that a field is a password field
         H - indicates that a field is to be hidden and marked as hidden
         ['URL'] is used to make a field 'clickable' in the display
         e.g.: 'mailto:$value', 'http://$value' or '$page?stuff';
         ['URLtarget']  HTML target link specification (for example: _blank)
         ['textarea']['rows'] and/or ['textarea']['cols']
         specifies a textarea is to be used to give multi-line input
         e.g. ['textarea']['rows'] = 5; ['textarea']['cols'] = 10
         ['values'] restricts user input to the specified constants,
         e.g. ['values'] = array('A','B','C') or ['values'] = range(1,99)
         ['values']['table'] and ['values']['column'] restricts user input
         to the values found in the specified column of another table
         ['values']['description'] = 'desc_column'
         The optional ['values']['description'] field allows the value(s) displayed
         to the user to be different to those in the ['values']['column'] field.
         This is useful for giving more meaning to column values. Multiple
         descriptions fields are also possible. Check documentation for this.
      */

      $opts['fdd']['id'] = array(
        'name'     => 'Id',
        'select'   => 'T',
        'options'  => 'AVCPDR', // auto increment
        'maxlen'   => 5,
        'align'    => 'right',
        'default'  => '0',
        'sort'     => true
        );

      $opts['fdd']['mandateReference'] = array('name'   => L::t('Mandate Reference'),
                                               'input'  => 'R',
                                               'select' => 'T',
                                               'maxlen' => 35,
                                               'sort'   => true);

      $opts['fdd']['nonrecurring'] = array('name'   => L::t('One Time'),
                                           'input'  => 'R',
                                           'select' => 'T',
                                           'maxlen' => 35,
                                           'sort'   => true,
                                           'values2' => array('0' => L::t('no'),
                                                              '1' => L::t('yes')));

      $opts['fdd']['mandateDate'] = array('name'     => L::t('Date Issued'),
                                          'select'   => 'T',
                                          'maxlen'   => 10,
                                          'sort'     => true,
                                          'css'      => array('postfix' => ' sepadate'),
                                          'datemask' => 'd.m.Y');

      $opts['fdd']['musicianId'] = array('name'     => L::t('Musician'),
                                         'input'    => 'R',
                                         'select'   => 'T',
                                         'maxlen'   => 11,
                                         'sort'     => true,
                                         //'options'  => 'LFADV', // no change allowed
                                         'default' => 0,
                                         'values' => array('table' => 'Musiker',
                                                           'column' => 'Id',
                                                           'description' => array('columns' => array('Name', 'Vorname'),
                                                                                  'divs' => array(', ')
                                                             ))
        );

      if ($projectId >= 0) {
        // Add the amount to debit

        $feeIdx = count($opts['fdd']);
        $opts['fdd']['projectFee'] = Config::$opts['money'];
        $opts['fdd']['projectFee'] = array_merge(
          Config::$opts['money'],
          array(
            'input' => 'V',
            'options' => 'LFACPDV',
            'name' => L::t('Project Fee'),
            'sql' => '`PMEjoin'.$feeIdx.'`.`Unkostenbeitrag`',
            'sqlw' => '`PMEjoin'.$feeIdx.'`.`Unkostenbeitrag`',
            'values' => array('table' => 'Besetzungen',
                              'column' => 'Unkostenbeitrag',
                              'join' => ('$main_table.musicianId = $join_table.MusikerId'.
                                         ' AND '.
                                         $projectId.' = $join_table.ProjektId'),
                              'description' => 'Unkostenbeitrag'
              )
            )
          );
      }

      $opts['fdd']['projectId'] = array('name'     => L::t('Project'),
                                        'input'    => 'R',
                                        'select'   => 'T',
                                        'maxlen'   => 11,
                                        'sort'     => true,
                                        //'options'  => 'LFADV', // no change allowed
                                        'default' => 0,
                                        'values' => array('table' => 'Projekte',
                                                          'column' => 'Id',
                                                          'description' => 'Name')
        );

      $opts['fdd']['IBAN'] = array('name'   => 'IBAN',
                                   'options' => 'LACPDV',
                                   'select' => 'T',
                                   'maxlen' => 35,
                                   'encryption' => array(
                                     'encrypt' => '\CAFEVDB\Config::encrypt',
                                     'decrypt' => '\CAFEVDB\Config::decrypt',
                                     ));

      $opts['fdd']['BIC'] = array('name'   => 'BIC',
                                  'select' => 'T',
                                  'maxlen' => 35,
                                  'encryption' => array(
                                    'encrypt' => '\CAFEVDB\Config::encrypt',
                                    'decrypt' => '\CAFEVDB\Config::decrypt',
                                    ));

      $opts['fdd']['BLZ'] = array('name'   => L::t('Bank Code'),
                                  'select' => 'T',
                                  'maxlen' => 12,
                                  'encryption' => array(
                                    'encrypt' => '\CAFEVDB\Config::encrypt',
                                    'decrypt' => '\CAFEVDB\Config::decrypt',
                                    ));

      $opts['fdd']['bankAccountOwner'] = array('name'   => L::t('Bank Account Owner'),
                                               'select' => 'T',
                                               'maxlen' => 80,
                                               'encryption' => array(
                                                 'encrypt' => '\CAFEVDB\Config::encrypt',
                                                 'decrypt' => '\CAFEVDB\Config::decrypt',
                                                 ));

      $opts['fdd']['lastUsedDate'] = array('name'     => L::t('Last-Used Date'),
                                           'select'   => 'T',
                                           'maxlen'   => 10,
                                           'sort'     => true,
                                           'css'      => array('postfix' => ' sepadate'),
                                           'datemask' => 'd.m.Y');

    

      if ($this->pme_bare) {
        // disable all navigation buttons, probably for html export
        $opts['navigation'] = 'N'; // no navigation
        $opts['options'] = '';
        // Don't display special page elements
        $opts['display'] =  array_merge($opts['display'],
                                        array(
                                          'form'  => false,
                                          'query' => false,
                                          'sort'  => false,
                                          'time'  => false,
                                          'tabs'  => false
                                          ));
        // Disable sorting buttons
        foreach ($opts['fdd'] as $key => $value) {
          $opts['fdd'][$key]['sort'] = false;
        }
      }

      $opts['execute'] = $this->execute;

      $this->pme = new \phpMyEdit($opts);

      if (Util::debugMode('request')) {
        echo '<PRE>';
        print_r($_POST);
        echo '</PRE>';
      }

    } // display()

    /**Provide a very primitive direct matrix representation, filtered
     * by the given project and/or musician.
     */
    static public function projectTableExport($projectId, $musicianId = -1, $handle = false)
    {
      $where = "( `projectId` = ".$projectId.
        " OR ".
        "`projectId` = ".Config::getValue('memberTableId')." )";
      if ($musicianId > 0) {
        $query .= " AND `musicianId = ".$musicianId;
      }

      $ownConnection = $handle === false;

      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $query = "SELECT `Musiker`.`Name`,`Musiker`.`Vorname`,`Projekte`.`Name` as 'projectName',`".self::MEMBER_TABLE."`.*,`Besetzungen`.`Unkostenbeitrag` AS 'projectFee' FROM ".self::MEMBER_TABLE."
  JOIN (SELECT * FROM Besetzungen WHERE ProjektId = ".$projectId.") AS Besetzungen
  ON `Besetzungen`.`MusikerId` = `".self::MEMBER_TABLE."`.`musicianId`
  LEFT JOIN `Musiker` ON `Musiker`.`Id` = `".self::MEMBER_TABLE."`.`musicianId`
  LEFT JOIN `Projekte` ON `Projekte`.`Id` = `".self::MEMBER_TABLE."`.`projectId`
  WHERE ".$where;

      $result = mySQL::query($query, $handle);
      $table = array();
      while ($row = mySQL::fetch($result)) {
        $row['purpose'] = array($row['projectName'],
                                L::t('Project Fees'),
                                '', '');
        $table[$row['id']] = $row;
      }
      
      if ($ownConnection) {
        mySQL::close($handle);
      }
    
      return $table;
    }
    
    /**Export the respective debit-mandates and generate a flat table
     * view which can 1 to 1 be exported into a CSV table suitable to
     * finally issue the debit mandates to the respective credit
     * institutes.
     */
    static public function aqBankingDebitNotes($debitTable)
    {
      $iban  = new \IBAN(Config::getValue('bankAccountIBAN'));
      $iban  = $iban->MachineFormat();
      $bic   = Config::getValue('bankAccountBIC');
      $owner = Config::getValue('bankAccountOwner');
      $executionDate = date('Y/m/d', strtotime('+ 14 days'));

      // "localBic";"localIban";"remoteBic";"remoteIban";"date";"value/value";"value/currency";"localName";"remoteName";"creditorSchemeId";"mandateId";"mandateDate/dateString";"mandateDebitorName";"sequenceType";"purpose[0]";"purpose[1]";"purpose[2]";"purpose[3]"
      $result = array();
      foreach($debitTable as $id => $row) {

        if ($row['nonrecurring']) {
          $sequenceType = 'once';
        } else if ($row['lastUsedDate'] == '0000-00-00') {
          $sequenceType = 'first';
        } else {
          $sequenceType = 'following';
        }
      
        $result[] = array(
          'localBic' => $bic,
          'localIBan' => $iban,
          'remoteBic' => $row['BIC'],
          'remoteIban' => $row['IBAN'],
          'date' => $executionDate,
          'value/value' => $row['projectFee'],
          'value/currency' => 'EUR',
          'localName' => $owner,
          'remoteName' => $row['bankAccountOwner'],
          'creditorSchemeId' => Config::getValue('bankAccountCreditorIdentifier'),
          'mandateId' => $row['mandateReference'],
          'mandateDate/dateString' => date('Ymd', strtotime($row['mandateDate'])),
          'mandateDebitorName' => $row['Name'].', '.$row['Vorname'],
          'sequenceType' => $sequenceType,
          'purpose[0]' => $row['purpose'][0],
          'purpose[1]' => $row['purpose'][1],
          'purpose[2]' => $row['purpose'][2],
          'purpose[3]' => $row['purpose'][3]
          );
      }
      return $result;
    }

  }; // class definition.

}

?>
