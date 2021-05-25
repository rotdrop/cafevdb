<?php

namespace OCA\CAFEVDB\Common;

use OCP\ILogger;
use OCP\IL10N;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;

use OCA\CAFEVDB\Storage\AppStorage;
use OCA\CAFEVDB\Common\IProgressStatus;

class PlainFileProgressStatus implements IProgressStatus
{
  private const READ_RETRY_LIMIT = 10;

  use \OCA\CAFEVDB\Traits\LoggerTrait;

  const DATA_DIR = 'progress-status';

  /** @var AppStorage */
  protected $storage;

  /** @var ISimpleFile */
  protected $file;

  /** @var array */
  protected $data;

  /**
   * Find the given or create a new progress-status file.
   */
  public function __construct(
    $appName
    , ILogger $logger
    , IL10N $l10n
    , AppStorage $storage
  ) {

    $this->logger = $logger;
    $this->l = $l10n;
    $this->storage = $storage;
    $this->file = null;
  }

  protected function reset()
  {
    $this->data = [
      'current' => 0,
      'target' => 0,
      'data' => [],
      'lastModified' => time(),
    ];
  }

  protected function flush()
  {
    $this->data['lastModified'] = time();
    $this->file->putContent(json_encode($this->data));
  }

  public function bind($id = null)
  {
    if (!empty($this->file) && $this->file->getName() == $id) {
      $this->sync();
      return;
    }

    if (!empty($file)) {
      try {
        $this->file->delete();
      } catch (\Throwable $t) {
        $this->logException($t);
      }
    }
    $this->file = null;

    if (!empty($id)) {
      try {
        $this->file = $this->storage->getFile(self::DATA_DIR, $id);
        $this->sync();
      } catch (\Throwable $t) {
        $this->logException($t);
        $this->reset();
      }
    }
    if (empty($this->file)) {
      $this->file = $this->storage->newTemporaryFile(self::DATA_DIR);
      $this->reset();
      $this->file->putContent(json_encode($this->data));
    }
  }

  public function getId()
  {
    return $this->file->getName();
  }

  public function update(int $current, ?int $target = null, ?array $data = null)
  {
    $this->data['current'] = $current;
    if (!empty($target)) {
      $this->data['target'] = $target;
    }
    if (!empty($data)) {
      $this->data['data'] = $data;
    }
    $this->flush();
  }

  public function sync()
  {
    for ($i = 0; $i < self::READ_RETRY_LIMIT; $i++) {
      $fileContent = $this->file->getContent();
      $fileData = json_decode($fileContent, true);
      if (is_array($fileData)) {
        $this->data = $fileData;
        return;
      }
    }
    throw new \RuntimeException($this->l->t('Unable to read progress status file "%s" after $d retries.', $this->file->getName(), $i));
  }

  public function getCurrent():int
  {
    return $this->data['current'];
  }

  public function getTarget():int
  {
    return $this->data['target'];
  }

  public function getLastModified():\DateTimeinterface
  {
    return (new \DateTimeImmutable)->setTimestamp($this->data['lastModified']);
  }

  public function getData():?array
  {
    return $this->data['data'];
  }

}
