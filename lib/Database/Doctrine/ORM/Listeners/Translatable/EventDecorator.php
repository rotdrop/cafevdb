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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\Translatable;

use Doctrine\Common\EventArgs;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Mapping\Event\TranslatableAdapter as AdapterInterface;
use Gedmo\Tool\Wrapper\AbstractWrapper;
use Gedmo\Mapping\Event\Adapter\ORM as BaseAdapterORM;

/**
 * Override the default listener to use a modified event subscriber
 * which also queries other sources of translations if a concrete
 * translations has not been persisted yet.
 */
class EventDecorator extends BaseAdapterORM implements AdapterInterface
{
  /** @var AdapterInterface */
  private $adapter;

  public function __construct(AdapterInterface $adapter)
  {
    $this->adapter = $adapter;
  }

  /**
   * {@inheritdoc}
   */
  public function setEventArgs(EventArgs $args)
  {
    parent::setEventArgs($args);
    $this->adapter->setEventArgs($args);
  }

  /**
   * Set the entity manager
   */
  public function setEntityManager(EntityManagerInterface $em)
  {
    parent::setEntityManager($em);
    $this->adapter->setEntityManager($em);
  }

  /**
   * {@inheritdoc}
   */
  public function usesPersonalTranslation($translationClassName)
  {
    $this->adapter->usesPersonalTranslation($translationClassName);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultTranslationClass()
  {
    return $this->adapter->getDefaultTranslationClass();
  }

  /**
   * {@inheritdoc}
   */
  public function loadTranslations($object, $translationClass, $locale, $objectClass)
  {
    return $this->adapter->loadTranslations($object, $translationClass, $locale, $objectClass);
  }

  /**
   * {@inheritdoc}
   */
  public function findTranslation(AbstractWrapper $wrapped, $locale, $field, $translationClass, $objectClass)
  {
    return $this->adapter->findTranslation($wrapped, $locale, $field, $translationClass, $objectClass);
  }

  /**
   * {@inheritdoc}
   */
  public function removeAssociatedTranslations(AbstractWrapper $wrapped, $transClass, $objectClass)
  {
    return $this->adapter->removeAssociatedTranslations($wrapped, $transClass, $objectClass);
  }

  /**
   * {@inheritdoc}
   */
  public function insertTranslationRecord($translation)
  {
    $this->adapter->insertTranslationRecord($translation);
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslationValue($object, $field, $value = false)
  {
    return $this->adapter->getTranslationValue($object, $field, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function setTranslationValue($object, $field, $value)
  {
    $this->adapter->setTranslationValue($object, $field, $value);
  }
}
