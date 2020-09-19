<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Common;

/**Support class to generate navigation buttons and the like.
 */
class Navigation
{
    const DISABLED = 1;
    const SELECTED = 2;

    /**Emit select options
     *
     * @param $options Array with option tags:
     *
     * value => option value
     * name  => option name
     * flags => optional or bit-wise or of self::DISABLED, self::SELECTED
     * title => optional title
     * label => optional label
     * group => optional option group
     * groupClass => optional css, only taken into account on group-change
     *
     * Optional fields need not be present.
     */
    public static function selectOptions($options)
    {
        $result = '';
        $indent = '';
        if (!is_array($options) || count($options) == 0) {
            return $result;
        }
        $oldGroup = isset($options[0]['group']) ? Util::htmlEscape($options[0]['group']) : false;
        if ($oldGroup) {
            $groupClass = isset($options[0]['groupClass']) ? ' class="'.$options[0]['groupClass'].'"' : '';
            $result .= '<optgroup label="'.$oldGroup.'"'.$groupClass.'>
';
            $indent = '  ';
        }
        foreach($options as $option) {
            $flags = isset($option['flags']) ? $option['flags'] : 0;
            $disabled = $flags & self::DISABLED ? ' disabled="disabled"' : '';
            $selected = $flags & self::SELECTED ? ' selected="selected"' : '';
            $label    = isset($option['label']) ? ' label="'.Util::htmlEscape($option['label']).'"' : '';
            $title    = isset($option['title']) ? ' title="'.Util::htmlEscape($option['title']).'"' : '';
            $group = isset($option['group']) ? Util::htmlEscape($option['group']) : false;
            if ($group != $oldGroup) {
                $result .= '</optgroup>
';
                $oldGroup = $group;
                $indent = '';
                if ($group) {
                    $groupClass = isset($option['groupClass']) ? ' class="'.$option['groupClass'].'"' : '';
                    $result .= '<optgroup label="'.$group.'"'.$groupClass.'>
';
                    $indent = '  ';
                }
            }
            $result .= $indent.'<option value="'.Util::htmlEscape($option['value']).'"'.
                    $disabled.$selected.$label.$title.
                    '>'.
                    Util::htmlEscape($option['name']).
                    '</option>
';
        }
        return $result;
    }

    /**Simple select option array from flat value array. */
    public static function simpleSelectOptions($options, $selected = null)
    {
        $optionDescription = array();
        foreach ($options as $option) {
            $optionDescription[] = array('name'  => $option,
                                         'value' => $option,
                                         'flags' => ($selected === $option ? self::SELECTED : 0));
        }
        return self::selectOptions($optionDescription);
    }

    /**Recursively emit hidden input elements to represent the given
     * data. $value may be a nested array.
     */
    public static function persistentCGI($key, $value = false)
    {
        if (is_array($key)) {
            $result = '';
            foreach ($key as $subkey => $subval) {
                $result .= self::persistentCGI($subkey, $subval);
            }
            return $result;
        } else if (is_array($value)) {
            $result = '';
            foreach($value as $subkey => $subval) {
                $result .= self::persistentCGI($key.'['.$subkey.']', $subval)."\n";
            }
            return $result;
        } else {
            return '<input type="hidden" name="'.$key.'" value="'.Util::htmlEscape($value).'"/>'."\n";
        }
    }


    /**Acutally rather a multi-select than a button, meant as drop-down
     * menu. Generates data which can be passed to prependTableButton()
     * below.
     */
    public static function tableExportButton()
    {
        $data = ''
              .'<span id="pme-export-block" class="pme-export-block">'
              .'<label>'
              .'<select '
              .'data-placeholder="'.L::t('Export Table').'" '
              .'class="pme-export-choice" '
              .'id="pme-export-choice" '
              .'title="'.Config::toolTips('pme-export-choice').'" '
              .'name="export" >
  <option value=""></option>
  <option '
        .'title="'.Config::toolTips('pme-export-excel').'" '
        .'value="EXCEL">'.L::t('Excel Export').'</option>
  <option '
        .'title="'.Config::toolTips('pme-export-htmlexcel').'" '
        .'value="SSML">'.L::t('HTML/Spreadsheet').'</option>
  <option '
        .'title="'.Config::toolTips('pme-export-csv').'" '
        .'value="CSV">'.L::t('CSV Export').'</option>
  <option '
        .'title="'.Config::toolTips('pme-export-html').'" '
        .'value="HTML">'.L::t('HTML Export').'</option>
</select></label></span>';

        $button = array('code' => $data);

        return $button;
    }

    /**Add a new button to the left of the already registered
     * phpMyEdit buttons. This is a dirty hack. But so what. Only the
     * L and F (list and filter) views are augmented.
     *
     * @param[in] $button The new buttons.
     *
     * @param[in] $misc Whether or not to include the extra misc-button.
     *
     * @param[in] $all Whether to add the button to non-list views.
     *
     * @return Array suitable to be plugged in $opts['buttons'].
     */
    public static function prependTableButton($button, $misc = false, $all = false)
    {
        return self::prependTableButtons(array($button), $misc, $all);
    }

    /**Add a new button to the left of the already registered
     * phpMyEdit buttons. This is a dirty hack. But so what. Only the
     * L and F (list and filter) views are augmented.
     *
     * @param[in] $buttons The new buttons.
     *
     * @param[in] $misc Whether or not to include the extra misc-button.
     *
     * @param[in] $all Whether to add the button to non-list views.
     *
     * @return Array suitable to be plugged in $opts['buttons'].
     */
    public static function prependTableButtons($buttons, $misc = false, $all = false)
    {
        // Cloned from phpMyEdit class:
        if (!$misc) {
            $default_buttons_no_B = array(
                'L' => array('<<', '<',
                             'placeholder', 'add',
                             '>', '>>',
                             'goto', 'rows_per_page','reload'),
                'F' => array('<<', '<',
                             'placeholder', 'add',
                             '>', '>>',
                             'goto', 'rows_per_page','reload'),
                'A' => array('save', 'apply', 'more', 'cancel'),
                'C' => array('save', 'more', 'cancel', 'reload'),
                'P' => array('save', 'apply', 'cancel'),
                'D' => array('save', 'cancel'),
                'V' => array('change', 'copy', 'delete', 'cancel', 'reload')
            );
        } else {
            $default_buttons_no_B = array(
                'L' => array('<<','<',
                             'misc', 'placeholder', 'add',
                             '>','>>',
                             'goto','rows_per_page','reload'),
                'F' => array('<<','<',
                             'misc', 'placeholder', 'add',
                             '>','>>',
                             'goto','rows_per_page','reload'),
                'A' => array('save', 'apply', 'more', 'cancel'),
                'C' => array('save', 'more', 'cancel', 'reload'),
                'P' => array('save', 'apply', 'cancel'),
                'D' => array('save', 'cancel'),
                'V' => array('change', 'copy', 'delete', 'cancel', 'reload')
            );
        }

        $result = array();
        foreach ($default_buttons_no_B as $key => $value) {
            if ($all && stristr("ACPDV", $key) !== false) {
                array_unshift($value, $button);
            }
            $upValue = array();
            $downValue = array();
            foreach ($value as $oneButton) {
                if ($oneButton === 'placeholder') {
                    foreach ($buttons as $button) {
                        if (isset($button['code'])) {
                            $buttonUp = preg_replace('/id="([^"]*)"/', 'id="$1-up"', $button);
                            $buttonDown = preg_replace('/id="([^"]*)"/', 'id="$1-down"', $button);
                            $buttonUp = preg_replace('/class="([^"]*)"/', 'class="$1 top"', $buttonUp);
                            $buttonDown = preg_replace('/class="([^"]*)"/', 'class="$1 bottom"', $buttonDown);
                            $upValue[] = $buttonUp;
                            $downValue[] = $buttonDown;
                        } else {
                            $upValue[]   = $button;
                            $downValue[] = $button;
                        }
                    }
                } else {
                    $upValue[]   = $oneButton;
                    $downValue[] = $oneButton;
                }
            }
            $result[$key] = array('up' => $upValue, 'down' => $downValue);
        }

        return $result;
    }

    /**Take any dashed lower-case string and convert to camel-acse.
     *
     * @param $string the string to convert.
     *
     * @param $capitalizeFirstCharacter self explaining.
     */
    public static function dashesToCamelCase($string, $capitalizeFirstCharacter = false)
    {
        $str = str_replace(' ', '', ucwords(str_replace('-', ' ', $string)));

        if (!$capitalizeFirstCharacter) {
            $str[0] = strtolower($str[0]);
        }

        return $str;
    }

    /**Take an camel-case string and convert to lower-case with dashes
     * between the words. First letter may or may not be upper case.
     */
    public static function camelCaseToDashes($string)
    {
        return strtolower(preg_replace('/([A-Z])/', '-$1', lcfirst($string)));
    }

    public static function buttonsFromArray($buttons)
    {
        return self::htmlTagsFromArray($buttons);
    }

    /**Generate some html tags. Up to now only buttons and option
     * elements.
     */
    public static function htmlTagsFromArray($tags)
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
            $class = ' class="'.$tag['class'].'"';
            $disabled = (isset($tag['disabled']) && $tag['disabled']) ? ' disabled="disabled"' : '';
            $data = '';
            if (isset($tag['data'])) {
                $dataArray = $tag['data'];
                if (!is_array($dataArray)) {
                    $dataArray = array('value' => $dataArray);
                }
                foreach ($dataArray as $key => $dataValue) {
                    $key = self::camelCaseToDashes($key);
                    $data .= ' data-'.$key.'="'.Util::htmlEscape($dataValue).'"';
                }
            }
            switch ($type) {
            case 'resetbutton':
            case 'submitbutton':
            case 'button':
                if ($type == 'resetbutton') {
                    $buttonType = 'reset';
                } else if ($type == 'submitbutton') {
                    $buttonType = 'submit';
                } else {
                    $buttonType = 'button';
                }
                $style = isset($tag['style']) ? ' style="'.$tag['style'].'"' : '';
                $html .= ''
                      .'<button type="'.$buttonType.'" '.$disabled.$class.$value.$title.$data.$id.$style.'>';
                if (isset($tag['image'])) {
                    $images = false;
                    if (!is_array($tag['image'])) {
                        $images = array($tag['image']);
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
                $style = isset($tag['style']) ? $tag['style'] : '';
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
                $selected = '';
                if (isset($tag['selected']) && $tag['selected'] !== false) {
                    $selected = ' selected="selected"';
                }
                $html .= ''
                      .'<option '.$class.$value.$title.$data.$id.$style.$selected.$disabled.'>'.$name.'</option>
';
                break;
            default:
                $html .= '<span>'.L::t('Error: Unknonwn Button Type').'</span>'."\n";
                break;
            }
            $html .= $between;
        }
        $html .= $post;
        return $html;
    }

    /**Generate a couple of standard buttons, identified by Ids.
     *
     * @param[in] string $id One of
     *   - an array; in this case buttonsFromArray() is called with the supplied data.
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
     * @param[in] bool $asListItem Generate a list item instead of a
     * @param[in] string $projectName name of the project if needed.
     * @param[in] int $projectId Id of the project if needed.
     * form with submit button.
     *
     * @return string The HTML form control requested.
     */
    public static function pageControlElement($id = 'projects',
                                              $projectName = '',
                                              $projectId = -1)
    {
        $controlid = $id.'control';
        $controlclass = '';
        $form = '';
        $value = '';
        $title = '';
        $post = array();
        $json = array();

        switch ($id) {

        case 'projects':
            $value = L::t("View all Projects");
            $title = L::t("Overview over all known projects (start-page).");
            $year = date("Y") - 1;
            $post = array('Projects' => $value,
                          'Template' => 'projects',
                          'PME_sys_qf1_comp' => '>=',
                          'PME_sys_qf1' => $year);
            break;

        case 'project-extra':
            $value = L::t("Project Extra-Fields");
            $title = L::t("Add additional data-fields to the instrumenation table for the project.");
            $post = array('ProjectExtraFields' => $value,
                          'Template' => 'project-extra',
                          'ShowDisabledFields' => false,
                          'ProjectName' => $projectName,
                          'ProjectId' => $projectId);
            break;

        case 'project-payments':
            $value = L::t("Received Payments");
            $title = L::t("A table holding the various payments of participants.");
            $controlclass = 'finance';
            $post = array('ProjectPayments' => $value,
                          'Template' => 'project-payments',
                          'ProjectName' => $projectName,
                          'ProjectId' => $projectId);
            break;

        case 'debit-notes':
            $value = L::t("Debit Notes");
            $title = L::t("A table holding all debit notes issued from the orchestra-software.");
            $controlclass = 'finance';
            $post = array('DebitNotes' => $value,
                          'Template' => 'debit-notes',
                          'ProjectName' => $projectName,
                          'ProjectId' => $projectId);
            break;

        case 'all':
            $value = L::t("Display all Musicians");
            $title = L::t("Display all musicians stored in the data-base, with detailed facilities for filtering and sorting.");
            $post = array('AllMusicians' => $value,
                          'Template' => 'all-musicians');
            break;

        case 'email':
            $title = L::t("Mass-email form, use with care. Mass-emails will be logged. Recipients will be specified by the Bcc: field in the header, so the recipients are undisclosed to each other.");
            $post = array('Template' => 'email',
                          'ProjectName' => $projectName,
                          'ProjectId' => $projectId);
            break;

        case 'emailhistory':
            $value = L::t("Email History");
            $title = L::t("Display all emails sent by our mass-email form.");
            $post = array('Template' => 'email-history',
                          'ProjectName' => $projectName,
                          'ProjectId' > $projectId);
            break;

        case 'projectlabel':
            Config::init();
            $syspfx = Config::$pmeopts['cgi']['prefix']['sys'];
            $opname = $syspfx.'operation';
            $opwhat = 'View?'.$syspfx.'rec='.$projectId;
            $opclass  = 'pme-view';
            $title = L::t("Display an overview page for the currently active project.
The overview-page gives the possibility to add events, change the instrumentation
and even edit the public web-pages for the project and other things.");
            $value = $projectName;
            $json = array('ProjectName' => $projectName,
                          'ProjectId' => $projectId);
            break;

        case 'detailed':
            $value = L::t("Instrumentation");
            $title = L::t("Detailed display of all registered musicians for the selected project. The table will allow for modification of personal data like email, phone, address etc.");
            $post = array('Template' => 'detailed-instrumentation',
                          'ProjectName' => $projectName,
                          'ProjectId' => $projectId);
            break;

        case 'instruments':
            $value = L::t("Add Instruments");
            $title = L::t("Display the list of instruments known by the data-base, possibly add new ones as needed.");
            $post = array('Template' => 'instruments',
                          'ProjectName' => $projectName,
                          'ProjectId' => $projectId);
            break;

        case 'projectinstruments':
            $value = L::t('Instrumentation Numbers');
            $title = L::t('Display the desired instrumentaion numbers, i.e. how many musicians are already registered for each instrument group and how many are finally needed.');
            $json = array('Template' => 'project-instruments',
                          'ProjectName' => $projectName,
                          'ProjectId' => $projectId);
            break;

        case 'debit-mandates':
            $value = L::t('Debit Mandates');
            $title = L::t('Display a table with the SEPA debit mandates related to the project.');
            $controlclass = 'finance';
            $post = array('Template' => 'sepa-debit-mandates',
                          'ProjectName' => $projectName,
                          'ProjectId' => $projectId);
            break;

        case 'insurances':
            $value = L::t("Insurances");
            $title = L::t("Display a table with an overview about the current state of the member's instrument insurances.");
            $controlclass = 'finance';
            $post = array('Template' => 'instrument-insurance');
            break;

        case 'insurancerates':
            $value = L::t("Insurance Rates");
            $title = L::t("Display a table with the insurance rates for the individual instrument insurances.");
            $controlclass = 'finance';
            $post = array('Template' => 'insurance-rates');
            break;

        case 'insurancebrokers':
            $value = L::t("Insurance Brokers");
            $title = L::t("Display a table with the insurance brokers.");
            $controlclass = 'finance';
            $post = array('Template' => 'insurance-brokers');
            break;
        }

        $post = http_build_query($post, '', '&');
        $json = htmlspecialchars(json_encode($json));
        $html =<<<__EOT__
<li class="nav-{$controlid} {$controlclass} tooltip-right" title="$title">
  <a href="#"
     data-id="{$controlid}"
     data-post="{$post}"
     data-json='{$json}'>
$value
  </a>
</li>
__EOT__;

        return $html;
    }
};

/**Try to correct common human input "errors", respectively
 * sloppiness. Not much, ATM. */
class FuzzyInput
{
    const HTML_TIDY = 1;
    const HTML_PURIFY = 2;
    const HTML_ALL = ~0;

    /**Check $input for "transposition error". Interchange each
     * consecutive pair of letters, try to validate by $callback, return
     * an array of transposed input strings, for which $callback
     * returned true.
     */
    public static function transposition($input, $callback)
    {
        if (!is_callable($callback)) {
            return array();
        }
        $result = array();
        $len = strlen($input);
        for ($idx = 0; $idx < $len - 1; ++$idx) {
            $victim = $input;
            $victim[$idx] = $input[$idx+1];
            $victim[$idx+1] = $input[$idx];
            if ($callback($victim)) {
                $result[] = $victim;
            }
        }
        return $result;
    }

    /**Try to get the number of bugs from a currency value. We act
     * quite simple: Strip the currency symbols from the users locale
     * and then try to parse the number, first with the users locale,
     * then with the C locale.
     *
     * @return mixed Either @c false or the floating point value
     * extracted from the input string.
     */
    public static function currencyValue($value)
    {
        $amount = preg_replace('/\s+/u', '', $value);
        $fmt = new \NumberFormatter(Util::getLocale(), \NumberFormatter::CURRENCY);
        $cur = $fmt->getSymbol(\NumberFormatter::CURRENCY_SYMBOL);
        $amount = str_replace($cur, '', $amount);
        $cur = $fmt->getSymbol(\NumberFormatter::INTL_CURRENCY_SYMBOL);
        $amount = str_replace($cur, '', $amount);
        $fmt = new \NumberFormatter(Util::getLocale(), \NumberFormatter::DECIMAL);
        $parsed = $fmt->parse($amount);
        if ($parsed === false) {
            $fmt = new \NumberFormatter('en_US_POSIX', \NumberFormatter::DECIMAL);
            $parsed = $fmt->parse($amount);
        }
        return $parsed !== false ? sprintf('%.02f', $parsed) : $parsed;
    }

    /**Try to correct HTML code.*/
    public static function purifyHTML($dirtyHTML, $method = self::HTML_PURIFY)
    {
        $purifier = null;
        if ($method & self::HTML_PURIFY) {
            $cacheDir = Config::userCacheDirectory('HTMLPurifier');
            $config = \HTMLPurifier_Config::createDefault();
            $config->set('Cache.SerializerPath', $cacheDir);
            $config->set('HTML.TargetBlank', true);
            // TODO: maybe add further options
            $purifier = new \HTMLPurifier($config);
        }

        $tidy = null;
        $tidyConfig = null;
        if ($method & self::HTML_TIDY) {
            $tidyConfig = array(
                'indent'         => true,
                'output-xhtml'   => true,
                'show-body-only' => true,
                'wrap'           => 200
            );
            $tidy = new \tidy;
        }

        if (!empty($tidy)) {
            $tidy->parseString($dirtyHTML, $tidyConfig, 'utf8');
            $tidy->cleanRepair();
            $dirtyHTML = (string)$tidy;
        }

        if (!empty($purifier)) {
            $dirtyHTML = $purifier->purify($dirtyHTML);
        }

        return $dirtyHTML;
    }

};
// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
