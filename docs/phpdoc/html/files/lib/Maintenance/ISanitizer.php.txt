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

namespace OCA\CAFEVDB\Maintenance;

use Symfony\Component\Console\Output\OutputInterface;

use OCA\CAFEVDB\Exceptions;

/**
 * "Sanitizers" are snippets of code which perform a task on a given single
 * entity (although this may mean to loop through asssociation
 * collections). One example is to add and remove known email-aliases
 * (e.g. some@gmail.com is also reachable via some@googlemail.com, and this
 * matters if it comes to mailing list membership).
 */
interface ISanitizer
{
  public const VERBOSITY_QUIET = OutputInterface::VERBOSITY_QUIET;
  public const VERBOSITY_NORMAL = OutputInterface::VERBOSITY_NORMAL;
  public const VERBOSITY_VERBOSE = OutputInterface::VERBOSITY_VERBOSE;
  public const VERBOSITY_VERY_VERBOSE = OutputInterface::VERBOSITY_VERY_VERBOSE;
  public const VERBOSITY_DEBUG = OutputInterface::VERBOSITY_DEBUG;

  /** @var string Tag supported on-persist sanitization. */
  const SANITIZE_PERSIST = 'persist';

  /** @var string Tag supported on-update sanitization. */
  const SANITIZE_UPDATE = 'update';

  /** @var string Tag supported on-remove sanitization. */
  const SANITIZE_REMOVE = 'remove';

  /** @return string Short description of the sanitizer. */
  public static function getDescription():string;

  /** @return string Name of the sanitizer. */
  public static function getName():string;

  /** @return array Validation messages, indexed by verbosity level. */
  public function getValidationMessages():array;

  /**
   * @param mixed $entity Set the entity to be validated.
   *
   * @return void
   */
  public function setEntity(mixed $entity):void;

  /**
   * @param mixed $entity
   *
   * @return mixed Get the entity to be validated.
   */
  public function getEntity(mixed $entity):mixed;

  /**
   * @return bool Return \true if the entity is ok to the judgement of this
   * sanitizer, \false otherwise. Of course, the entity may fail the
   * validations carried out by other sanitizers ...
   */
  public function validate():bool;

  /**
   * @param string $lifeCycle Either of SANITIZE_PERSIST, SANITIZE_UPDATE or
   * SANITIZE_REMOVE. The sanitizer implementation need not implement all
   * sanitizers.
   *
   * @param bool $flush Whether to call flush after sanitizing.
   *
   * @return void
   *
   * @throws Exceptions\SanitizerException
   */
  public function sanitize(string $lifeCycle, bool $flush = false):void;

  /**
   * Call sanitize(SANITIZE_PERSIST, $flush).
   *
   * @param bool $flush Whether to call flush after sanitizing.
   *
   * @return void
   *
   * @throws Exceptions\SanitizerException
   */
  public function sanitizePersist(bool $flush = false):void;

  /**
   * Call sanitize(SANITIZE_UPDATE, $flush).
   *
   * @param bool $flush Whether to call flush after sanitizing.
   *
   * @return void
   *
   * @throws Exceptions\SanitizerException
   */
  public function sanitizeUpdate(bool $flush = false):void;

  /**
   * Call sanitize(SANITIZE_REMOVE, $flush).
   *
   * @param bool $flush Whether to call flush after sanitizing.
   *
   * @return void
   *
   * @throws Exceptions\SanitizerException
   */
  public function sanitizeRemove(bool $flush = false):void;

  /**
   * @return string The full class name of the entity the sanitizer is
   * dedicated to.
   */
  public static function getEntityClass():string;
}
