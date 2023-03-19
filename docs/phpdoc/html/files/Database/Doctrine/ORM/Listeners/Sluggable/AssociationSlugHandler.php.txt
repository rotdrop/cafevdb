<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\Sluggable;

use OCA\CAFEVDB\Wrapped\Doctrine\Persistence\Mapping\ClassMetadata;
use OCA\CAFEVDB\Wrapped\Doctrine\Persistence\ObjectManager;
use OCA\CAFEVDB\Wrapped\Gedmo\Exception\InvalidMappingException;
use OCA\CAFEVDB\Wrapped\Gedmo\Sluggable\Mapping\Event\SluggableAdapter;
use OCA\CAFEVDB\Wrapped\Gedmo\Sluggable\SluggableListener;
use OCA\CAFEVDB\Wrapped\Gedmo\Tool\Wrapper\AbstractWrapper;
use OCA\CAFEVDB\Wrapped\Gedmo\Sluggable\Handler\SlugHandlerInterface;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping\ClassMetadata as ORMMetaData;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\UnitOfWork;

/**
 * Simple slug-handler which updates a field of an associated
 * entity. Inspired by:
 *
 * Sluggable handler which should be used for inversed relation mapping
 * used together with RelativeSlugHandler. Updates back related slug on
 * relation changes
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
class AssociationSlugHandler implements SlugHandlerInterface
{
  /**
   * @var SluggableListener
   */
  protected $sluggable;

  /**
   * {@inheritdoc}
   *
   * $options = array(
   *     'relationClass' => 'objectclass',
   *     'inverseSlugField' => 'slug',
   *     'mappedBy' => 'relationField'
   * )
   */
  public function __construct(SluggableListener $sluggable)
  {
    $this->sluggable = $sluggable;
  }

  /** {@inheritdoc} */
  public function onChangeDecision(SluggableAdapter $eventAdapter, array &$config, $object, &$slug, &$needToChangeSlug, $otherSlugs)
  {
  }

  /** {@inheritdoc} */
  public function postSlugBuild(SluggableAdapter $eventAdapter, array &$config, $object, &$slug)
  {
  }

  /** {@inheritdoc} */
  public static function validate(array $options, ClassMetadata $meta)
  {
    if (!isset($options['associationSlug']) || !strlen($options['associationSlug'])) {
      throw new InvalidMappingException("'associationSlug' option must be specified for object slug mapping - {$meta->name}");
    }
  }

  /** {@inheritdoc} */
  public function onSlugCompletion(SluggableAdapter $eventAdapter, array &$config, $object, &$slug)
  {
    $objectManager = $eventAdapter->getObjectManager();
    /** @var ORMMetadata $meta */
    $meta = $objectManager->getClassMetadata(get_class($object));

    $options = $config['handlers'][get_called_class()];
    // $wrapped = AbstractWrapper::wrap($object, $objectManager);
    // $oldSlug = $wrapped->getPropertyValue($config['slug']);

    list($associationField, $associationSlugField) = explode('.', $options['associationSlug']);

    // we must be on a to-one side, otherwise distributing the slug
    // makes barely any sense
    //
    // We allow this as we use a OneToMany - ManyToOne "trick" to implement
    // lazy OneToOne associations from the inverse side.

    // if (!isset($meta->associationMappings[$associationField])
    //     || ($meta->associationMappings[$associationField]['type'] == ORMMetaData::MANY_TO_MANY)
    //     || ($meta->associationMappings[$associationField]['type'] == ORMMetaData::MANY_TO_ONE)) {
    //   throw new InvalidMappingException('We are on the to-many side of '.$associationField);
    // }

    $targetEntity = $meta->associationMappings[$associationField]['targetEntity'];
    /** @var ORMMetadata $targetMeta */
    $targetMeta = $objectManager->getClassMetadata($targetEntity);

    $uow = $objectManager->getUnitOfWork();
    $association = $meta->getReflectionProperty($associationField)->getValue($object);
    if ($meta->isCollectionValuedAssociation($associationField)) {
      $collection = $association;
    } else {
      $collection = [ $association ];
    }
    foreach ($collection as $targetObject) {
      if (empty($targetObject)) {
        continue;
      }
      $oldTargetSlug = $targetMeta->getReflectionProperty($associationSlugField)->getValue($targetObject);
      $targetMeta->getReflectionProperty($associationSlugField)->setValue($targetObject, $slug);
      $targetState = $uow->getEntityState($targetObject);
      if ($targetState == UnitOfWork::STATE_MANAGED || $targetState == UnitOfWork::STATE_NEW) {
        $eventAdapter->setOriginalObjectProperty($uow, $targetObject, $associationSlugField, $oldTargetSlug);
        $eventAdapter->recomputeSingleObjectChangeSet($uow, $targetMeta, $targetObject);
      }
    }
  }

  /** {@inheritdoc} */
  public function handlesUrlization()
  {
    return false;
  }
}
