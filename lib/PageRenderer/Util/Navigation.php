<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020, 2021, 2022, 2023, 2024 Claus-Justus Heine
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

namespace OCA\CAFEVDB\PageRenderer\Util;

use OCP\IL10N;
use Psr\Log\LoggerInterface as ILogger;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Database\Legacy\PME\IOptions as PMEOptions;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\PageRenderer;
use OCA\CAFEVDB\Service\ToolTipsService;

/**Support class to generate navigation buttons and the like.
 */
class Navigation
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  const DISABLED = 1;
  const SELECTED = 2;

  /** @var OCP\IL10N */
  protected IL10N $l;

  /** @var OCA\CAFEVDB\Service\ToolTipsService */
  protected $toolTipsService;

  /** @var OCA\CAFEVDB\Database\Legacy\PME\IOptions */
  protected $pmeOptions;

  // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    string $appName,
    IL10N $l10n,
    ILogger $logger,
    ToolTipsService $toolTipsService,
    PMEOptions $pmeOptions,
  ) {
    $this->l = $l10n;
    $this->logger = $logger;
    $this->toolTipsService = $toolTipsService;
    $this->pmeOptions = $pmeOptions;
  }
  // phpcs:enable

  /**
   * Emit select options
   *
   * @param array $options Array with option tags:
   *
   * value => option value
   * name  => option name
   * flags => optional or bit-wise or of self::DISABLED, self::SELECTED
   * title => optional title
   * label => optional label
   * class => optional CSS class
   * group => optional option group
   * groupClass => optional css, only taken into account on group-change
   * groupData => optional data array, only taken into account on group-change
   * data => optional data for option data attributes
   *
   * Optional fields need not be present.
   *
   * @param null|int|array $selectedValues Optional. Set Navigation::SELECTED for the
   * given values. $selectedValues may be a single value or an array of
   * values.
   *
   * @return string HTML fragment.
   */
  public static function selectOptions(array $options, mixed $selectedValues = []):string
  {
    if (empty($selectedValues)) {
      $selectedValues = [];
    }
    $result = '';
    $indent = '';
    if (!is_array($options) || count($options) == 0) {
      return $result;
    }
    if (!is_array($selectedValues)) {
      $selectedValues = [ $selectedValues ];
    }
    $option = $options[0]; // initialize option groups
    $oldGroup = isset($option['group']) ? Util::htmlEscape($option['group']) : false;
    if ($oldGroup) {
      $groupClass = isset($option['groupClass']) ? ' class="'.$option['groupClass'].'"' : '';
      $groupId = 0;
      $groupInfoData = [
        'id' => $groupId,
        'default' => false,
      ];
      $groupData = " data-group-info='".json_encode($groupInfoData, JSON_FORCE_OBJECT)."'"
                 . ' data-group-id="'.$groupId.'"'
                 . (isset($option['groupData']) ? " data-group='".json_encode($option['groupData'], JSON_FORCE_OBJECT)."'" : '');
      $result .= '<optgroup label="'.$oldGroup.'"'.$groupClass.$groupData.'>
      ';
      $indent = '  ';
    }
    foreach ($options as $option) {
      $value = $option['value'];
      $flags = isset($option['flags']) ? $option['flags'] : 0;
      $disabled = $flags & self::DISABLED ? ' disabled' : '';
      if (($flags & self::SELECTED) || in_array($value, $selectedValues)) {
        $selected = ' selected="selected"';
      } else {
        $selected = '';
      }
      $label = isset($option['label']) ? ' label="'.Util::htmlEscape($option['label']).'"' : '';
      $title = isset($option['title']) ? ' title="'.Util::htmlEscape($option['title']).'"' : '';
      $group = isset($option['group']) ? Util::htmlEscape($option['group']) : false;
      $cssClass = isset($option['class']) ? ' class="'.Util::htmlEscape($option['class']).'"' : '';
      $data = '';
      if (isset($option['data'])) {
        $optionData = $option['data'];
        if (!is_array($optionData)) {
          $optionData = [ $optionData ];
        }
        foreach ($optionData as $key => $dataValue) {
          $data .= ' data-'.Util::htmlEscape($key)."='";
          if (is_array($dataValue) || ($dataValue instanceof \JsonSerializable)) {
            $data .= json_encode($dataValue, JSON_FORCE_OBJECT);
          } else {
            // convert to string
            $data .= Util::htmlEscape((string)$dataValue);
          }
          $data .= "'";
        }
      }
      if ($group != $oldGroup) {
        $result .= '</optgroup>
        ';
        $oldGroup = $group;
        ++$groupId;
        $indent = '';
        if ($group) {
          $groupClass = isset($option['groupClass']) ? ' class="'.$option['groupClass'].'"' : '';
          $groupInfoData = [
            'id' => $groupId,
            'default' => false,
          ];
          $groupData = " data-group-info='".json_encode($groupInfoData, JSON_FORCE_OBJECT)."'"
                     . ' data-group-id="'.$groupId.'"'
                     . (isset($option['groupData']) ? " data-group='".json_encode($option['groupData'], JSON_FORCE_OBJECT)."'" : '');
          $result .= '<optgroup label="'.$group.'"'.$groupClass.$groupData.'>
          ';
          $indent = '  ';
        }
      }
      $result .= $indent.'<option value="'.Util::htmlEscape((string)$value).'"'
        . $cssClass.$disabled.$selected.$label.$title.(isset($groupId) ? ' data-group-id="'.$groupId.'"' : '').$data
        . '>'.
              Util::htmlEscape($option['name']).
              '</option>
                 ';
    }
    return $result;
  }

  /**
   * Simple select option array from flat value array.
   *
   * @param array $options
   *
   * @param null|string $selected Option to select by default.
   *
   * @return HTML fragment.
   */
  public static function simpleSelectOptions(array $options, ?string $selected = null)
  {
    $optionDescription = [];
    foreach ($options as $option) {
      $optionDescription[] = ['name'  => $option,
                              'value' => $option,
                              'flags' => ($selected === $option ? self::SELECTED : 0)];
    }
    return self::selectOptions($optionDescription);
  }

  /**
   * Recursively emit hidden input elements to represent the given
   * data. $value may be a nested array.
   *
   * @param mixed $key
   *
   * @param mixed $value
   *
   * @return string HTML fragment.
   */
  public static function persistentCGI(mixed $key, mixed $value = false)
  {
    if (is_array($key)) {
      $result = '';
      foreach ($key as $subkey => $subval) {
        $result .= self::persistentCGI($subkey, $subval);
      }
      return $result;
    } elseif (is_array($value)) {
      $result = '';
      foreach ($value as $subkey => $subval) {
        $result .= self::persistentCGI($key.'['.$subkey.']', $subval)."\n";
      }
      return $result;
    } else {
      return '<input type="hidden" name="'.$key.'" value="'.Util::htmlEscape($value).'"/>'."\n";
    }
  }


  /**
   * Acutally rather a multi-select than a button, meant as drop-down
   * menu. Generates data which can be passed to prependTableButton()
   * below.
   *
   * @return array Return [ 'code' => HTML_FRAGMENT ]
   */
  public function tableExportButton():array
  {
    $data = ''
          .'<span id="pme-export-block" class="pme-export-block pme-button-container">'
          .'<select '
          .'data-placeholder="'.$this->l->t('Export Table').'" '
          .'class="pme-export-choice" '
          .'id="pme-export-choice" '
          .'title="'.$this->toolTipsService['pme-export-choice'].'" '
          .'name="export" >
  <option value=""></option>
  <!-- <option disabled '
    .'title="'.$this->toolTipsService['pme-export-ods'].'" '
    .'value="ODS">'.$this->l->t('LibreOffice Export').'</option> -->
  <option '
    .'title="'.$this->toolTipsService['pme-export-excel'].'" '
    .'value="EXCEL">'.$this->l->t('Excel Export').'</option>
  <option '
    .'title="'.$this->toolTipsService['pme-export-pdf'].'" '
    .'value="PDF">'.$this->l->t('PDF Export').'</option>
  <option '
    .'title="'.$this->toolTipsService['pme-export-csv'].'" '
    .'value="CSV">'.$this->l->t('CSV Export').'</option>
  <option '
    .'title="'.$this->toolTipsService['pme-export-html'].'" '
    .'value="HTML">'.$this->l->t('HTML Export').'</option>
</select></span>';

    $button = ['code' => $data];

    return $button;
  }

  /**
   * Add a new button to the left of the already registered
   * phpMyEdit buttons. This is a dirty hack. But so what. Only the
   * L and F (list and filter) views are augmented.
   *
   * @param array $button The new buttons.
   *
   * @param bool|array $misc Whether or not to include the extra
   * misc-button or a reordering of button positions.
   *
   * @param bool $all Whether to add the button to non-list views.
   *
   * @return array suitable to be plugged in $opts['buttons'].
   */
  public function prependTableButton(array $button, mixed $misc = false, bool $all = false)
  {
    return self::prependTableButtons([ $button ], $misc, $all);
  }

  /**
   * Add a new button to the left of the already registered
   * phpMyEdit buttons. This is a dirty hack. But so what. Only the
   * L and F (list and filter) views are augmented.
   *
   * @param array $buttons The new buttons.
   *
   * @param bool|array $misc Whether or not to include the extra
   * misc-button or a reordering of button positions.
   *
   * @return array suitable to be plugged in $opts['buttons'].
   */
  public function prependTableButtons(array $buttons, mixed $misc = false)
  {
    $defaultButtonsNoB = [
      'L' => [
        'add',
        '<<', '<',
        'goto',
        '>', '>>',
        'rows_per_page',
        'reload',
      ],
      'F' => [
        'add',
        '<<', '<',
        'goto',
        '>', '>>',
        'rows_per_page',
        'reload',
      ],
      'A' => ['save', 'apply', 'more', 'cancel'],
      'C' => ['save', 'more', 'cancel', 'reload'],
      'P' => ['save', 'apply', 'cancel'],
      'D' => ['save', 'cancel', 'reload'],
      'V' => ['change', 'copy', 'delete', 'cancel', 'reload']
    ];

    if ($misc === true) {
      $misc = [ 'misc', 'placeholder' ];
      foreach ($buttons as &$modButton) {
        $modButton['name'] = 'placeholder';
      }
    } elseif ($misc === false && !empty($buttons)) {
      $misc = [ 'placeholder' ];
      foreach ($buttons as &$modButton) {
        $modButton['name'] = 'placeholder';
      }
    } elseif (!is_array($misc)) {
      $misc = [];
    }

    if (!isset($misc['up']) && !isset($misc['down'])) {
      $misc = [ 'up' => $misc, 'down' => $misc ];
    }
    $misc = array_merge([ 'up' => [], 'down' => [] ], $misc);
    foreach ($misc as $vPos => &$miscDef) {
      $vPos = $vPos;
      if (!isset($miscDef['left']) && !isset($miscDef['right'])) {
        $miscDef = [ 'left' => $miscDef, 'right' => [] ];
      }
      $miscDef = array_merge([ 'left' => [], 'right' => [] ], $miscDef);
    }

    $result = [];
    $cssPositions = [ 'up' => 'top', 'down' => 'bottom' ];
    foreach (['up', 'down'] as $verticalPosition) {
      $miscVPos = $misc[$verticalPosition];
      $defaultButtons = $defaultButtonsNoB;
      $defaultButtons['L'] = array_merge($miscVPos['left'], $defaultButtons['L'], $miscVPos['right']);
      $defaultButtons['F'] = array_merge($miscVPos['left'], $defaultButtons['F'], $miscVPos['right']);

      foreach ($defaultButtons as $key => $value) {
        $positionValue = [];
        foreach ($value as $oneButton) {
          $replacement = false;
          foreach ($buttons as $button) {
            if ($button['name'] == $oneButton) {
              $replacement = true;
              if (isset($button['code'])) { // 'code' is a magic PME thing
                $button = preg_replace('/\sid="([^"]*)"/', ' id="$1-'.$verticalPosition.'"', $button);
                $button = preg_replace('/\sfor="([^"]*)"/', ' for="$1-'.$verticalPosition.'"', $button);
                $button = preg_replace('/class="([^"]*)"/', 'class="$1 '.$cssPositions[$verticalPosition].' '.$verticalPosition.'"', $button);
                $button = str_replace('{POSITION}', $cssPositions[$verticalPosition], $button);
              }
              $positionValue[] = $button;
            }
          }
          if (!$replacement) {
            $positionValue[] = $oneButton;
          }
        }
        $result[$key][$verticalPosition] = $positionValue;
      }
    }
    return $result;
  }

  /**
   * @param array $buttons
   *
   * @return string HTML fragment
   *
   * @see htmlTagsFromArray
   */
  public function buttonsFromArray(array $buttons)
  {
    return self::htmlTagsFromArray($buttons);
  }

  /**
   * Generate some html tags. Up to now only buttons and option
   * elements.
   *
   * @param array $tags
   *
   * @return string HTML fragment.
   */
  public function htmlTagsFromArray(array $tags):string
  {
    // Global setup, if any
    $pre = $post = $between = '';
    if (isset($tags['pre'])) {
      $pre = $tags['pre'];
      unset($tags['pre']);
    }
    if (isset($tags['post'])) {
      $post = $tags['post'];
      unset($tags['post']);
    }
    if (isset($tags['between'])) {
      $between = $tags['between'];
      unset($tags['between']);
    }

    // Per element setup
    $html = $pre;
    foreach ($tags as $key => $tag) {
      $type  = isset($tag['type']) ? $tag['type'] : 'button';
      $name  = $tag['name'];
      $value = ' value="'.Util::htmlEscape((isset($tag['value']) ? $tag['value'] : $name)).'"';
      $title = ' title="'.(isset($tag['title']) ? $tag['title'] : $name).'"';
      $id    = isset($tag['id']) ? ' id="'.$tag['id'].'"' : '';
      $class = empty($tag['class']) ? '' : ' class="'.$tag['class'].'"';
      $disabled = (isset($tag['disabled']) && $tag['disabled']) ? ' disabled' : '';
      $formAction = empty($tag['formaction']) ? '' : ' formaction="'.$tag['formaction'].'"';
      $data = '';
      if (isset($tag['data'])) {
        $dataArray = $tag['data'];
        if (!is_array($dataArray)) {
          $dataArray = ['value' => $dataArray];
        }
        foreach ($dataArray as $key => $dataValue) {
          $key = Util::camelCaseToDashes($key);
          $data .= ' data-'.$key.'="'.Util::htmlEscape($dataValue).'"';
        }
      }
      switch ($type) {
        case 'resetbutton':
        case 'submitbutton':
        case 'button':
          if ($type == 'resetbutton') {
            $buttonType = 'reset';
          } elseif ($type == 'submitbutton') {
            $buttonType = 'submit';
          } else {
            $buttonType = 'button';
          }
          $method = isset($tag['method']) ? ' formmethod="'.$tag['method'].'"' : '';
          $style = isset($tag['style']) ? ' style="'.$tag['style'].'"' : '';
          $html .= ''
            .'<button type="'.$buttonType.'" '.$method.$formAction.$disabled.$class.$value.$title.$data.$id.$style.'>';
          if (isset($tag['image'])) {
            $images = false;
            if (!is_array($tag['image'])) {
              $images = [$tag['image']];
            } else {
              $images = $tag['image'];
            }
            $count = 0;
            foreach ($images as $image) {
              if (isset($tag['id'])) {
                $id = $tag['id'].'-img';
                if ($count > 0) {
                  $id .= '-'.$count;
                }
                $id = ' id="'.$id.'" ';
              } else {
                $id = '';
              }
              $class = ' number-'.$count;
              $html .= ''.
                '<img class="svg'.$class.'" '.
                $id.
                'src="'.$image.'" alt="'.$name.'" '.
                ' />';
              ++$count;
            }
          } else {
            $html .= $name;
          }
          $html .= '</button>
          ';
          break;
        case 'input':
          $style = !empty($tag['style']) ? $tag['style'] : '';
          if (isset($tag['image'])) {
            $style = 'background:url(\''.$tag['image'].'\') no-repeat center;'.$style;
            $value  = '';
          }
          $style = $style ? ' style="'.$style.'"' : '';
          $name  = $name != '' ? ' name="'.Util::htmlEscape($name).'"' : '';
          $html .= ''
            .'<input type="button" '.$class.$value.$title.$data.$id.$style.$name.'/>
                  ';
          break;
        case 'option':
          $style = isset($tag['style']) ? ' style="'.$tag['style'].'"' : '';
          $name  = empty($name) ? '' : Util::htmlEscape($name);
          $selected = '';
          if (isset($tag['selected']) && $tag['selected'] !== false) {
            $selected = ' selected="selected"';
          }
          $html .= ''
            .'<option '.$class.$value.$title.$data.$id.$style.$selected.$disabled.'>'.$name.'</option>
                  ';
          break;
        default:
          $html .= '<span>'.$this->l->t('Error: Unknonwn Button Type').'</span>'."\n";
          break;
      }
      $html .= $between;
    }
    $html .= $post;
    return $html;
  }

  /**
   * Generate a couple of standard buttons, identified by Ids.
   *
   * @param string $template The page template to refer to.
   *   - projects Project Overview.
   *   - all Overview of all musicians.
   *   - email Mass-email dialog (obsolete).
   *   - emailhistory History of sent mass-emails (obsolete).
   *   - projectlabel A button which leads to an overview page of the
   *     current project, parameters $projectName and $projectId
   *     habe to be given.
   *   - detailed Instrumentation list for the project. Formerly there
   *     was also a "brief" instrumentation list, which is no longer
   *     there.
   *   - instruments List of all known instruments with cross-link to WikiPedia.
   *   - projectinstrumens Page with instrumentation number for the project.
   *   - debitmandates Page with debit mandates for the project.
   *   - insurances Page with instrument insurances.
   *   - insurancerates Page with knwon insurance rates.
   *   - insurancebrokers Page with knwon brokers, including (maybe) their address.
   *
   * @param string $projectName name of the project if needed.
   *
   * @param int $projectId Id of the project if needed form with submit button.
   *
   * @return string The HTML form control requested.
   */
  public function pageControlElement(
    string $template = PageRenderer\Projects::TEMPLATE,
    string $projectName = '',
    int $projectId = 0,
  ):string {
    $controlid = $template . '-control';
    $controlclass = '';
    $value = '';
    $title = '';
    $post = null;
    $json = null;

    switch ($template) {
      case PageRenderer\Projects::TEMPLATE:
        $value = $this->l->t("View all Projects");
        $title = $this->l->t("Overview over all known projects (start-page).");
        $year = date("Y") - 1;
        $sysPfx = $this->pmeOptions['cgi']['prefix']['sys'];
        $field = 'year';
        $post = [
          'projects' => $value,
          'template' => $template,
          $sysPfx.PHPMyEdit::QUERY_FIELD.$field.'_comp' => '>=',
          $sysPfx.PHPMyEdit::QUERY_FIELD.$field => $year
        ];
        break;

      case PageRenderer\ProjectParticipantFields::TEMPLATE:
        $value = $this->l->t("Project Participant-Fields");
        $title = $this->l->t("Add additional data-fields to the instrumenation table for the project.");
        $post = [
          'projectParticipantFields' => $value,
          'template' => $template,
          'showDisabledFields' => false,
          'projectName' => $projectName,
          'projectId' => $projectId,
        ];
        break;

      case PageRenderer\ProjectPayments::TEMPLATE:
        $value = $this->l->t("Received Payments");
        $title = $this->l->t("A table holding the various payments of participants.");
        $controlclass = 'finance';
        $post = [
          'projectPayments' => $value,
          'template' => $template,
          'projectName' => $projectName,
          'projectId' => $projectId,
        ];
        break;

      case PageRenderer\SepaBulkTransactions::TEMPLATE:
        $value = $this->l->t("Issued Bulk Transactions");
        $title = $this->l->t("A table holding all bulk bank-transactions issued from the orchestra-software.");
        $controlclass = 'finance';
        $post = [
          'debitNotes' => $value,
          'template' => $template,
          'projectName' => $projectName,
          'projectId' => $projectId,
        ];
        break;

      case 'all':
        $value = $this->l->t("Display all Musicians");
        $title = $this->l->t("Display all musicians stored in the data-base, with detailed facilities for filtering and sorting.");
        $post = ['allMusicians' => $value,
                 'template' => 'all-musicians'];
        break;

      case PageRenderer\Blog::TEMPLATE:
      case 'blog':
        $value = $this->l->t("Frontpage Blog");
        $title = $this->l->t("Simplistic blog page with follow apps, used primarily to display hints
if something has changed in the orchestra app.");
        $post = [
          'blog' => $value,
          'template' => PageRenderer\Blog::TEMPLATE,
        ];
        break;

      case 'email':
        $title = $this->l->t(
          "Mass-email form, use with care. Mass-emails will be logged. Recipients will be specified by the Bcc: field in the header, so the recipients are undisclosed to each other."
        );
        $post = [
          'template' => $template,
          'projectName' => $projectName,
          'projectId' => $projectId,
        ];
        break;

      case 'emailhistory':
        $value = $this->l->t("Email History");
        $title = $this->l->t("Display all emails sent by our mass-email form.");
        $post = [
          'template' => 'email-history',
          'projectName' => $projectName,
          'projectId' > $projectId,
        ];
        break;

      case 'projectlabel':
        $title = $this->l->t("Display an overview page for the currently active project.
The overview-page gives the possibility to add events, change the instrumentation
and even edit the public web-pages for the project and other things.");
        $value = $this->l->t('Overview %s', $projectName);
        $json = [
          'projectName' => $projectName,
          'projectId' => $projectId,
        ];
        break;

      case PageRenderer\ProjectParticipants::TEMPLATE:
        $value = $this->l->t("Participants");
        $title = $this->l->t("Detailed display of all registered musicians for the selected project. The table will allow for modification of personal data like email, phone, address etc.");
        $post = [
          'template' => $template,
          'projectName' => $projectName,
          'projectId' => $projectId,
        ];
        break;

      case PageRenderer\Instruments::TEMPLATE:
        $value = $this->l->t("Musical Instruments");
        $title = $this->l->t("Display the list of instruments known by the data-base, possibly add new ones as needed.");
        $post = [
          'template' => $template,
          'projectName' => $projectName,
          'projectId' => $projectId,
        ];
        break;

      case PageRenderer\InstrumentFamilies::TEMPLATE:
        $value = $this->l->t("Instrument Families");
        $title = $this->l->t("Display the list of instrument families and add or change them as needed.");
        $post = [
          'template' => $template,
          'projectName' => $projectName,
          'projectId' => $projectId,
        ];
        break;

      case 'config-check':
        $value = $this->l->t("Test Configuration");
        $title = $this->l->t("Run brief sanity checks on the configuration options.");
        $post = [ 'template' => 'maintenance/configcheck' ];
        break;

      case PageRenderer\ProjectInstrumentationNumbers::TEMPLATE:
        $value = $this->l->t('Instrumentation Numbers');
        $title = $this->l->t('Display the desired instrumentaion numbers, i.e. how many musicians are already registered for each instrument group and how many are finally needed.');
        $json = [
          'template' => $template,
          'projectName' => $projectName,
          'projectId' => $projectId,
        ];
        break;

      case PageRenderer\SepaBankAccounts::TEMPLATE:
        if (!empty($projectId)) {
          $value = $this->l->t('Create Bulk Transactions');
          $title = $this->l->t('Display a table with the bank accounts of the project participants,
with the possibility to issued money transfers as well as debit-notes
to and from the registered bank accounts.');
        } else {
          $value = $this->l->t('Bank Accounts');
          $title = $this->l->t('Display a table with the bank accounts -- including debit-mandates, if any -- of all participants for all projects.');
        }
        $controlclass = 'finance';
        $post = [
          'template' => $template,
          'projectName' => $projectName,
          'projectId' => $projectId,
        ];
        break;

      case PageRenderer\InstrumentInsurances::TEMPLATE:
        $value = $this->l->t("Insurances");
        $title = $this->l->t("Display a table with an overview about the current state of the member's instrument insurances.");
        $controlclass = 'finance';
        $post = [
          'template' => $template,
        ];
        break;

      case PageRenderer\InsuranceRates::TEMPLATE:
        $value = $this->l->t("Insurance Rates");
        $title = $this->l->t("Display a table with the insurance rates for the individual instrument insurances.");
        $controlclass = 'finance';
        $post = [
          'template' => $template,
        ];
        break;

      case PageRenderer\InsuranceBrokers::TEMPLATE:
        $value = $this->l->t("Insurance Brokers");
        $title = $this->l->t("Display a table with the insurance brokers.");
        $controlclass = 'finance';
        $post = [
          'template' => $template,
        ];
        break;

      case PageRenderer\TaxExemptionNotices::TEMPLATE:
        $value = $this->l->t("Notices of Exemption");
        $title = $this->l->t("Display a table an overview table with exemption notices received by tax offices.");
        $controlclass = 'finance';
        $post = [
          'template' => $template,
          'projectName' => $projectName,
          'projectId' => $projectId,
        ];
        break;

      case PageRenderer\DonationReceipts::TEMPLATE:
        $value = $this->l->t("Donation Receipts");
        $title = $this->l->t("Display a table an overview table with donation receipts send out to donators.");
        $controlclass = 'finance';
        $post = [
          'template' => $template,
          'projectName' => $projectName,
          'projectId' => $projectId,
        ];
        break;
    }

    $json = $json ?? $post;
    $post = $post ?? $json;

    $post = http_build_query($post, '', '&');
    $json = htmlspecialchars(json_encode($json));
    $html =<<<__EOT__
      <li class="nav-{$controlid} {$controlclass} tooltip-right" title="$title">
      <a href="?{$post}"
      data-id="{$controlid}"
      data-post="{$post}"
      data-json='{$json}'>
      $value
      </a>
      </li>
      __EOT__;

    return $html;
  }
}
