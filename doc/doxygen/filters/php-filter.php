#!/usr/bin/php
<?php
$source = file_get_contents($_SERVER['argv'][1]);
$tokens = token_get_all($source);

$buffer = null;
foreach ($tokens as $token) {
  if (is_string($token)) {
    if ((! empty($buffer)) && ($token == ';')) {
      echo $buffer;
      unset($buffer);
    }
    echo $token;
  } else {
    list($id, $text) = $token;
    switch ($id) {
    case T_DOC_COMMENT :
      $text = addcslashes($text, '\\');
      if (preg_match('#@var\s+[^\$]*$#ms', $text)) {
        $dataTypes = [ 'array<[^>]+>', '\S+', ];
        foreach ($dataTypes as $dataType) {
          // Single-line on multiple lines, @var being the last line of the comment
          $rex = '#(@var\s+'.$dataType.')([^\n\r]+)(\n\r?)(\s+\*)/#ms';
          $sub = '$1 \$\$\$ $3$4$2 */';
          if (preg_match($rex, $text)) {
            $buffer = preg_replace($rex, $sub, $text);
            break;
          }
          // Multi-line comment, place doc on the next line
          $rex = '#(@var\s+'.$dataType.')([^\n\r]*)(\n\r?)(\s+\*)#ms';
          $sub = '$1 \$\$\$ $3$4$2';
          if (preg_match($rex, $text)) {
            $buffer = preg_replace($rex, $sub, $text);
            break;
          }
          // Single line type-hint without doc '/** @var \Closure */'
          $rex = '#(@var\s+'.$dataType.')\s+(\*/)#ms';
          $sub = '$1 \$\$\$ $2';
          if (preg_match($rex, $text)) {
            $buffer = preg_replace($rex, $sub, $text);
            break;
          }
          // Single line comment '/** @var \Closure Foo and bar and so on */'
          $rex = '#(@var\s+'.$dataType.')\s+(\S+.*\S+)\s+(\*/)#ms';
          $sub = '$1 \$\$\$ $3'."\n".'/**< $2 */';
          if (preg_match($rex, $text)) {
            $tmp = preg_replace($rex, $sub, $text);
            list($buffer, $postDoc) = explode("\n", $tmp);
            break;
          }
        }
      } else {
        echo $text;
      }
      break;

    case T_VARIABLE :
      if (!empty($buffer)) {
        echo str_replace('$$$', $text, $buffer);
        unset($buffer);
      }
      if (!empty($postDoc)) {
        echo trim($text).' '.$postDoc."\n";
        unset($postDoc);
      } else {
        echo $text;
      }
      break;

    default:
      if ((! empty($buffer))) {
        $buffer .= $text;
      } else {
        echo $text;
      }
      break;
    }
  }
}
