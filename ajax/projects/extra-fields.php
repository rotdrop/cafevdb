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

  try {

    ob_start();

    Error::exceptions(true);
    Config::init();

    $request = Util::cgiValue('request', '');
    $value   = Util::cgiValue('value', '');

    if (!empty($value)) {
    switch ($request) {
    case 'TypeInfo':
      $multi = ProjectExtra::multiValueField($value);
      \OC_JSON::success(
        array('data' => array(
                'message' => L::t("Request \"%s\" successful", array($request)),
                'TypeInfo' => $multi ? 'multiple' : 'single'
                )));
      return true;
    case 'AllowedValuesOptions':
      if (!isset($value['values']) || !isset($value['selected'])) {
        break;
      }
      $values = ProjectExtra::explodeAllowedValues($value['values']);
      $valueString = implode("\n", $values);
      $options = Navigation::simpleSelectOptions($values, $value['selected']);
      \OC_JSON::success(
        array('data' => array(
                'message' => L::t("Request \"%s\" successful", array($request)),
                'AllowedValues' => $valueString,
                'AllowedValuesOptions' => $options
                )));
      return true;
    }
    }

    \OC_JSON::error(
      array("data" => array(
              "message" => L::t("Unhandled request:")." ".print_r($_POST, true))));

    return false;

  } catch (\Exception $e) {

    $debugText .= ob_get_contents();
    @ob_end_clean();

    $exceptionText = $e->getFile().'('.$e->getLine().'): '.$e->getMessage();
    $trace = $e->getTraceAsString();

    $admin = Config::adminContact();

    $mailto = $admin['email'].
      '?subject='.rawurlencode('[CAFEVDB-Exception] Exceptions from Email-Form').
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
