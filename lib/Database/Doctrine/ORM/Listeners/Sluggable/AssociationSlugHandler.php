<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\Sluggable;

use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
use Gedmo\Exception\InvalidMappingException;
use Gedmo\Sluggable\Mapping\Event\SluggableAdapter;
use Gedmo\Sluggable\SluggableListener;
use Gedmo\Tool\Wrapper\AbstractWrapper;
use Gedmo\Sluggable\Handler\SlugHandlerInterface;
use Doctrine\ORM\Mapping\ClassMetadata as ORMMetaData;
use Doctrine\ORM\UnitOfWork;

/**
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
   * @var ObjectManager
   */
  protected $om;

  /**
   * @var SluggableListener
   */
  protected $sluggable;

  /**
   * $options = array(
   *     'relationClass' => 'objectclass',
   *     'inverseSlugField' => 'slug',
   *     'mappedBy' => 'relationField'
   * )
   * {@inheritdoc}
   */
  public function __construct(SluggableListener $sluggable)
  {
    $this->sluggable = $sluggable;
  }

  /**
   * {@inheritdoc}
   */
  public function onChangeDecision(SluggableAdapter $ea, array &$config, $object, &$slug, &$needToChangeSlug)
  {
  }

  /**
   * {@inheritdoc}
   */
  public function postSlugBuild(SluggableAdapter $ea, array &$config, $object, &$slug)
  {
  }

  /**
   * {@inheritdoc}
   */
  public static function validate(array $options, ClassMetadata $meta)
  {
    if (!isset($options['associationSlug']) || !strlen($options['associationSlug'])) {
      throw new InvalidMappingException("'associationSlug' option must be specified for object slug mapping - {$meta->name}");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onSlugCompletion(SluggableAdapter $ea, array &$config, $object, &$slug)
  {
    $om = $ea->getObjectManager();
    /** @var ORMMetadata $meta */
    $meta = $om->getClassMetadata(get_class($object));

    $options = $config['handlers'][get_called_class()];
    $wrapped = AbstractWrapper::wrap($object, $om);
    $oldSlug = $wrapped->getPropertyValue($config['slug']);

    list($associationField, $associationSlugField) = explode('.', $options['associationSlug']);

    // we must be on a to-one side, otherwise distributing the slug
    // makes barely any sense.
    if (!isset($meta->associationMappings[$associationField])
        || ($meta->associationMappings[$associationField]['type'] == ORMMetaData::MANY_TO_MANY)
        || ($meta->associationMappings[$associationField]['type'] == ORMMetaData::MANY_TO_ONE)) {
      throw new InvalidMappingException('We are on the to-many side of '.$associationField);
    }

    $targetEntity = $meta->associationMappings[$associationField]['targetEntity'];
    /** @var ORMMetadata $targetMeta */
    $targetMeta = $om->getClassMetadata($targetEntity);

    $uow = $om->getUnitOfWork();
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
        $oid = spl_object_hash($targetObject);
        $ea->setOriginalObjectProperty($uow, $oid, $associationSlugField, $oldTargetSlug);
        $ea->recomputeSingleObjectChangeSet($uow, $targetMeta, $targetObject);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function handlesUrlization()
  {
    return false;
  }
}
