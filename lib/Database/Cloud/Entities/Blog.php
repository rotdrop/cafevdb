<?php

namespace OCA\CAFEVDB\Database\Cloud\Entities;

use OCP\AppFramework\Db\Entity;

class Blog extends Entity
{
  public $id;
  protected $author;
  protected $created;
  protected $editor;
  protected $modified;
  protected $message;
  protected $inReplyTo;
  protected $deleted;
  protected $priority;
  protected $popup;
  protected $reader;
}
