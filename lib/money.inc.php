<?php

if ($value < 0) {
  return '<span style="color:#FF0000;">'.$value.'&euro;</span>';
} else {
  return $value.'&euro;';
}

?>