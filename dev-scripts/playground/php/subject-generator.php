<?php


class Test
{
  const SUBJECT_OPTION_SEPARATOR = ': ';
  const SUBJECT_GROUP_SEPARATOR = '; ';
  const SUBJECT_ITEM_SEPARATOR = ', ';

  public static function generateCompositeSubjet(array $subjects)
  {
    natsort($subjects);
    $oldPrefix = false;
    $postfix = [];
    $purpose = '';
    foreach ($subjects as $subject) {
      $parts = explode(self::SUBJECT_OPTION_SEPARATOR, $subject);
      print_r($parts);
      $prefix = $parts[0];
      if (count($parts) < 2 || $oldPrefix != $prefix) {
        $purpose .= implode(self::SUBJECT_ITEM_SEPARATOR, $postfix);
        if (strlen($purpose) > 0) {
          $purpose .= self::SUBJECT_GROUP_SEPARATOR;
        }
        $purpose .= $prefix;
        if (count($parts) >= 2) {
          $purpose .= self::SUBJECT_OPTION_SEPARATOR;
          $oldPrefix = $prefix;
        } else {
          $oldPrefix = false;
        }
        $postfix = [];
      }
      if (count($parts) >= 2) {
        $postfix = array_merge($postfix, array_splice($parts, 1));
      }
    }
    if (!empty($postfix)) {
      $purpose .= implode(self::SUBJECT_ITEM_SEPARATOR, $postfix);
    }

    return $purpose;
  }
}

$subject1 = 'Mehrfach Geld: Langer Name Name; Mehrfach Geld: Option1; Mehrfach Geld: Noch was; TestRegelmäßig: 00 Option 1; TestRegelmäßig: Option 2';
$subject2 = 'Unkostenbeitrag; TestRegelmäßig: 00 Option 1; TestRegelmäßig: Option 2';

echo Test::generateCompositeSubjet(explode(Test::SUBJECT_GROUP_SEPARATOR, $subject1)).PHP_EOL;
