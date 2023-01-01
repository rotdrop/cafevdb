<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2022 Claus-Justus Heine
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

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;

/**
 * Debit notes from destination accounts.
 *
 * @ORM\Entity
 */
class SepaDebitNote extends SepaBulkTransaction
{
  /**
   * @var \DateTimeImmutable
   *
   * Latest date for the pre-notification of the debitors
   *
   * @ORM\Column(type="date_immutable", nullable=true)
   */
  private $preNotificationDeadline;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=256, nullable=true, options={"comment"="Cloud Calendar Object URI"})
   */
  private $preNotificationEventUri;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=256, nullable=true, options={"comment"="Cloud Calendar Object UID"})
   */
  private $preNotificationEventUid;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=256, nullable=true, options={"comment"="Cloud Calendar Object URI"})
   */
  private $preNotificationTaskUri;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=256, nullable=true, options={"comment"="Cloud Calendar Object UID"})
   */
  private $preNotificationTaskUid;

  /**
   * Set preNotificationDeadline.
   *
   * @param mixed $preNotificationDeadline
   *
   * @return SepaDebitNote
   */
  public function setPreNotificationDeadline(mixed $preNotificationDeadline):SepaDebitNote
  {
    $this->preNotificationDeadline = self::convertToDateTime($preNotificationDeadline);

    return $this;
  }

  /**
   * Get preNotificationDeadline.
   *
   * @return \DateTimeInterface|null
   */
  public function getPreNotificationDeadline():?\DateTimeInterface
  {
    return $this->preNotificationDeadline;
  }

  /**
   * Set preNotificationEventUri.
   *
   * @param null|string $preNotificationEventUri
   *
   * @return SepaDebitNote
   */
  public function setPreNotificationEventUri(?string $preNotificationEventUri):SepaBulkTransaction
  {
    $this->preNotificationEventUri = $preNotificationEventUri;

    return $this;
  }

  /**
   * Get preNotificationEventUri.
   *
   * @return string
   */
  public function getPreNotificationEventUri():?string
  {
    return $this->preNotificationEventUri;
  }

  /**
   * Set preNotificationEventUid.
   *
   * @param null|string $preNotificationEventUid
   *
   * @return SepaDebitNote
   */
  public function setPreNotificationEventUid(?string $preNotificationEventUid):SepaBulkTransaction
  {
    $this->preNotificationEventUid = $preNotificationEventUid;

    return $this;
  }

  /**
   * Get preNotificationEventUid.
   *
   * @return string
   */
  public function getPreNotificationEventUid():?string
  {
    return $this->preNotificationEventUid;
  }

  /**
   * Set preNotificationTaskUri.
   *
   * @param null|string $preNotificationTaskUri
   *
   * @return SepaDebitNote
   */
  public function setPreNotificationTaskUri(?string $preNotificationTaskUri):SepaBulkTransaction
  {
    $this->preNotificationTaskUri = $preNotificationTaskUri;

    return $this;
  }

  /**
   * Get preNotificationTaskUri.
   *
   * @return null|string
   */
  public function getPreNotificationTaskUri():?string
  {
    return $this->preNotificationTaskUri;
  }

  /**
   * Set preNotificationTaskUid.
   *
   * @param null|string $preNotificationTaskUid
   *
   * @return SepaDebitNote
   */
  public function setPreNotificationTaskUid(?string $preNotificationTaskUid):SepaBulkTransaction
  {
    $this->preNotificationTaskUid = $preNotificationTaskUid;

    return $this;
  }

  /**
   * Get preNotificationTaskUid.
   *
   * @return null|string
   */
  public function getPreNotificationTaskUid():?string
  {
    return $this->preNotificationTaskUid;
  }
}
