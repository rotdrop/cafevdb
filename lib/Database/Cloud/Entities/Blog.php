<?php

namespace OCA\CAFEVDB\Database\Cloud\Entities;

use Doctrine\DBAL\Types\Types;

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
    // $this->addType('id', Types::BIGINT); this is default
    $this->addType('author', Types::STRING);
    $this->addType('created', Types::BIGINT);
    $this->addType('editor', Types::STRING);
    $this->addType('modified', Types::BIGINT);
    $this->addType('message', Types::STRING);
    $this->addType('inReplyTo', Types::BIGINT);
    $this->addType('deleted', Types::BOOLEAN);
    $this->addType('priority', Types::BIGINT);
    $this->addType('popup', Types::BOOLEAN);
    $this->addType('reader', Types::STRING);
  }

}
