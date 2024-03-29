<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2021, 2022, 2023 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Common;

use DateTimeImmutable;

use Psr\Log\LoggerInterface as ILogger;
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

  /** {@inheritdoc} */
  public function delete()
  {
  }

  /** {@inheritdoc} */
  public function bind(mixed $id = null)
  {
  }

  /** {@inheritdoc} */
  public function getId()
  {
    return -1;
  }

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
    return true;
  }

  /** {@inheritdoc} */
  public function sync()
  {
  }

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
  public function getData():?array
  {
    return $this->applicationData;
  }
}
