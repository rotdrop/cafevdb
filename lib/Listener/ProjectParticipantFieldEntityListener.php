<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2023, 2024 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Listener;

use Throwable;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Event as ORMEvent;

use Psr\Log\LoggerInterface as ILogger;
use OCP\AppFramework\IAppContainer;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Service\ProjectParticipantFieldsService;

/**
 * Perform infra-structure update on changes of a defined extra column in the
 * instrumentation table.
 */
class ProjectParticipantFieldEntityListener
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  /** @var EventsService */
  protected $eventsService;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected ILogger $logger,
    protected IAppContainer $appContainer,
    protected EntityManager $entityManager,
  ) {
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function prePersist(Entities\ProjectParticipantField $entity, ORMEvent\PrePersistEventArgs $event)
  {
    // make sure that the field is pulicly readable if it has an associated
    // ProjectEvent
    if ($entity->getParticipantAccess() == Types\EnumAccessPermission::NONE && !empty($entity->getProjectEvent())) {
      $this->setParticipantAccess(Types\EnumAccessPermission::READ);
    }
    $this->getFieldsService()->handlePersistField($entity);
  }

  /** {@inheritdoc} */
  public function preRemove(Entities\ProjectParticipantField $entity, ORMEvent\PreRemoveEventArgs $event)
  {
    if (!$entity->isExpired()) {
      return;
    }
    $this->getFieldsService()->handleRemoveField($entity);
  }

  /** {@inheritdoc} */
  public function preUpdate(Entities\ProjectParticipantField $entity, ORMEvent\PreUpdateEventArgs $event)
  {
    // make sure that the field is pulicly readable if it has an associated
    // ProjectEvent
    if ($entity->getParticipantAccess() == Types\EnumAccessPermission::NONE && !empty($entity->getProjectEvent())) {
      $this->setParticipantAccess(Types\EnumAccessPermission::READ);
    }

    $field = 'name';
    $changeSet = $this->getTranslationChangeSet($entity, $event, $field);
    if ($changeSet) {
      $this->getFieldsService()->handleRenameField($entity, $changeSet[0], $changeSet[1]);
    }

    $field = 'tooltip';
    $changeSet = $this->getTranslationChangeSet($entity, $event, $field);
    if ($changeSet) {
      $this->getFieldsService()->handleChangeFieldTooltip($entity, $changeSet[0], $changeSet[1]);
    }

    $field = 'dataType';
    $changeSet = $this->getTranslationChangeSet($entity, $event, $field);
    if ($changeSet) {
      $this->getFieldsService()->handleChangeFieldType($entity, $changeSet[0], $changeSet[1]);
    }

    $field = 'multiplicity';
    $changeSet = $this->getTranslationChangeSet($entity, $event, $field);
    if ($changeSet) {
      $this->getFieldsService()->handleChangeFieldMultiplicity($entity, $changeSet[0], $changeSet[1]);
    }
  }

  /** @return ProjectParticipantFieldsService */
  private function getFieldsService():ProjectParticipantFieldsService
  {
    return $this->appContainer->get(ProjectParticipantFieldsService::class);
  }

  /**
   * Gedmo\Translatable hack-around: the actual change set sometimes
   * has to be cleared. The work around is to provide the translated
   * changeset in an extra field which is populated on request by
   * Gedmo\Translatable\TranslatableListener.
   *
   * If the field is not present in the translation changeset, then
   * fallback to the changes provided by the ORM event, so
   * this function can also be used for non-translatable fields at the
   * cost of a failing array lookup.
   *
   * @param Entities\ProjectParticipantField $entity
   *
   * @param ORMEvent\PreUpdateEventArgs $event
   *
   * @param string $field
   *
   * @return null|array
   */
  private function getTranslationChangeSet(
    Entities\ProjectParticipantField $entity,
    ORMEvent\PreUpdateEventArgs $event,
    string $field,
  ):?array {
    return $entity->getTranslationChangeSet($field)
      ?? ($event->hasChangedField($field) ? [ $event->getOldValue($field), $event->getNewValue($field) ] : null);
  }
}
