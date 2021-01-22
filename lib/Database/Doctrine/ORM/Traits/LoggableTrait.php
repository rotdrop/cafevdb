<?php

declare(strict_types=1);

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Traits;

use DateTime;

trait LoggableTrait
{
  public function getUpdateLogMessage(array $changeSets = []): string
  {
    $message = [];
    foreach ($changeSets as $property => $changeSet) {
      for ($i = 0, $s = sizeof($changeSet); $i < $s; $i++) {
        if ($changeSet[$i] instanceof DateTime) {
          $changeSet[$i] = $changeSet[$i]->format('Y-m-d H:i:s.u');
        }
      }

      if ($changeSet[0] === $changeSet[1]) {
        continue;
      }

      $message[] = $this->createChangeSetMessage($property, $changeSet);
    }

    return implode("\n", $message);
  }

  public function getCreateLogMessage(): string
  {
    return sprintf('%s #%s created', self::class, (string)$this->getId());
  }

  public function getRemoveLogMessage(): string
  {
    return sprintf('%s #%s removed', self::class, (string)$this->getId());
  }

  private function createChangeSetMessage(string $property, array $changeSet): string
  {
    return sprintf(
      '%s #%s : property "%s" changed from "%s" to "%s"',
      self::class,
      (string)$this->getId(),
      $property,
      ! is_array($changeSet[0]) ? $changeSet[0] : 'an array',
      ! is_array($changeSet[1]) ? $changeSet[1] : 'an array'
    );
  }
}
