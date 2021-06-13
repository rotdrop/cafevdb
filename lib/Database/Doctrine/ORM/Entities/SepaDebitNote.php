<?php
/* Orchestra member, musician and project management application.
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
   * @ORM\Column(type="date_immutable", nullable=false)
   */
  private $preNotificationDeadline;

  /**
   * @var int
   *
   * @ORM\Column(type="string", length=256, nullable=false, options={"comment"="Cloud Calendar Object URI"})
   */
  private $preNotificationEventUri;

  /**
   * @var int
   *
   * @ORM\Column(type="string", length=256, nullable=false, options={"comment"="Cloud Calendar Object URI"})
   */
  private $preNotificationTaskUri;

  /**
   * Set preNotificationDeadline.
   *
   * @param mixed $preNotificationDeadline
   *
   * @return SepaDebitNote
   */
  public function setPreNotificationDeadline($preNotificationDeadline):SepaDebitNote
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
   * @param int $preNotificationEventUri
   *
   * @return SepaDebitNote
   */
  public function setPreNotificationEventUri($preNotificationEventUri):SepaBulkTransaction
  {
    $this->preNotificationEventUri = $preNotificationEventUri;

    return $this;
  }

  /**
   * Get preNotificationEventUri.
   *
   * @return string
   */
  public function getPreNotificationEventUri()
  {
    return $this->preNotificationEventUri;
  }

  /**
   * Set preNotificationTaskUri.
   *
   * @param int $preNotificationTaskUri
   *
   * @return SepaDebitNote
   */
  public function setPreNotificationTaskUri($preNotificationTaskUri):SepaBulkTransaction
  {
    $this->preNotificationTaskUri = $preNotificationTaskUri;

    return $this;
  }

  /**
   * Get preNotificationTaskUri.
   *
   * @return string
   */
  public function getPreNotificationTaskUri()
  {
    return $this->preNotificationTaskUri;
  }
}
