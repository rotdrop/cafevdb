<?php

namespace OCA\CAFEVDB\Database\Cloud\Entities;

use OCP\AppFramework\Db\Entity;

class ProgressStatus extends Entity
{
  protected $id;
  protected $tag;
  protected $user;
  protected $current;
  protected $target;
}
