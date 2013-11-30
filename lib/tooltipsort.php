<?php

namespace CAFEVDB {

class L 
{
  public static function t($text) {
    return "L::t('".str_replace("'","\'",$text)."')";
  }
  
};

require_once('tooltips.php');

$tips = ToolTips::toolTips();

ksort($tips);

echo "array(\n";
foreach($tips as $key => $value) {
  $strValue = '';
  if (is_array($value)) {
    $strValue = "array(\n";
    foreach ($value as $subKey => $subValue) {
      $strValue .= "    '$subKey' => $subValue,\n";
    }
    $strValue .= "  ),\n";
  } else {
    $strValue = $value.",\n";
  }
  echo "  '$key' => $strValue\n";
}
echo ")";

}

?>