<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2022, 2024 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Listeners;

use OCA\CAFEVDB\Wrapped\Gedmo\Loggable\Mapping\Event\LoggableAdapter;
use OCA\CAFEVDB\Wrapped\Gedmo\Loggable\LoggableListener as BaseLoggableListener;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;

use OCA\CAFEVDB\Wrapped\Doctrine\Persistence\ObjectManager;

/**
 * Override default for database query logging.
 *
 * @todo ATM all properties of all entities are logged with the
 * exception of the LogEntry-class, of course. Perhaps make this configurable.
 */
class GedmoLoggableListener extends BaseLoggableListener
{
  /**
   * @param null|string $userId string or null.
   *
   * @param null|string $remoteAddress string or null.
   */
  public function __construct(
    private ?string $userId = null,
    private ?string $remoteAddress = null,
  ) {
    parent::__construct();
  }

  /**
   * Set remote address for logging.
   *
   * @param null|strinng $remoteAddress
   *
   * @return void
   */
  public function setRemoteAddress($remoteAddress):void
  {
    $this->remoteAddress = $remoteAddress;
  }

  /** {@inheritdoc} */
  public function getConfiguration(ObjectManager $objectManager, $class)
  {
    $config = parent::getConfiguration($objectManager, $class);
    if (!isset($config['logEntryClass'])) {
      $config['logEntryClass'] = CAFEVDB\Entities\LogEntry::class;
    }
    // @todo Perhaps make this configurable
    if (!isset($config['loggable'])) {
      $config['loggable'] = $class != $config['logEntryClass'];
    }
    if (!empty($config['loggable'])) {
      // make all fields versioned if not configured for the class.
      if (!isset($config['versioned'])) {
        $classMetadata = $objectManager->getClassMetadata($class);
        $config['versioned'] = $classMetadata->getFieldNames();
      }
    }

    return $config;
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.UndefinedVariable)
   */
  protected function getLogEntryClass(LoggableAdapter $eventAdapter, $class)
  {
    return isset(self::$configurations[$this->name][$class]['logEntryClass']) ?
      self::$configurations[$this->name][$class]['logEntryClass'] :
      CAFEVDB\Entities\LogEntry::class;
  }

  /**
   * {@inheritdoc}
   */
  protected function prePersistLogEntry($logEntry, $object)
  {
    $logEntry->setRemoteAddress($this->remoteAddress);
  }
}
