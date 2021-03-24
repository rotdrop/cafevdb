<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library se Doctrine\ORM\Tools\Setup;is free software; you can redistribute it and/or
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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Listeners;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;

use Doctrine\Persistence\ObjectManager;

/**
 * Override default for database query logging.
 *
 * @todo ATM all properties of all entities are logged with the
 * exception of the LogEntry-class, of course. Perhaps make this configurable.
 */
class GedmoLoggableListener extends \Gedmo\Loggable\LoggableListener
{
  /** @var string */
  private $remoteAddress;

  /**
   * @param mixed $userId string or null
   *
   * @param mixed $remoteAddress string or null
   */
  public function __construct($userId = null, $remoteAddress = null)
  {
    parent::__construct();
    $this->username = $userId;
    $this->remoteAddress = $remoteAddress;
  }

  /**
   * Set remote address for logging.
   *
   * @param mixed string or null
   */
  public function setRemoteAddress($remoteAddress)
  {
    $this->remoteAddress = $remoteAddress;
  }

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

  protected function getLogEntryClass(\Gedmo\Loggable\Mapping\Event\LoggableAdapter $ea, $class)
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
