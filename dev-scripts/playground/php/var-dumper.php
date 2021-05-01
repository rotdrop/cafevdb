<?php

include_once __DIR__ . '/../../../vendor/autoload.php';

use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

class Test
{
  private $varCloner;
  private $varDumper;

  public function __construct()
  {
    $this->varCloner = new VarCloner;
    $this->varDumper = new CliDumper;
  }

  public function dump1($variable)
  {
    return $this->varDumper->dump($this->varCloner->cloneVar($variable), true);
  }

  public function dump2($variable)
  {
    $output = fopen('php://memory', 'r+b');

    $this->varDumper->dump($this->varCloner->cloneVar($variable), $output);
    return stream_get_contents($output, -1, 0);
  }

  public function dump3($variable)
  {
    $output = '';

    $this->varDumper->dump(
      $this->varCloner->cloneVar($variable),
      function ($line, $depth) use (&$output) {
        // A negative depth means "end of dump"
        if ($depth >= 0) {
          // Adds a two spaces indentation to the line
          $output .= str_repeat('  ', $depth).$line."\n";
        }
      }
    );
    return $output;
  }

}

$test = new Test;

dump($test);
echo '****************************************'.PHP_EOL;
echo $test->dump1($test);
echo '****************************************'.PHP_EOL;
echo $test->dump2($test);
echo '****************************************'.PHP_EOL;
echo $test->dump3($test);
