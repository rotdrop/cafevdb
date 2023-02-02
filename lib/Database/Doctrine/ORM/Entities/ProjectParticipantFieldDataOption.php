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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Wrapped\Ramsey\Uuid\UuidInterface;

use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Events;
use OCA\CAFEVDB\Service\ConfigService;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;
use OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoTranslatableListener as TranslatableListener;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Database\Doctrine\Util as DBUtil;
use OCA\CAFEVDB\Common\Uuid;
use OCA\CAFEVDB\Enums\EnumParticipantFieldMultiplicity as FieldMultiplicity;
use OCA\CAFEVDB\Enums\EnumParticipantFieldDataType as FieldType;

use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Event;

use OCA\CAFEVDB\Database\EntityManager;

/**
 * ProjectParticipantFieldsDataOptions
 *
 * @ORM\Table(
 *   name="ProjectParticipantFieldsDataOptions",
 *   indexes={
 *     @ORM\Index(columns={"key"}),
 *    }
 * )
 * @ORM\Entity
 * @Gedmo\TranslationEntity(class="TableFieldTranslation", idToString={"key"="BIN2UUID(%s)"})
 * @Gedmo\SoftDeleteable(
 *   fieldName="deleted",
 *   hardDelete="OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\SoftDeleteable\HardDeleteExpiredUnused"
 * )
 * @ORM\HasLifecycleCallbacks
 */
class ProjectParticipantFieldDataOption implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use CAFEVDB\Traits\TranslatableTrait;
  use CAFEVDB\Traits\SoftDeleteableEntity;
  use CAFEVDB\Traits\TimestampableEntity;
  use CAFEVDB\Traits\UnusedTrait;

  public const GENERATOR_KEY = Uuid::NIL;
  public const GENERATOR_LABEL = '__generator__';

  /**
   * @var ProjectParticipantField
   *
   * Link back to ProjectParticipantField
   *
   * @ORM\ManyToOne(targetEntity="ProjectParticipantField", inversedBy="dataOptions")
   * @ORM\Id
   */
  private $field;

  /**
   * @var \OCA\CAFEVDB\Wrapped\Ramsey\Uuid\UuidInterface
   *
   * @ORM\Column(type="uuid_binary")
   * @ORM\Id
   */
  private $key;

  /**
   * @var string
   *
   * @Gedmo\Translatable(untranslated="untranslatedLabel")
   * @ORM\Column(type="string", length=128, nullable=true)
   */
  private $label;

  /**
   * @var string
   *
   * Untranslated variant of self:$label, filled automatically by
   * Gedmo\Translatable
   */
  private $untranslatedLabel;

  /**
   * Multi-purpose field. For FieldMultiplicity::RECURRING the PHP class
   * name of the generator class.
   *
   * @var string
   *
   * @ORM\Column(type="string", length=1024, nullable=true)
   */
  private $data;

  /**
   * @var float
   * Optional value of a deposit for monetary options.
   *
   * @ORM\Column(type="float", nullable=true)
   */
  private $deposit;

  /**
   * @var int Limit on number of group members for
   * FieldMultiplicity::GROUPSOFPEOPLE, FieldMultiplicity::GROUPOFPEOPLE
   * fields. Misused as starting date for recurring receivables
   * generators.
   *
   * @ORM\Column(type="bigint", nullable=true)
   */
  private $limit;

  /**
   * @var string
   *
   * @Gedmo\Translatable
   * @ORM\Column(type="string", length=4096, nullable=true)
   */
  private $tooltip;

  /**
   * @ORM\OneToMany(targetEntity="ProjectParticipantFieldDatum", mappedBy="dataOption", indexBy="musician_id", cascade={"persist"}, orphanRemoval=true, fetch="EXTRA_LAZY")
   * @Gedmo\SoftDeleteableCascade(delete=false, undelete=true)
   */
  private $fieldData;

  /**
   * @var ProjectPayment
   *
   * @ORM\OneToMany(targetEntity="ProjectPayment", mappedBy="receivableOption")
   */
  private $payments;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct()
  {
    $this->__wakeup();
    $this->fieldData = new ArrayCollection();
    $this->payments = new ArrayCollection();
    $this->key = null;
    $this->field = null;
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function __clone()
  {
    if (empty($this->field) || empty($this->key)) {
      return;
    }
    $oldKey = $this->key;
    $this->__construct();
    $this->key = (string)$oldKey == self::GENERATOR_KEY
               ? $oldKey
               : Uuid::create();
  }

  /** {@inheritdoc} */
  public function __wakeup()
  {
    $this->arrayCTOR();
  }

  /**
   * Set field.
   *
   * @param null|int|ProjectParticipantField $field
   *
   * @return ProjectParticipantFieldDataOption
   */
  public function setField(mixed $field):ProjectParticipantFieldDataOption
  {
    $this->field = $field;

    return $this;
  }

  /**
   * Get field.
   *
   * @return ProjectParticipantField
   */
  public function getField():ProjectParticipantField
  {
    return $this->field;
  }

  /**
   * Set key.
   *
   * @param string|UuidInterface $key
   *
   * @return ProjectParticipantFieldDataOption
   */
  public function setKey($key):ProjectParticipantFieldDataOption
  {
    $key = Uuid::asUuid($key);
    if (empty($key)) {
      throw new Exceptions\DatabaseException("UUID DATA: ".$key);
    }
    $this->key = $key;

    return $this;
  }

  /**
   * Get key.
   *
   * @return UuidInterface
   */
  public function getKey()
  {
    return $this->key;
  }

  /**
   * Set label.
   *
   * @param null|string $label
   *
   * @return ProjectParticipantFieldDataOption
   */
  public function setLabel(?string $label):ProjectParticipantFieldDataOption
  {
    $this->label = $label;
    if ($this->getLocale() == ConfigService::DEFAULT_LOCALE) {
      $this->untranslatedLabel = $this->label;
    }
    return $this;
  }

  /**
   * Get label.
   *
   * @return string|null
   */
  public function getLabel():?string
  {
    return $this->label;
  }

  /**
   * Set untranslatedLabel.
   *
   * @param null|string $untranslatedLabel
   *
   * @return ProjectParticipantFieldDataOption
   *
   * @throws Exceptions\DatabaseReadonlyException
   */
  public function setUntranslatedLabel(?string $untranslatedLabel):ProjectParticipantFieldDataOption
  {
    throw new Exceptions\DatabaseReadonlyException('The property "untranslatedLabel" cannot be set, it is read-only.');
    return $this;
  }

  /**
   * Get untranslatedLabel.
   *
   * @return string|null
   */
  public function getUntranslatedLabel():?string
  {
    return $this->untranslatedLabel;
  }

  /**
   * Set data.
   *
   * @param null|string $data
   *
   * @return ProjectParticipantFieldDataOption
   */
  public function setData(?string $data):ProjectParticipantFieldDataOption
  {
    $this->data = $data;

    return $this;
  }

  /**
   * Get data.
   *
   * @return null|string
   */
  public function getData():?string
  {
    return $this->data;
  }

  /**
   * Set deposit.
   *
   * @param null|float $deposit
   *
   * @return ProjectParticipantFieldDatum
   */
  public function setDeposit(?float $deposit):ProjectParticipantFieldDataOption
  {
    $this->deposit = $deposit;

    return $this;
  }

  /**
   * Get deposit.
   *
   * @return null|float
   */
  public function getDeposit():?float
  {
    return $this->deposit;
  }

  /**
   * Set tooltip.
   *
   * @param string|null $tooltip
   *
   * @return ProjectParticipantFieldDataOption
   */
  public function setTooltip(?string $tooltip):ProjectParticipantFieldDataOption
  {
    $this->tooltip = $tooltip;

    return $this;
  }

  /**
   * Get tooltip.
   *
   * @return string
   */
  public function getTooltip():?string
  {
    return $this->tooltip;
  }

  /**
   * Set limit.
   *
   * @param null|int $limit
   *
   * @return ProjectParticipantFieldDataOption
   */
  public function setLimit(?int $limit):ProjectParticipantFieldDataOption
  {
    $this->limit = $limit;

    return $this;
  }

  /**
   * Get limit.
   *
   * @return int
   */
  public function getLimit()
  {
    return $this->limit;
  }

  /**
   * Set fieldData.
   *
   * @param Collection $fieldData
   *
   * @return ProjectParticipantFieldDataOption
   */
  public function setFieldData(Collection $fieldData):ProjectParticipantFieldDataOption
  {
    $this->fieldData = $fieldData;

    return $this;
  }

  /**
   * Get fieldData.
   *
   * @return Collection
   */
  public function getFieldData():Collection
  {
    return $this->fieldData;
  }

  /**
   * Set payments.
   *
   * @param Collection $payments
   *
   * @return ProjectParticipantPaymentsOption
   */
  public function setPayments(Collection $payments):ProjectParticipantFieldDataOption
  {
    $this->payments = $payments;

    return $this;
  }

  /**
   * Get payments.
   *
   * @return Collection
   */
  public function getPayments():Collection
  {
    return $this->payments;
  }

  /**
   * Filter field-data by musician.
   *
   * @param Musician $musician
   *
   * @return Collection
   *
   * @todo Why does this return a collection? There should be zero or one data
   * item.
   */
  public function getMusicianFieldData(Musician $musician):Collection
  {
    return $this->fieldData->matching(
      DBUtil::criteriaWhere([ 'musician' => $musician ])
    );
  }

  /**
   * Return the number of ProjectParticipantFieldDatum entities and
   * ProjectPayment entities attatched to this option.
   *
   * @return int
   */
  public function usage():int
  {
    return $this->fieldData->count() + $this->payments->count();
  }

  /** @return bool Whether this field links to the cloud-file-systen. */
  public function isFileSystemContext():bool
  {
    return $this->field->getDataType() == FieldType::CLOUD_FILE
      && $this->field->getMultiplicity() != FieldMultiplicity::SIMPLE;
  }

  /**
   * Remove 'label' from the set of translatable fields if it is the base of
   * file- or folder-names and thus should not change on a per-user basis.
   *
   * @param array $fields The array of annotated translatable fields.
   *
   * @return array The array of translatable fields based on the state of the
   * entity. This must be a sub-set of the input array.
   */
  public function filterTranslatableFields(array $fields):array
  {
    if (($this->field instanceof ProjectParticipantField) && $this->isFileSystemContext()) {
      // Field name is used as file-system name, so keep it "constant", do not translate
      return array_filter($fields, fn($field) => $field !== 'label');
    }
    if ((string)$this->key == self::GENERATOR_KEY || $this->label == self::GENERATOR_LABEL) {
      return array_filter($fields, fn($field) => $field !== 'label');
    }
    return $fields;
  }

  /** @var bool */
  private $preUpdatePosted = false;

  /**
   * {@inheritdoc}
   *
   * @ORM\PreUpdate
   */
  public function preUpdate(Event\PreUpdateEventArgs $event)
  {
    $field = 'label';
    if (!$event->hasChangedField($field)) {
      return;
    }
    /** @var OCA\CAFEVDB\Database\EntityManager $entityManager */
    $entityManager = EntityManager::getDecorator($event->getEntityManager());
    $entityManager->dispatchEvent(new Events\PreRenameProjectParticipantFieldOption($this, $event->getOldValue($field), $event->getNewValue($field)));
    $this->preUpdatePosted = true;
  }

  /**
   * {@inheritdoc}
   *
   * @ORM\PostUpdate
   */
  public function postUpdate(Event\LifecycleEventArgs $event)
  {
    if (!$this->preUpdatePosted) {
      return;
    }
    /** @var OCA\CAFEVDB\Database\EntityManager $entityManager */
    $entityManager = EntityManager::getDecorator($event->getEntityManager());
    $entityManager->dispatchEvent(new Events\PostRenameProjectParticipantFieldOption($this));
    $this->preUpdatePosted = false;
  }
}
