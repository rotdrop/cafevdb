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
    const TABLE = 'ProjectIncomingPayments';
    private $scope;
    private $pme;
    private $pme_bare;
    private $execute;

    public function __construct($execute = true)
    {
      $this->execute = $execute;
      $this->pme = false;
      $this->pme_bare = false;

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
      return L::t('Project Incoming Payments');
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

      $projectId = Util::cgiValue('ProjectId', false);
      $projectName = Util::cgiValue('ProjectName', false);

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

      // Inherit a bunch of default options
      $opts = Config::$pmeopts;

      $opts['cgi']['persist'] = array(
        'Template' => 'insurance-brokers',
        'DisplayClass' => 'ProjectPayments',
        'ClassArguments' => array());

      $opts['cgi']['persist']['ProjectName'] = $projectName;
      $opts['cgi']['persist']['ProjectId']   = $projectId;

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

      /* Get the user's default language and use it if possible or you can
         specify particular one you want to use. Refer to official documentation
         for list of available languages. */
      //$opts['language'] = $_SERVER['HTTP_ACCEPT_LANGUAGE']; // . '-UTF8';

      /* Table-level filter capability. If set, it is included in the WHERE clause
         of any generated SELECT statement in SQL query. This gives you ability to
         work only with subset of data from table.

         $opts['filters'] = "column1 like '%11%' AND column2<17";
         $opts['filters'] = "section_id = 9";
         $opts['filters'] = "PMEtable0.sessions_count > 200";
      */

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

      $opts['fdd']['ProjectId'] = array(
        'name'     => L::t('Project'),
        'css'      => array('postfix' => ' project-id'),
        'sql'      => 'PMEjoin2.ProjektId',
        'input'    => 'VH',
        'values'   => array(
          'table'  => 'Besetzungen',
          'column' => 'Id',
          'description' => 'ProjektId',
          'join'   => '$join_table.Id = $main_table.InstrumentationId'
          ),
        );

      $opts['fdd']['ProjectName'] = array(
        'name'     => L::t('Project'),
        'css'      => array('postfix' => ' project-id'),
        'sql'      => 'PMEjoin3.Name',
        'input'    => 'V',
        'values'   => array(
          'table'  => 'Projekte',
          'column' => 'Id',
          'description' => 'Name',
          'join'   => '$join_table.Id = PMEjoin2.ProjektId'
          ),
        'select'   => 'T',
        'maxlen'   => 40,
        'sort'     => true,
        );

      $opts['fdd']['Amount'] = Config::$opts['money'];
      $opts['fdd']['Amount']['name'] = L::t('Amount');

      $opts['fdd']['DateInitiated'] = Config::$opts['date'];
      $opts['fdd']['DateInitiated']['name'] = L::t('Date Issued');

      $opts['fdd']['DateOfReceipt'] = Config::$opts['date'];
      $opts['fdd']['DateOfReceipt']['name'] = L::t('Date of Receipt');

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
