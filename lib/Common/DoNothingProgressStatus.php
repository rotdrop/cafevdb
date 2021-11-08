<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Common;

use OCP\ILogger;
use OCP\IL10N;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;

use OCA\CAFEVDB\Storage\AppStorage;
use OCA\CAFEVDB\Common\IProgressStatus;
use OCA\CAFEVDB\Exceptions;

/** Dummy implementation doing nothing. */
class DoNothingProgressStatus extends AbstractProgressStatus
{
  /** @var int */
  protected $target = -1;

  /** @var int */
  protected $current = -1;

  /** @var mixed */
  protected $applicationData = null;

  /** @var \DateTimeImmutable */
  protected $lastModified;

  public function __construct()
  {
    $this->lastModified = new \DateTimeImmutable;
  }

  /** {@inheritdoc} */
  public function delete() {}

  /** {@inheritdoc} */
  public function bind($id = null) {}

  /** {@inheritdoc} */
  public function getId() { return -1; }

  /** {@inheritdoc} */
  public function update(int $current, ?int $target = null, ?array $data = null):bool
  {
    $this->current = $current;
    if ($target !== null) {
      $this->target = $target;
    }
    if ($data !== null) {
      $this->applicationData = $data;
    }
    $this->lastModified = new \DateTimeImmutable;
    return true;
  }

  /** {@inheritdoc} */
  public function sync() {}

  /** {@inheritdoc} */
  public function getCurrent():int
  {
    return $this->current;
  }

  /** {@inheritdoc} */
  public function getTarget():int
  {
    return $this->target;
  }

  /** {@inheritdoc} */
  public function getLastModified():\DateTimeinterface
  {
    return $this->lastModified;
  }

  /** {@inheritdoc} */
  public function getData():?array
  {
    return $this->applicationData;
  }

}
