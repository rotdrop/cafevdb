<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Maintenance\Sanitizers;

use OCP\ILogger;
use OCP\IL10N;

use Symfony\Component\Console\Output\Output;

use OCA\CAFEVDB\Maintenance\ISanitizer;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Exceptions;

/**
 * Add part of shared contents to the interface.
 *
 * @SuppressWarnings(PHPMD.ShortMethodName)
 */
abstract class AbstractSanitizer implements ISanitizer
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  /** @var object */
  protected $entity;

  /** @var array */
  protected $messages = [];

  /**
   * @param EntityManager $entityManager The entity-manger for the entire mess.
   *
   * @param ILogger $logger A cloud logger instance.
   */
  public function __construct(
    EntityManager $entityManager,
    ILogger $logger = null,
  ) {
    $this->entityManager = $entityManager;
    $this->logger = $logger;
    $this->entity = null;
  }

  /** {@inheritdoc} */
  public function setEntity(mixed $entity):void
  {
    $this->entity = $entity;
  }

  /** {@inheritdoc} */
  public function getEntity(mixed $entity):mixed
  {
    return $this->entity;
  }

  /** {@inheritdoc} */
  public static function getName():string
  {
    return self::NAME;
  }

  /** {@inheritdoc} */
  public function getValidationMessages():array
  {
    return $this->messages;
  }

  /**
   * @param string $message The message to remember.
   *
   * @param int $level One of the verbosity levels.
   *
   * @return void
   */
  protected function addMessage(string $message, int $level = self::VERBOSITY_NORMAL):void
  {
    if ($this->logger) {
      switch ($level) {
        case self::VERBOSITY_QUIET:
          break;
        case self::VERBOSITY_VERY_VERBOSE:
          $this->logDebug($message, shift: 1);
          break;
        case self::VERBOSITY_VERBOSE:
          $this->logInfo($message, shift: 1);
          break;
        case self::VERBOSITY_NORMAL:
          $this->logInfo($message, shift: 1);
          break;
        case self::VERBOSITY_DEBUG:
          $this->logDebug($message);
          break;
      }
    }
    $this->messages[$level][] = $message;
  }

  /** @return void */
  protected function resetMessages():void
  {
    $this->messages = [];
  }

  /**
   * {@inheritdoc}
   *
   * Default implementation which reports validation failure such that the
   * calling code always executes the sanitizer.
   */
  public function validate():bool
  {
    return false;
  }

  /** {@inheritdoc} */
  public function sanitizeRemove(bool $flush = false):void
  {
    $lifeCycle = self::SANITIZE_REMOVE;
    throw new Exceptions\SanitizerNotImplementedException(
      $this->l->t('Sanitizing of life-cycle stage "%s" is not implemented.', $lifeCycle)
    );
  }

  /** {@inheritdoc} */
  public function sanitizePersist(bool $flush = false):void
  {
    $lifeCycle = self::SANITIZE_PERSIST;
    throw new Exceptions\SanitizerNotImplementedException(
      $this->l->t('Sanitizing of life-cycle stage "%s" is not implemented.', $lifeCycle)
    );
  }

  /** {@inheritdoc} */
  public function sanitizeUpdate(bool $flush = false):void
  {
    try {
      $this->sanitizePersist($flush);
    } catch (Exceptions\SanitizerNotImplementedException $e) {
      throw new Exceptions\SanitizerNotImplementedException(
        $this->l->t('Sanitizing of life-cycle stage "%s" is not implemented.', self::SANITIZE_UPDATE),
        0,
        $e
      );
    }
  }

  /** {@inheritdoc} */
  public function sanitize(string $lifeCycle, bool $flush = false):void
  {
    switch ($lifeCycle) {
      case self::SANITIZE_UPDATE:
      case self::SANITIZE_PERSIST:
      case self::SANITIZE_REMOVE:
        $method = 'sanitize' . ucfirst($lifeCycle);
        $this->$method($flush);
        break;
      default:
        throw new Exceptions\SanitizerNotImplementedException(
          $this->l->t('Sanitizing of life-cycle stage "%s" is not implemented.', $lifeCycle)
        );
    }
  }
}
