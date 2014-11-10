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
class InstrumentInsurance
  extends Instrumentation
{
  const CSS_PREFIX = 'cafevdb-page';
  const RATE_TABLE = 'InsuranceRates';
  const MEMBER_TABLE = 'InstrumentInsurance';
  const TAXES = 0.19;
  protected $broker;
  protected $brokerNames;
  protected $scope;
  protected $scopeNames;
  protected $accessory;
  protected $accessoryNames;

  function __construct($execute = true) {
    parent::__construct($execute);

    $handle = mySQL::connect($this->opts);

    $this->broker = mySQL::multiKeys('InstrumentInsurance', 'Broker', $handle);
    // brokerNames ... maybe store an additional table with further
    // information about the brokers
    foreach ($this->broker as $tag) {
      $this->brokerNames[$tag] = $tag;
    }
    
    $this->scope = mySQL::multiKeys('InstrumentInsurance', 'GeographicalScope', $handle);
    $this->scopeNames = array();
    foreach ($this->scope as $tag) {
      $this->scopeNames[$tag] = strval(L::t($tag));
    }
    if (false) {
      // Dummies to keep the translation right.
      L::t('Germany');
      L::t('Europe');
      L::t('World');
    }

    $this->accessory = mySQL::multiKeys('InstrumentInsurance', 'Accessory', $handle);
    $this->accessoryNames = array();
    foreach ($this->accessory as $tag) {
      $this->accessoryNames[$tag] = strval(L::t($tag));
    }
    if (false) {
      // Dummies to keep the translation right.
      L::t('false');
      L::t('true');
    }

    $this->projectName = Config::getValue('memberTable');
    $this->projectId = Config::getValue('memberTableId');
    
    mySQL::close($handle);
  }

  public function shortTitle()
  {
    return L::t('Overview over the Bulk Instrument Insurances');
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
    Config::init();

    global $debug_query;
    $debug_query = Util::debugMode('query');

    $template        = $this->template;
    $projectName     = $this->projectName;
    $projectId       = $this->projectId;
    $recordsPerPage  = $this->recordsPerPage;
    $opts            = $this->opts;
    $musicianId      = $this->musicianId;

    /*
     * IMPORTANT NOTE: This generated file contains only a subset of huge amount
     * of options that can be used with phpMyEdit. To get information about all
     * features offered by phpMyEdit, check official documentation. It is available
     * online and also for download on phpMyEdit project management page:
     *
     * http://platon.sk/projects/main_page.php?project_id=5
     *
     * This file was generated by:
     *
     *                    phpMyEdit version: 5.7.1
     *       phpMyEdit.class.php core class: 1.204
     *            phpMyEditSetup.php script: 1.50
     *              generating setup script: 1.50
     */

    $opts['tb'] = 'InstrumentInsurance';

    // Number of records to display on the screen
    // Value of -1 lists all records in a table
    $opts['inc'] = $recordsPerPage;

    $opts['cgi']['persist'] = array(
      'ProjectName' => $projectName,
      'ProjectId' => $projectId,
      'MusicianId' => $musicianId,
      'Template' => 'instrument-insurance',
      'DisplayClass' => 'InstrumentInsurance',
      'Table' => $opts['tb'],
      'requesttoken' => \OCP\Util::callRegister());

    // Name of field which is the unique key
    $opts['key'] = 'Id';

    // Type of key field (int/real/string/date etc.)
    $opts['key_type'] = 'int';

    // Sorting field(s)
    $opts['sort_field'] = array('Broker','GeographicalScope','MusicianId','Accessory');

    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    $opts['options'] = 'ACVDFM';
    $opts['misc']['css']['major'] = 'debit-note';
    $opts['misc']['css']['minor'] = 'debit-note insurance tipsy-nw';
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

    if ($musicianId > 0) {
      $opts['filters'] = "`PMEtable0`.`MusicianId` = ".$musicianId;
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

    $opts['fdd']['Id'] = array(
      'name'     => 'Id',
      'select'   => 'T',
      'options'  => 'AVCPDR', // auto increment
      'maxlen'   => 5,
      'align'    => 'right',
      'default'  => '0',
      'sort'     => true
      );

    $opts['fdd']['MusicianId'] = array('name'     => strval(L::t('Musician')),
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
    $opts['fdd']['BillToParty'] = array('name'     => strval(L::t('Bill-to Party')),
                                        'select'   => 'T',
                                        'maxlen'   => 11,
                                        'sort'     => true,
                                        'default'  => 0,
                                        //'options'  => 'LFADV', // no change allowed
                                        'values' => array('table' => 'Musiker',
                                                          'column' => 'Id',
                                                          'description' => array('columns' => array('Name', 'Vorname'),
                                                                                 'divs' => array(', ')
                                                            ))
                                      );

    $opts['fdd']['Broker'] = array('name' => strval(L::t('Insurance Broker')),
                                   'select'   => 'D',
                                   'maxlen'   => 384,
                                   'sort'     => true,
                                   'default'  => '',
                                   'values2'  => $this->brokerNames);

    $opts['fdd']['GeographicalScope'] = array('name' => strval(L::t('Geographical Scope')),
                                              'select'   => 'D',
                                              'maxlen'   => 384,
                                              'sort'     => true,
                                              'default'  => '',
                                              'values2'  => $this->scopeNames);

    $opts['fdd']['Object'] = array('name'     => strval(L::t('Insured Object')),
                                   'select'   => 'T',
                                   'maxlen'   => 384,
                                   'sort'     => true);

    $opts['fdd']['Accessory'] = array('name' => strval(L::t('Accessory')),
                                      'select'   => 'D',
                                      'maxlen'   => 384,
                                      'sort'     => true,
                                      'default' => 'false',
                                      'values2'  => $this->accessoryNames);

    $opts['fdd']['Manufacturer'] = array('name'     => strval(L::t('Manufacturer')),
                                         'select'   => 'T',
                                         'maxlen'   => 384,
                                         'sort'     => true);

    $opts['fdd']['YearOfConstruction'] = array('name'     => strval(L::t('Year of Construction')),
                                              'select'   => 'T',
                                              'maxlen'   => 384,
                                              'sort'     => true);

    $opts['fdd']['InsuranceAmount'] = Config::$opts['money'];
    $opts['fdd']['InsuranceAmount']['name'] = strval(L::t('Insurance Amount'));

    $rateIdx = count($opts['fdd']);
    $opts['fdd']['InsuranceRate'] = array(
      'input' => 'V',
      'name' => L::t('Insurance Rate'),
      'options' => 'LFACPDV',
      'sql' => '`PMEjoin'.$rateIdx.'`.`Rate`',
      'sqlw' => '`PMEjoin'.$rateIdx.'`.`Rate`',
      'values' => array(
        'table' => 'InsuranceRates',
        'column' => 'Rate',
        'join' => '$join_table.Broker = $main_table.Broker'.
        ' AND '.
        '$join_table.GeographicalScope = $main_table.GeographicalScope',
        'description' => 'Rate')
      );

    $opts['fdd']['InsuranceFee'] = array(
      'input' => 'V',
      'name' => L::t('Insurance Fee')."<br/>".L::t('including taxes'),
      'options' => 'LFACPDV',
      'sql' => 'ROUND(`PMEtable0`.`InsuranceAmount` * `PMEjoin'.$rateIdx.'`.`Rate` * (1+'.self::TAXES.'), 2)',
      'sqlw' => '`PMEjoin'.$rateIdx.'`.`Rate`'
      );

    $opts['fdd']['InsuranceTotal'] = array(
      'input' => 'V',
      'name' => L::t('Total Insurance'),
      'select' => 'T',
      'options' => 'ACPDV',
      'sql' => "MusicianId",
      'escape' => false,
      'nowrap' => true,
      'sort' =>false,
      'php' => array(
        'type' => 'function',
        'function' => 'CAFEVDB\InstrumentInsurance::instrumentInsurancePME',
        'parameters' => array()
        )
      );

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

  //!Button redirect
  public static function instrumentInsurancePME($insuranceId, $opts, $action, $k, $fds, $fdd, $row)
  {
    return Musicians::instrumentInsurance($row['qf1_idx'], $opts);
  }

  /**Fetch the total insurance amount for one musician.
   */
  public static function insuranceAmount($musicianId, $handle = false)
  {
    $ownConnection = $handle === false;
    if ($ownConnection) {
      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);
    }

    $amount = 0;
    $query = "SELECT SUM(`InsuranceAmount`) FROM `InstrumentInsurance` WHERE `MusicianId` = ".$musicianId;
    $result = mySQL::query($query, $handle);
    $result = mySQL::fetch($result, MYSQL_NUM);
    if (isset($result[0])) {
      $amount = $result[0];
    }
    
    if ($ownConnection) {
      mySQL::close($handle);
    }

    return $amount;
  }

  /**Convert an array of insurance ids to a (smaller) array of the
   * corresponding debit-mandate ids
   */
  public static function remapToDebitIds($insuranceIds, $handle = false)
  {
    $projectName = Config::getValue('memberTable');
    $projectId = Config::getValue('memberTableId');

    $insuranceTable = 'InstrumentInsurance';
    $debitTable = 'SepaDebitMandates';
    
    // remap insurance ids to debit mandate ids
    $query = 'SELECT `'.$insuranceTable.'`.`Id` AS \'OrigId\',
  `'.$debitTable.'`.`id` AS \'DebitId\'
  FROM `'.$debitTable.'` RIGHT JOIN `'.$insuranceTable.'`
  ON (
       `'.$insuranceTable.'`.`BillToParty` <= 0
       AND
       `'.$debitTable.'`.`musicianId` = `'.$insuranceTable.'`.`MusicianId`
    ) OR (
       `'.$insuranceTable.'`.`BillToParty` > 0
       AND
       `'.$debitTable.'`.`musicianId` = `'.$insuranceTable.'`.`BillToParty`
    )
    WHERE `'.$debitTable.'`.`projectId` = '.$projectId;

    $ownConnection = $handle === false;
    if ($ownConnection) {
      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);
    }

    // Fetch the result (or die) and remap the Ids
    $result = mySQL::query($query, $handle);
    $map = array();
    while ($line = mysql_fetch_assoc($result)) {
      $map[$line['OrigId']] = $line['DebitId'];
    }
    
    if ($ownConnection) {
      mySQL::close($handle);
    }

    $result = array();
    foreach($insuranceIds as $key) {
      if (!isset($map[$key])) {
        continue;
      }
      $result[] = $map[$key];
    }

    return array_unique($result);
  }
  
  /**Convert an array of insurance ids to a (smaller) array of the
   * related musician ids. Each insurance entry has one exactly one
   * musician-in-charge, so this should work in principle.
   */
  public static function remapToMusicianIds($insuranceIds, $handle = false)
  {
    $projectName = Config::getValue('memberTable');
    $projectId = Config::getValue('memberTableId');

    $insuranceTable = 'InstrumentInsurance';
    $otherTable = 'Musiker';
    $musicianId = 'Id';
    
    // remap insurance ids to musician ids
    $query = 'SELECT `'.$insuranceTable.'`.`Id` AS \'OrigId\',
  `'.$otherTable.'`.`id` AS \'OtherId\'
  FROM `'.$otherTable.'` RIGHT JOIN `'.$insuranceTable.'`
  ON (
       `'.$insuranceTable.'`.`BillToParty` <= 0
       AND
       `'.$otherTable.'`.`'.$musicianId.'` = `'.$insuranceTable.'`.`MusicianId`
    ) OR (
       `'.$insuranceTable.'`.`BillToParty` > 0
       AND
       `'.$otherTable.'`.`'.$musicianId.'` = `'.$insuranceTable.'`.`BillToParty`
    )
    WHERE 1';

    //throw new \Exception('QUERY: '.$query);
    
    $ownConnection = $handle === false;
    if ($ownConnection) {
      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);
    }

    // Fetch the result (or die) and remap the Ids
    $result = mySQL::query($query, $handle);
    $map = array();
    while ($line = mysql_fetch_assoc($result)) {
      $map[$line['OrigId']] = $line['OtherId'];
    }
    
    if ($ownConnection) {
      mySQL::close($handle);
    }

    $result = array();
    foreach($insuranceIds as $key) {
      if (!isset($map[$key])) {
        continue;
      }
      $result[] = $map[$key];
    }

    return array_unique($result);
  }

  /**Generate an overview table to the respective musician. This is
   * meant for back-report to the musician, so we do not need all
   * fields. We include
   *
   * Broker, Geog. Scope, Object, Manufacturer, Amount, Rate, Fee
   *
   * Potentially, insured musician and payer may be different. We
   * generate a table of the form
   *
   * array('payer' => array(<Name and Address Information>),
   *       'totals' => <Total Fee including taxes>,
   *       'musicians' => array(MusID => array('name' => <Human Readable Name>,
   *                                           'subtotals' => <Total Fee for this one, incl. Taxes>,
   *                                           'items' => array(<Insured Items>)
   */
  public static function musicianOverview($musicianId, $handle = false)
  {
    $ownConnection = $handle === false;
    if ($ownConnection) {
      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);
    }

    $streetAddress = Musicians::fetchStreetAddress($musicianId, $handle);
    
    $insurances = array('payer' => $streetAddress,
                        'totals' => 0,
                        'musicians' => array());

    $rates = self::fetchRates($handle);
    
    $query = "SELECT * FROM `".self::MEMBER_TABLE."` WHERE ("
      . " ( (ISNULL(`BillToParty`) OR `BillToParty` <= 0) AND `MusicianId` = ".$musicianId." ) "
      . " OR "
      . " `BillToParty` = ".$musicianId
      . " )";
    
    $result = mySQL::query($query, $handle);
    while ($row = mySQL::fetch($result)) {
      $musId = $row['MusicianId'];
      if (!isset($insurances['musicians'][$musId])) {
        $insurances['musicians'][$musId] = array(
          'name' => '',
          'subTotals' => 0.0,
          'items' => array()
          );
      }
      $rateKey = $row['Broker'].$row['GeographicalScope'];
      $itemInfo = array('broker' => $row['Broker'],
                        'scope' => $row['GeographicalScope'],
                        'object' => $row['Object'],
                        'manufacturer' =>  $row['Manufacturer'],
                        'amount' => $row['InsuranceAmount'],
                        'rate' => $rates[$rateKey]);

      $amount = floatval($itemInfo['amount']);
      $rate = floatval($itemInfo['rate']);
      $itemInfo['amount'] = $amount; // convert to float
      $itemInfo['rate'] = $rate; // convert to float
      $itemInfo['fee'] = $amount * $rate; // * (1.0 + (float)self::TAXES);
      $insurances['musicians'][$musId]['items'][] = $itemInfo;
    }

    $totals = 0.0;
    foreach($insurances['musicians'] as $id => $info) {
      $subtotals = 0.0;
      foreach($info['items'] as $itemInfo) {
        $subtotals += $itemInfo['fee'];
      }
      $insurances['musicians'][$id]['subTotals'] = $subtotals;
      $totals += $subtotals;
      $name = Musicians::fetchName($id, $handle);
      $insurances['musicians'][$id]['name'] = $name['firstName'].' '.$name['lastName'];
    }
    $insurances['totals'] = $totals;
    
    if ($ownConnection) {
      mySQL::close($handle);
    }

    return $insurances;
  }

  /**Take the data provided by self::musicianOverview() to generate a
   * PDF with a DIN-letter in order to send the overview to the
   * respective musician by SnailMail. The resulting letter will be
   * returned as string.
   */
  public static function musicianOverviewLetter($overview, $name = 'dummy.pdf', $dest = 'S')
  {
    $year = strftime('%Y');
    $css = "insurance-overview-table";
    $style = '<style>
  table.'.$css.' {
    border: #000 1px solid;
    border-collapse:collapse;
    border-spacing:0px;
    /*width:auto; not with tcpdf ... */
  }
  table.'.$css.' td {
    border: #000 1px solid;
    min-width:5em;
    padding: 0.1em 0.5em 0.1em 0.5em;
  }
  table.'.$css.' th {
    border: #000 1px solid;
    min-width:5em;
    padding: 0.1em 0.5em 0.1em 0.5em;
    text-align:center;
    font-weight:bold;
    font-style:italic;
  }
  table.'.$css.' td.summary {
    text-align:right;
  }
  td.money, td.percentage {
    text-align:right;
  }
  table.totals {
    font-weight:bold;
  }
</style>';
    
    // create a PDF object
    $pdf = new PDFLetter(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
 
    // set document (meta) information
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Claus-Justus Heine');
    $pdf->SetTitle(L::t('Annual Insurance Fees for %s, %d. %s',
                        array($overview['payer']['firstName'].' '.$overview['payer']['surName'],
                              $year,
                              Config::getValue('streetAddressName01'))));
    $pdf->SetSubject(L::t('Overview over insured instruments and insurance fee details and summary'));
    $pdf->SetKeywords('invoice, insurance, instruments');
 
    // add a page
    $pdf->addPage();

    // Falzmarken
    $pdf->Line(3,105,6,105);
    $pdf->Line(3,148,8,148);
    $pdf->Line(3,210,6,210);
 
    // Address record
    $pdf->frontHeader(
      'c/o Claus-Justus Heine<br>'.
      'Engelboldstr. 97<br>'.
      '70569 Stuttgart<br>'.
      'Phon: 0151-651 6666 0<br>'.
      'M@il: Kassenwart@CAFeV.DE'
      );
    $pdf->addressFieldSender('C.-J. Heine, Engelboldstr. 97, 70569 Stuttgart');
    $pdf->addressFieldRecipient(
      $overview['payer']['firstName'].' '.$overview['payer']['surName'].'
'.$overview['payer']['street'].'
'.$overview['payer']['ZIP'].' '.$overview['payer']['city']
      );

    $pdf->date(strftime('%x'));

    $pdf->subject(L::t("Annular insurance fees for %d", array($year)));
    $pdf->letterOpen(L::t('Dear %s,', array($overview['payer']['firstName'])));
      
    $pdf->writeHtmlCell(PDFLetter::PAGE_WIDTH-PDFLetter::LEFT_TEXT_MARGIN-PDFLetter::RIGHT_TEXT_MARGIN,
                        10,
                        PDFLetter::LEFT_TEXT_MARGIN, $pdf->GetY()+1*PDFLetter::FONT_SIZE,
                        L::t('this letter informs you about the details of the instrument-insurances
we are maintaining for you on your behalf. This letter is
machine-generated; in case of any inconsistencies or other questions
please contact us as soon as possible in order to avoid any further
misunderstandings. This letter is sent to you by traditional
"snail-mail" for data-security reason. Please keep a copy of this
letter in a safe place; further insurance-charts will only be sent to
you if something changes. However, you may request information about
your insured items at any time, but we require you to send us a
prepaid envelope with proper address-information to us. We kindly thank your understanding.'), '', 1);

    $html = '';
    foreach($overview['musicians'] as $id => $insurance) {
      $html .= '
<h4>'.L::t('Insured Person: %s', array($insurance['name'])).'</h4>
<table cellpadding="2" class="'.$css.'">
  <tr>
    <th>'.L::t('Vendor').'</th>
    <th width="90">'.L::t('Scope').'</th>
    <th width="80">'.L::t('Object').'</th>
    <th>'.L::t('Manufacturer').'</th>
    <th width="60">'.L::t('Amount').'</th>
    <th width="45">'.L::t('Rate').'</th>
    <th>'.L::t('Fee').'</th> 
  </tr>';
      foreach($insurance['items'] as $object) {
        $html .= '
  <tr>
    <td class="text">'.$object['broker'].'</td>
    <td class="text">'.L::t($object['scope']).'</td>
    <td class="text">'.$object['object'].'</td>
    <td class="text">'.$object['manufacturer'].'</td>
    <td class="money">'.money_format('%n', $object['amount']).'</td>
    <td class="percentage">'.($object['rate']*100.0).' %'.'</td>
    <td class="money">'.money_format('%n', $object['fee']).'</td>
  </tr>';
      }
      $html .= '
  <tr>
    <td class="summary" colspan="6">'.
      L::t('Sub-totals (excluding taxes)',
           array(InstrumentInsurance::TAXES)).'
    </td>
    <td class="money">'.money_format('%n', $insurance['subTotals']).'</td>
  </tr>
</table>';

      $pdf->writeHtmlCell(PDFLetter::PAGE_WIDTH-PDFLetter::LEFT_TEXT_MARGIN-PDFLetter::RIGHT_TEXT_MARGIN,
                          10,
                          PDFLetter::LEFT_TEXT_MARGIN, $pdf->GetY()+1*PDFLetter::FONT_SIZE,
                          $style.$html, '', 1);

    }

    $totals = $overview['totals'];
    $taxRate = floatval(InstrumentInsurance::TAXES);
    $taxes = $totals * $taxRate;
    $html = '';
    $html .= '
<table class="totals">
  <tr>
    <td class="summary">'.L::t('Total amount excluding taxes:').'</td>
    <td class="money">'.money_format('%n', $totals).'</td>
  </tr>
  <tr>
    <td class="summary">'.L::t('%0.2f %% insurance taxes:', array($taxRate*100.0)).'</td>
    <td class="money">'.money_format('%n', $taxes).'</td>
  </tr>
  <tr>
    <td class="summary">'.L::t('Total amount to pay:').'</td>
    <td class="money">'.money_format('%n', $totals+$taxes).'</td>
  </tr>
</table>';    

    
    $pdf->writeHtmlCell(PDFLetter::PAGE_WIDTH-PDFLetter::LEFT_TEXT_MARGIN-PDFLetter::RIGHT_TEXT_MARGIN,
                        10,
                        PDFLetter::LEFT_TEXT_MARGIN, $pdf->GetY()+1*PDFLetter::FONT_SIZE,
                        $style.$html, '', 1);
    
    $pdf->letterClose(L::t('Best wishes,'), 'Claus-Justus Heine (Kassenwart)');
      
    //Close and output PDF document
    return $pdf->Output($name, $dest);    
  }
  
  /**Fetch the insurance rates of the respective brokers. For the time
   * being brokers offer different rates, independent from the
   * instrument, but depending on the geographical scope (Germany,
   * Europe, World).
   *
   * Return value is an associative array of the form
   *
   * array(BROKERSCOPE => RATE)
   *
   * where "RATE" is the actual fraction, not the percentage.
   */
  public static function fetchRates($handle = false)
  {
    $ownConnection = $handle === false;
    if ($ownConnection) {
      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);
    }

    $rates = array();
    $query = "SELECT * FROM `".self::RATE_TABLE."` WHERE 1";
    $result = mySQL::query($query, $handle);
    while ($row = mySQL::fetch($result)) {
      $rateKey = $row['Broker'].$row['GeographicalScope'];
      $rates[$rateKey] = $row['Rate'];
    }
    //print_r($rates);

    if ($ownConnection) {
      mySQL::close($handle);
    }

    return $rates;
  }
  
  /**Compute the annual insurance fee for the respective
   * musician. Note that the relevant column is the "BillToParty".
   */
  public static function annualFee($musicianId, $handle = false)
  {
    $ownConnection = $handle === false;
    if ($ownConnection) {
      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);
    }

    $rates = self::fetchRates($handle);

    $fee = 0.0;
    $query = "SELECT * FROM `".self::MEMBER_TABLE."` WHERE ("
      . " ( (ISNULL(`BillToParty`) OR `BillToParty` <= 0) AND `MusicianId` = ".$musicianId." ) "
      . " OR "
      . " `BillToParty` = ".$musicianId
      . " )";
    $result = mySQL::query($query, $handle);
    while ($row = mySQL::fetch($result)) {
      $rateKey = $row['Broker'].$row['GeographicalScope'];
      $amount = floatval($row['InsuranceAmount']);
      if (!isset($rates[$rateKey])) {
        Util::error(L::t("Invalid broker/geographical-scope combination: %s - %s",
                         array($row['Broker'], $row['GeographicalScope'])));
      }
      $fee += $amount * $rates[$rateKey];
    }
    $fee += $fee * self::TAXES;
    
    if ($ownConnection) {
      mySQL::close($handle);
    }

    return round($fee, 0);
  }

}; // class definition.

}

?>
