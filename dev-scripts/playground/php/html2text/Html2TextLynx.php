<?php
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ExecutableFinder;

class Html2TextLynx
{

  static function convert($html)
  {
    $lynx = (new ExecutableFinder)->find('lynx');
    if (empty($lynx)) {
      throw new Exceptions\EnduserNotificationException($this->l->t('Please install the "lynx" program on the server.'));
    }
    $htmlConvert = new Process([
      $lynx,
      '-force_html',
      '-noreferer',
      '-nomargins',
      '-dont_wrap_pre',
      '-nolist',
      '-display_charset=utf-8',
      '-width=80',
      '-dump',
      '-stdin',
    ]);
    $htmlConvert->setInput($html);
    $htmlConvert->run();
    $text = $htmlConvert->getOutput();

    echo 'FINISH ' . $test . PHP_EOL;

    return $text;
  }
}
