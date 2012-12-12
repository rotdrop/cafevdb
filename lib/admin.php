<?php

namespace CAFEVDB;

class Admin
{
  // 
  public static function fillInstrumentationNumbers()
  {
    Config::init();

    // Fetch the actual list of instruments, we will need it anyway
    $handle = mySQL::connect(Config::$pmeopts);

    $instruments = mySQL::multiKeys('Musiker', 'Instrumente', $handle);

    // Current mysql versions do not seem to support "IF NOT EXISTS", so
    // we simply try to do our best and add one column in each request.

    foreach ($instruments as $instr) {
      $query = 'ALTER TABLE `BesetzungsZahl` ADD COLUMN `'.$instr."` TINYINT NOT NULL DEFAULT '0'";
      $result = mySQL::query($query, $handle); // simply ignore any error
    }
    mySQL::close($handle);
  }

  public static function checkInstrumentsTable()
  {
    Config::init();

    $handle = mySQL::connect(Config::$pmeopts);

    Instruments::check($handle);

    mySQL::close($handle);
  }

  public static function sanitizeInstrumentsTable()
  {
    Config::init();

    $handle = mySQL::connect(Config::$pmeopts);

    Instruments::sanitizeTable($handle, false);

    mySQL::close($handle);
  }

  public static function recreateAllViews()
  {
    Config::init();

    $handle = mySQL::connect(Config::$pmeopts);

    // Fetch the list of projects
    $query = 'SELECT `Id` FROM `Projekte` WHERE 1';
    $result = mySQL::query($query, $handle);

    while ($line = mySQL::fetch($result)) {
      $projectId = $line['Id'];

      print '<H4>Recreating view for project '.$projectId.'</H4><BR/>';

      // Just diagnostic
      //Util::error("Before Create ".$projectId, false);

      Projects::createView($projectId, false, $handle);

      // Just diagnostic
      //Util::error("After Create ".$projectId, false);
    }

    mySQL::close($handle);

    print '<H4>Success</H4><BR/>';    

  }

};

?>