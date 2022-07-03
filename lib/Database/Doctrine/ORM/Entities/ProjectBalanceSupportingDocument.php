<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Event;

/**
 * ProjectBalanceSupportingDocument
 *
 * A table which combines documents from the encrypted Files table to form a
 * compound supporting, enumerated document in order to support the financial
 * post-process labor.
 *
 * @ORM\Table(name="ProjectBalanceSupportingDocuments")
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectBalanceSupportingDocumentsRepository")
 */
class ProjectBalanceSupportingDocument implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\TimestampableEntity;
  use CAFEVDB\Traits\SoftDeleteableEntity;

  /**
   * @ORM\ManyToOne(targetEntity="Project", inversedBy="participantInstruments", fetch="EXTRA_LAZY")
   * @ORM\Id
   */
  private $project;

  /**
   * @var int
   *
   * This is a POSITIVE per-musician sequence count. It currently is
   * incremented using
   * \OCA\CAFEVDB\Database\Doctrine\ORM\Traits\PerProjectSequenceTrait
   *
   * @ORM\Column(type="integer")
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="NONE")
   * _AT_ORM\GeneratedValue(strategy="CUSTOM")
   * _AT_ORM\CustomIdGenerator(class="OCA\CAFEVDB\Database\Doctrine\ORM\Mapping\PerProjectSequenceGenerator")
   */
  private $sequence;

  /**
   * @var EncryptedFile
   *
   * @ORM\ManyToMany(targetEntity="EncryptedFile")
   * @ORM\JoinTable(
   *   joinColumns={
   *     @ORM\JoinColumn(name="project_id", referencedColumnName="project_id"),
   *     @ORM\JoinColumn(name="sequence", referencedColumnName="sequence")
   *   },
   *   inverseJoinColumns={@ORM\JoinColumn(unique=true)}
   * )
   */
  private $documents;
}
