<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2016-2016 Claus-Justus Heine <himself@claus-justus-heine.de>
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

  /**Display the instruments used or required by a project.
   */
  class ProjectExtra
  {
    const CSS_PREFIX = 'cafevdb-page';
    const TABLE_NAME = 'ProjectExtraFields';
    private $pme;
    private $pme_bare;
    private $execute;
    public $projectId;
    public $projectName;

    public function __construct($execute = true)
    {
      $this->execute = $execute;
      $this->pme = false;
      $this->pme_bare = false;

      $this->projectId = Util::cgiValue('ProjectId', false);
      $this->projectName = Util::cgiValue('ProjectName', false);

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
      if ($this->projectName !== false) {
        return L::t("Extra-Fields for Project %s",
                    array($this->projectName));
      } else {
        return L::t("Extra Fields for Projects");
      }
    }

    public function headerText()
    {
      return $this->shortTitle();
    }

    function display()
    {
      global $debug_query;
      $debug_query = Util::debugMode('query');

      if (Util::debugMode('request')) {
        echo '<PRE>';
        /* print_r($_SERVER); */
        print_r($_POST);
        echo '</PRE>';
      }

      $projectId   = $this->projectId;
      $projectName = $this->projectName;
      $projectMode = $projectId > 0;

      // Inherit a bunch of default options
      $opts = Config::$pmeopts;

      $opts['cgi']['persist'] = array(
        'Template' => 'project-extra',
        'DisplayClass' => 'ProjectExtra',
        'ClassArguments' => array());

      if ($projectMode || true) {
        $opts['cgi']['persist']['ProjectName'] = $projectName;
        $opts['cgi']['persist']['ProjectId']   = $projectId;
      }

      $opts['tb'] = 'ProjectExtraFields';

      // Name of field which is the unique key
      $opts['key'] = 'Id';

      // Type of key field (int/real/string/date etc.)
      $opts['key_type'] = 'int';

      // Sorting field(s)
      $opts['sort_field'] = array(
        'ProjectId',
        'DisplayOrder',
        'Name');

      if ($projectMode !== false) {
        $opts['filters'] = 'ProjectId = '.$this->projectId;
      }

      // Number of records to display on the screen
      // Value of -1 lists all records in a table
      // $opts['inc'] = -1;

      // Options you wish to give the users
      // A - add,  C - change, P - copy, V - view, D - delete,
      // F - filter, I - initial sort suppressed
      $opts['options'] = 'ACPVDF';

      // Number of lines to display on multiple selection filters
      $opts['multiple'] = '6';

      // Navigation style: B - buttons (default), T - text links, G - graphic links
      // Buttons position: U - up, D - down (default)
      //$opts['navigation'] = 'DB';

      // Display special page elements
      $opts['display'] =  array_merge($opts['display'],
                                      array(
                                        'form'  => true,
                                        //'query' => true,
                                        'sort'  => true,
                                        'time'  => true,
                                        'tabs'  => false
                                        ));

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
         ['select'] T - text, N - numeric, D - drop-down, M - multiple selection,
         O - radio buttons, C - check-boxes
         ['options'] optional parameter to control whether a field is displayed
         L - list, F - filter, A - add, C - change, P - copy, D - delete, V - view
         Another flags are:
         R - indicates that a field is read only
         0 - indicates that a field is disabled
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
        'options'  => '', // auto increment
        'maxlen'   => 11,
        'default'  => '0',
        'sort'     => true,
        );

      /************************************************************************
       *
       * Bug: the following is just too complicated.
       *
       * Goal:
       * Display a list of projects, sorted by year, then by name, constraint:
       *
       */

      // fetch the list of all projects in order to provide a somewhat
      // cooked filter list
      $allProjects = Projects::fetchProjects(false /* no db handle */,
                                             true /* include years */,
                                             true /* most recent years first */);
      $projectQueryValues = array('*' => '*'); // catch-all filter
      $projectQueryValues[''] = L::t('no projects yet');
      $projects = array();
      $groupedProjects = array();
      foreach ($allProjects as $id => $proj) {
        $projectQueryValues[$id] = $proj['Jahr'].': '.$proj['Name'];
        $projects[$id] = $proj['Name'];
        $groupedProjects[$id] = $proj['Jahr'];
      }

      $opts['fdd']['ProjectId'] = array(
        'name'      => L::t('Project-Name'),
        'css' => array('postfix' => ' project-extra-project-name'),
        'options'   => ($projectMode ? 'VCDAP' : 'FLVCDAP'),
        'select|DV' => 'T', // delete, filter, list, view
        'select|ACPFL' => 'D',  // add, change, copy
        'maxlen'   => 20,
        'size'     => 16,
        'default'  => '-1',
        'sort'     => true,
        'values|ACP' => array(
          'table' => 'Projekte',
          'column' => 'Id',
          'description' => 'Name',
          'groups' => 'Jahr',
          'orderby' => '$table.`Jahr` DESC',
          'join' => '$main_table.ProjectId = $join_table.Id',
          ),
        'values|DVFL' => array(
          'table' => 'Projekte',
          'column' => 'Id',
          'description' => 'Name',
          'groups' => 'Jahr',
          'orderby' => '$table.`Jahr` DESC',
          'join' => '$main_table.`ProjectId` = $join_table.`Id`',
          'filters' => '$table.`Id` IN (SELECT `ProjectId` FROM $main_table)',
          ),
        );

      // will hide this later
      $opts['fdd']['FieldIndex'] = array(
        'name' => L::t('Field-Index'),
        'css' => array('postfix' => ' field-index'),
        //'options' => '',
        'selet' => 'N',
        'maxlen' => 5,
        'sort' => true,
        'input' => 'R',
        'tooltip' => Config::toolTips('extra-fields-field-index'),
        );

      $opts['fdd']['Name'] = array(
        'name' => L::t('Field-Name'),
        'css' => array('postfix' => ' field-name'),
        'select' => 'T',
        'maxlen' => 20,
        'sort' => true,
        'tooltip' => Config::toolTips('extra-fields-field-name'),
        );

      $opts['fdd']['DisplayOrder'] = array(
        'name' => L::t('Display-Order'),
        'css' => array('postfix' => ' display-order'),
        'select' => 'N',
        'maxlen' => 5,
        'sort' => true,
        'tooltip' => Config::toolTips('extra-fields-display-order'),
        );

      // TODO: maybe get rid of enums and sets alltogether
      $typeValues = mySQL::multiKeys(self::TABLE_NAME, 'Type');
      $opts['fdd']['Type'] = array(
        'name' => L::t('Type'),
        'css' => array('postfix' => ' field-type'),
        'size' => 20,
        'maxlen' => 20,
        'select' => 'D',
        'sort' => false,
        'values' => $typeValues,
        'tooltip' => Config::toolTips('extra-fields-type'),
        );

      $opts['fdd']['AllowedValues'] = array(
        'name' => L::t('Allowed Values'),
        'css' => array('postfix' => ' allowed-values'),
        'select' => 'T',
        'maxlen' => 20,
        'sort' => true,
        'display|LF' => array('popup' => 'data'),
        'tooltip' => Config::toolTips('extra-fields-allowed-values'),
        );

      $opts['fdd']['DefaultValue'] = array(
        'name' => L::t('Default Value'),
        'css' => array('postfix' => ' default-value'),
        'select' => 'T',
        'maxlen' => 20,
        'sort' => true,
        'display|LF' => array('popup' => 'data'),
        'tooltip' => Config::toolTips('extra-fields-default-value'),
        );

      if (Config::$expertmode) {

        $opts['fdd']['Tab'] = array(
          'name' => L::t('Tab'),
          'css' => array('postfix' => ' extra-fields-tab'),
          'select' => 'T',
          'maxlen' => 20,
          'sort' => true,
          'tooltip' => Config::toolTips('extra-fields-tab'),
          );

        $ownCloudGroups = \OC_Group::getGroups();
        $opts['fdd']['Readers'] = array(
          'name' => L::t('Readers'),
          'css' => array('postfix' => ' readers chosen-dropup user-groups'),
          'select' => 'M',
          'values' => $ownCloudGroups,
          'maxlen' => 10,
          'sort' => true,
          'display' => array('popup' => 'data'),
          'tooltip' => Config::toolTips('extra-fields-readers'),
          );

        $opts['fdd']['Writers'] = array(
          'name' => L::t('Writers'),
          'css' => array('postfix' => ' writers chosen-dropup user-groups'),
          'select' => 'M',
          'values' => $ownCloudGroups,
          'maxlen' => 10,
          'sort' => true,
          'display' => array('popup' => 'data'),
          'tooltip' => Config::toolTips('extra-fields-writers'),
          );
      }

      $opts['triggers']['update']['before'][]  = 'CAFEVDB\Util::beforeAnythingTrimAnything';
      $opts['triggers']['update']['before'][]  = 'CAFEVDB\ProjectExtra::beforeUpdateTrigger';
      $opts['triggers']['update']['before'][] =  'CAFEVDB\Util::beforeUpdateRemoveUnchanged';
      $opts['triggers']['insert']['before'][]  = 'CAFEVDB\Util::beforeAnythingTrimAnything';
      $opts['triggers']['insert']['before'][]  = 'CAFEVDB\ProjectExtra::beforeInsertTrigger';

      $opts['execute'] = $this->execute;

      $pmeSysPfx = Config::$pmeopts['cgi']['prefix']['sys'];
      $opts['cgi']['append'][$pmeSysPfx.'fl'] = 0;

      $pme = new \phpMyEdit($opts);
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
    public static function beforeInsertTrigger(&$pme, $op, $step, $oldvals, &$changed, &$newvals)
    {
      $projectId = $newvals['ProjectId'];
      if (empty($projectId)) {
        return false;
      }
      // insert the beast with the next available field id
      $index = mySQL::selectFirstHoleFromTable(self::TABLE_NAME, 'FieldIndex',
                                               "`ProjectId` = ".$projectId,
                                               $pme->dbh);

      if ($index === false) {
        return false;
      }

      $newvals['FieldIndex'] = $index;

      // make sure writer-acls are a subset of reader-acls
      $writers = preg_split('/\s*,\s*/', $newvals['Writers']);
      $readers = preg_split('/\s*,\s*/', $newvals['Readers']);
      $missing = array_diff($writers, $readers);
      if (!empty($missing)) {
        $readers = array_merge($readers, $missing);
        $newvals['Readers'] = implode(',', $readers);
      }

      return true;
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
    public static function beforeUpdateTrigger(&$pme, $op, $step, $oldvals, &$changed, &$newvals)
    {
      $projectId = $newvals['ProjectId'];
      if (empty($projectId)) {
        return false;
      }

      // make sure writer-acls are a subset of reader-acls
      $writers = preg_split('/\s*,\s*/', $newvals['Writers']);
      $readers = preg_split('/\s*,\s*/', $newvals['Readers']);
      $missing = array_diff($writers, $readers);
      if (!empty($missing)) {
        $readers = array_merge($readers, $missing);
        $newvals['Readers'] = implode(',', $readers);
        if (!in_array('Readers', $changed)) {
          $changed[] = 'Readers';
        }
      }

      return true;
    }

  }; // class ProjectExtra

}

?>
