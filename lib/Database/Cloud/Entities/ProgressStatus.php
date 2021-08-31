<?php

namespace OCA\CAFEVDB\Database\Cloud\Entities;

use Doctrine\DBAL\Types\Types;

use OCP\AppFramework\Db\Entity;

use OCP\ILogger;

class ProgressStatus extends Entity
{
  public $id;
  protected $userId;
  protected $current;
  protected $target;
  protected $data;
  protected $lastModified;

  public function __construct() {
    // $this->addType('id', 'int'); this is default
    $this->addType('current', 'int');
    $this->addType('target', 'int');
    $this->addType('data', 'string');
    $this->addType('lastModified', 'int');
    $this->lastModified = time();
    $this->markFieldUpdated('lastModified');
  }

  public function __call($methodName, $args) {
    if (strpos($methodName, 'set') === 0) {
      $this->lastModified = time();
      $this->markFieldUpdated('lastModified');
    }
    return parent::__call($methodName, $args);
  }
}
