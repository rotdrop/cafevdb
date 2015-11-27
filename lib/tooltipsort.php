<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2015 Claus-Justus Heine <himself@claus-justus-heine.de>
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

  // Header part
  $header = "<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2015 Claus-Justus Heine <himself@claus-justus-heine.de>
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

  /** Tool-tips management with translations. */
  class ToolTips
  {
    static private \$toolTipsData = '';
    static function toolTips()
    {
      if (self::\$toolTipsData == '') {
        self::\$toolTipsData = self::makeToolTips();
      }
      return self::\$toolTipsData;
    }
    static private function makeToolTips()
    {
      return array(
";

  // Footer part
  $footer = "
        );

    }
  }; // class toolTips

} // namespace

/*
 * Local Variables: ***
 * c-basic-offset: 2 ***
 * End: ***
 */
?>
";

  // Basic indent
  $indent = "        ";
  $subIndent = "  ";

  class L
  {
    public static function t($text) {
      return "L::t('".str_replace("'","\'",$text)."')";
    }

  };

  function arrayRecursiveDiff($aArray1, $aArray2) {
    $aReturn = array();

    foreach ($aArray1 as $mKey => $mValue) {
      if (array_key_exists($mKey, $aArray2)) {
        if (is_array($mValue)) {
          $aRecursiveDiff = arrayRecursiveDiff($mValue, $aArray2[$mKey]);
          if (count($aRecursiveDiff)) { $aReturn[$mKey] = $aRecursiveDiff; }
        } else {
          if ($mValue != $aArray2[$mKey]) {
            $aReturn[$mKey] = $mValue;
          }
        }
      } else {
        $aReturn[$mKey] = $mValue;
      }
    }
    return $aReturn;
  }

  require_once(__DIR__.'/tooltips.php');

  $tipTags = array();

  $tagFile = __DIR__.'/tooltips.txt';
  if(file_exists($tagFile)) {
    $tipTags = preg_split('/\s+/', trim(file_get_contents($tagFile)));
  }

  $emptyTips = array();
  foreach($tipTags as $tipTag) {
    $emptyTips[$tipTag] = '';
  }

  $tips = ToolTips::toolTips();

  $newTips = array_merge($emptyTips, $tips);
  ksort($tips);
  ksort($newTips);

  $output = $header;
  foreach($newTips as $key => $value) {
    $strValue = '';
    if (is_array($value)) {
      $strValue = "array(\n";
      foreach ($value as $subKey => $subValue) {
        $strValue .= $indent.$subIndent."'$subKey' => $subValue,\n";
      }
      $strValue .= $indent.$subIndent."),\n";
    } else {
      if ($value == '') {
        $value = "''";
      }
      $strValue = $value.",\n";
    }
    $output .= $indent."'$key' => $strValue\n";
  }
  $output .= $footer;

  file_put_contents(__DIR__.'/tooltips.php.new', $output);

  echo "\n*** New Tips: ***\n";
  print_r(arrayRecursiveDiff($newTips, $tips));

  echo "\n*** Obsolete Tips: ***\n";
  print_r(arrayRecursiveDiff($tips, $newTips));

  echo "\n*** Empty Tips: ***\n";
  foreach ($newTips as $key => $value) {
    if ($value === '' || !$value) {
      echo $key.' ';
    }
  }
  echo "\n";
}

?>