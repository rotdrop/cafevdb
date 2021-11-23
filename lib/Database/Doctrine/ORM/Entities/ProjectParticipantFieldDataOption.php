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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Wrapped\Ramsey\Uuid\UuidInterface;

use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Events;
use OCA\CAFEVDB\Service\ConfigService;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Database\Doctrine\Util as DBUtil;
use OCA\CAFEVDB\Common\Uuid;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as Multiplicity;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as DataType;

use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Event;

/**
 * ProjectParticipantFieldsDataOptions
 *
 * @ORM\Table(name="ProjectParticipantFieldsDataOptions")
 * @ORM\Entity
 * @Gedmo\TranslationEntity(class="TableFieldTranslation")
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
   * Multi-purpose field. For Multiplicity::RECURRING the PHP class
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
   * Multiplicity::GROUPSOFPEOPLE, Multiplicity::GROUPOFPEOPLE
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

  public function __construct()
  {
    $this->__wakeup();
    $this->fieldData = new ArrayCollection();
    $this->payments = new ArrayCollection();
    $this->key = null;
    $this->field = null;
  }

  public function __clone()
  {
    if (empty($this->field) || empty($this->key)) {
      return;
    }
    $oldKey = $this->key;
    $this->__construct();
    $this->key = $oldKey == Uuid::nil()
               ? $oldKey
               : Uuid::create();
  }

  public function __wakeup()
  {
    $this->arrayCTOR();
    $this->forceTranslationLocale();
  }

  protected function forceTranslationLocale()
  {
    $field = $this->field;
    if ($field instanceof ProjectParticipantField) {
      if ($field->getDataType() == Types\EnumParticipantFieldDataType::CLOUD_FILE
          || $field->getDataType() == Types\EnumParticipantFieldDataType::CLOUD_FOLDER) {
        $this->setLocale(ConfigService::DEFAULT_LOCALE);
      }
    }
  }

  /**
   * Set field.
   *
   * @param ProjectParticipantField $field
   *
   * @return ProjectParticipantFieldDataOption
   */
  public function setField($field):ProjectParticipantFieldDataOption
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
    if (empty($key = Uuid::asUuid($key))) {
      throw new \Exception("UUID DATA: ".$key);
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
   * @param null|string $float
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
   * @param int $limit
   *
   * @return ProjectParticipantFieldDataOption
   */
  public function setLimit($limit):ProjectParticipantFieldDataOption
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
  public function setFieldData($fieldData):ProjectParticipantFieldDataOption
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
  public function setPayments($payments):ProjectParticipantFieldDataOption
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
   * Filter field-data by musician
   *
   * @param Musician $musician
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
   */
  public function usage():int
  {
    return $this->fieldData->count()
      + $this->payments->count();
  }

  /** @var bool */
  private $preUpdatePosted = false;

  /**
   * @ORM\PreUpdate
   *
   * @param Event\PreUpdateEventArgs $event
   */
  public function preUpdate(Event\PreUpdateEventArgs $event)
  {
    $field = 'label';
    if (!$event->hasChangedField($field)) {
      return;
    }
    /** @var OCA\CAFEVDB\Database\EntityManager $entityManager */
    $entityManager = $event->getEntityManager();
    $entityManager->dispatchEvent(new Events\PreRenameProjectParticipantFieldOption($this, $event->getOldValue($field), $event->getNewValue($field)));
    $this->preUpdatePosted = true;
  }

  /**
   * @ORM\PostUpdate
   *
   * @param Event\LifecycleEventArgs $event
   */
  public function postUpdate(Event\LifecycleEventArgs $event)
  {
    if (!$this->preUpdatePosted) {
      return;
    }
    /** @var OCA\CAFEVDB\Database\EntityManager $entityManager */
    $entityManager = $event->getEntityManager();
    $entityManager->dispatchEvent(new Events\PostRenameProjectParticipantFieldOption($this));
    $this->preUpdatePosted = false;
  }

  /**
   * @ORM\PostLoad
   */
  public function postLoad(Event\LifecycleEventArgs $eventArgs)
  {
    $this->forceTranslationLocale();
  }
}
