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

use Doctrine\Common\EventArgs;

/**
 * Override the default listener to use a modified event subscriber
 * which also queries other sources of translations if a concrete
 * translations has not been persisted yet.
 */
class GedmoTranslatableListener extends \Gedmo\Translatable\TranslatableListener
{
  /** @var Translatable\EventDecorator */
  private $adapter = null;

  /**
   * {@inheritdoc}
   */
  protected function getEventAdapter(EventArgs $args)
  {
    if (empty($adapter)) {
      $adapter = new Translatable\EventDecorator(parent::getEventAdapter($args));
    }
    $adapter->setEventArgs($args);
    return $adapter;
  }

  // /**
  //  * {@inheritdoc}
  //  */
  // protected function getNamespace()
  // {
  //   return __NAMESPACE__;
  // }
}
