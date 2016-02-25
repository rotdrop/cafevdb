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

namespace CAFEVDB {

  \OCP\JSON::checkLoggedIn();
  \OCP\JSON::checkAppEnabled('cafevdb');
  \OCP\JSON::callCheck();

  // Check if we are a group-admin, otherwise bail out.
  if (!Config::inGroup()) {
    \OC_JSON::error(array("data" => array("message" => "Unsufficient privileges.")));
    return;
  }

  $debugText = '';

  try {

    ob_start();

    Error::exceptions(true);
    Config::init();

    $request = Util::cgiValue('request', '');
    $value   = Util::cgiValue('value', '');

    if ($value !== '') {
      switch ($request) {
      case 'TypeInfo':
        $types = self::fieldTypes($pme->dbh);
        $multi = $types[$value]['Multiplicity'] === 'multiple';
        \OC_JSON::success(
          array('data' => array(
                  'message' => L::t("Request \"%s\" successful", array($request)),
                  'TypeInfo' => $multi ? 'multiple' : 'single'
                  )));
        return true;
      case 'ValidateAmount':
        if (!isset($value['amount'])) {
          break;
        }
        $amount = $value['amount'];
        if (empty($amount)) {
          $amount = 0;
        } else {
          $parsed = FuzzyInput::currencyValue($amount);
          if ($parsed === false) {
            \OC_JSON::error(
              array("data" => array(
                      "message" => L::t('Could not parse number: "%s"', array($amount)),
                      'Amount' => false
                      )
                )
              );
            return false;
          }
          $amount = $parsed;
        }
        \OC_JSON::success(
          array('data' => array(
                  'message' => L::t("Request \"%s\" successful", array($request)),
                  'Amount' => $amount
                  )));
        return true;
      case 'AllowedValuesOption':
        // This is as well for changing as for adding new options.
        if (!isset($value['selected']) ||
            !isset($value['data']) ||
            !isset($value['keys'])) {
          break;
        }
        $selected = $value['selected'];
        $data  = $value['data'];
        $keys  = $value['keys'] ? $value['keys'] : [];
        $index = $data['index'];
        $used  = $data['used'] === 'used';

        $pfx = Config::$pmeopts['cgi']['prefix']['data'];
        $allowed = Util::cgiValue($pfx.'AllowedValues');
        $allowed = ProjectExtra::explodeAllowedValues(ProjectExtra::implodeAllowedValues($allowed), false);
        if (count($allowed) !== 1) {
          throw new \InvalidArgumentException(L::t('No or too many items available: ').print_r($allowed, true));
        }

        // Our data row
        $item = reset($allowed);

        // potentially tweak key to be unique (and simpler) if not already in use.
        if (!$used) {
          $item['key'] = ProjectExtra::allowedValuesUniqueKey($item['key'], $keys);
        }

        // remove dangerous html
        $item['tooltip'] = FuzzyInput::purifyHTML($item['tooltip']);

        switch ($data['Group']) {
        case 'surcharge':
          // see that it is a valid decimal number ...
          if (!empty($item['data'])) {
            $parsed = FuzzyInput::currencyValue($item['data']);
            if ($parsed === false) {
              \OC_JSON::error(
                array("data" => array(
                        "message" => L::t('Could not parse number: "%s"', array($item['data'])),
                        'AllowedValue' => false,
                        'AllowedValueInput' => false,
                        'AllowedValueOption' => false
                        )
                  )
                );
              return false;
            }
            $item['data'] = $parsed;
          }
          break;
        default:
          break;
        }

        $input = '';
        $options = array();
        if (!empty($item['key'])) {
          $key = $item['key'];
          $options[] = array('name' => $item['label'],
                             'value' => $key,
                             'flags' => ($selected === $key ? Navigation::SELECTED : 0));
          $input = ProjectExtra::allowedValueInputRow($item, $index, $used);
        }
        $options = Navigation::selectOptions($options);
        \OC_JSON::success(
          array('data' => array(
                  'message' => L::t("Request \"%s\" successful", array($request)),
                  'AllowedValue' => $allowed,
                  'AllowedValueInput' => $input,
                  'AllowedValueOption' => $options
                  )));
        return true;
      }
    }

    \OC_JSON::error(
      array("data" => array(
              "message" => L::t("Unhandled request:")." ".print_r($_POST, true),
              "debug" => htmlspecialchars($debugText))));

    return false;

  } catch (\Exception $e) {

    $debugText .= ob_get_contents();
    @ob_end_clean();

    $exceptionText = $e->getFile().'('.$e->getLine().'): '.$e->getMessage();
    $trace = $e->getTraceAsString();

    $admin = Config::adminContact();

    $mailto = $admin['email'].
      '?subject='.rawurlencode('[CAFEVDB-Exception] Exceptions from extra-fields form').
      '&body='.rawurlencode($exceptionText."\r\n".$trace);
    $mailto = '<span class="error email"><a href="mailto:'.$mailto.'">'.$admin['name'].'</a></span>';

    \OCP\JSON::error(
      array(
        'data' => array(
          'caption' => L::t('PHP Exception Caught'),
          'error' => 'exception',
          'exception' => $exceptionText,
          'trace' => $trace,
          'message' => L::t('Error, caught an exception. '.
                            'Please copy the displayed text and send it by email to %s.',
                            array($mailto)),
          'debug' => htmlspecialchars($debugText))));

    return false;
  }

} // namespace CAFEVDB

?>
