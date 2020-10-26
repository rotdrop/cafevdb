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

  public function __construct() {
    // $this->addType('id', 'int'); this is default
    $this->addType('author', 'string');
    $this->addType('created', 'int');
    $this->addType('editor', 'string');
    $this->addType('modified', 'int');
    $this->addType('message', 'string');
    $this->addType('inReplyTo', 'int');
    $this->addType('deleted', 'bool');
    $this->addType('priority', 'int');
    $this->addType('popup', 'bool');
    $this->addType('reader', 'string');
  }

}
