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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\SoftDeleteable;

use Gedmo\Mapping\Event\AdapterInterface;
use Gedmo\SoftDeleteable\HardDeleteable\HardDeleteableInterface;

  /**
 * Allow hard-deletion of unused objects.
 */
class HardDeleteUnused implements HardDeleteableInterface
{
  /** @var AdapterInterface */
  protected $eventAdapter;

  /** {@inheritdoc} */
  public function __construct(AdapterInterface $ea)
  {
    $this->eventAdapter = $ea;
  }

  /** {@inheritdoc} */
  public function hardDeleteAllowed($object, $config)
  {
    if (method_exists($object, 'unused')) {
      return $object->unused();
    }
  }
}
