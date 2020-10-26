<?php

namespace OCA\CAFEVDB\Database\Cloud\Entities;

use OCP\AppFramework\Db\Entity;

use OCP\ILogger;

class ProgressStatus extends Entity
{
  public $id;
  protected $userId;
  protected $current;
  protected $target;
  protected $lastModified;

  public function __construct() {
    // $this->addType('id', 'int'); this is default
    $this->addType('userId', 'string');
    $this->addType('current', 'int');
    $this->addType('target', 'int');
    $this->addType('lastModified', 'int');
  }


  public function __call($methodName, $args) {
    if (strpos($methodName, 'set') === 0) {
      $this->lastModified = time();
      $this->markFieldUpdated('lastModified');
    }
    return parent::__call($methodName, $args);
  }
}
