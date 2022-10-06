<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Maintenance\Sanitizers;

use OCP\ILogger;

use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\Util as DBUtil;

/**
 * See that we always have both, some@gmail.com as well as some@googlemail.com.
 */
class GoogleMailSanitizer extends AbstractSanitizer
{
  const NAME = 'googlemail-gmail';

  const GOOGLEMAIL_DOMAIN = '@googlemail.com';
  const GMAIL_DOMAIN = '@gmail.com';

  /** @var bool */
  protected $validated = null;

  /** @var Entities\MusicianEmailAddress */
  protected $entity;

  /** {@inheritdoc} */
  public function __construct(
    EntityManager $entityManager,
    ILogger $logger = null,
  ) {
    parent::__construct(
      $entityManager,
      $logger,
    );
  }

  /** {@inheritdoc} */
  public static function getDescription():string
  {
    return 'Add each other of the two Google email addresses if one is already present.';
  }

  /** {@inheritdoc} */
  public static function getEntityClass():string
  {
    return Entities\MusicianEmailAddress::class;
  }

  /** {@inheritdoc} */
  public function setEntity(mixed $entity):void
  {
    parent::setEntity($entity);
    $this->validated = null;
  }

  /**
   * @return null|string Return the respective alternate Google mail address.
   */
  private function otherAddress():?string
  {
    $address = $this->entity->getAddress();
    $secondAddress = null;
    if (str_ends_with($address, self::GOOGLEMAIL_DOMAIN)) {
      $secondAddress = substr($address, 0, -strlen(self::GOOGLEMAIL_DOMAIN)) . self::GMAIL_DOMAIN;
    } elseif (str_ends_with($address, self::GMAIL_DOMAIN)) {
      $secondAddress = substr($address, 0, -strlen(self::GMAIL_DOMAIN)) . self::GOOGLEMAIL_DOMAIN;
    }
    return $secondAddress;
  }

  /**
   * {@inheritdoc}
   *
   * @return bool Return \false if either of the other Google email addresses
   * is missing if the other one is present.
   */
  public function validate():bool
  {
    if ($this->validated !== null) {
      $this->addMessage('Already validated: ' . $this->entity->getAddress(), self::VERBOSITY_DEBUG);
      return $this->validated;
    }
    $this->messages = [];
    $otherAddress = $this->otherAddress();
    if (empty($otherAddress)) {
      $this->addMessage(sprintf('Address "%s" is not a google address.', $this->entity->getAddress()), self::VERBOSITY_VERY_VERBOSE);
      $this->validated = true;
    } else {
      $this->addMessage(
        sprintf(
          'Address "%s" requires the additional address "%2$s".',
          $this->entity->getAddress(),
          $otherAddress,
        ),
        self::VERBOSITY_VERY_VERBOSE
      );
      $musician = $this->entity->getMusician();
      $matching = $musician->getEmailAddresses()->filter(fn(Entities\MusicianEmailAddress $address) => $address->getAddress() == $otherAddress);
      // matching(DBUtil::criteriaWhere([ 'address' => $otherAddress ]));
      $this->validated = $matching->count() > 0;
      if ($this->validated) {
        $this->addMessage('Both google addresses are already present.', self::VERBOSITY_VERY_VERBOSE);
      } else {
        $this->addMessage(sprintf('The additional address "%s" is missing.', $otherAddress), self::VERBOSITY_VERBOSE);
      }
    }
    return $this->validated;
  }

  /** {@inheritdoc} */
  public function sanitizePersist(bool $flush = false):void
  {
    if ($this->validate()) {
      return;
    }
    $otherAddress = $this->otherAddress();
    $musician = $this->entity->getMusician();
    $otherEntity = new Entities\MusicianEmailAddress($otherAddress, $musician);
    $musician->getEmailAddresses()->set($otherAddress, $otherEntity);
    try {
      $this->persist($otherEntity);
      if ($flush) {
        $this->flush();
      }
    } catch (\Throwable $t) {
      throw new Exceptions\SanitizerException(
        sprintf(
          'Persisting the other Google address "%1$s" failed (our\'s is "%2$s").',
          $otherAddress,
          $this->entity->getAddress(),
        ),
        0,
        $t
      );
    }
  }

  /** {@inheritdoc} */
  public function sanitizeRemove(bool $flush = false):void
  {
    if ($this->validate() === false) {
      return;
    }
    $otherAddress = $this->otherAddress();
    $musician = $this->entity->getMusician();
    $matching = $musician->getEmailAddresses()->filter(fn(Entities\MusicianEmailAddress $address) => $address->getAddress() == $otherAddress);
    // $matching = $musician->getEmailAddresses()->matching(DBUtil::criteriaWhere([ 'address' => $otherAddress ]));
    if ($matching->count() == 0) {
      $this->addMessage(sprintf('Other address "%s" already removed.', $otherAddress), self::VERBOSITY_VERBOSE);
      throw new Exceptions\SanitizerNotNeededException(
        sprintf(
          'The alternate Google-email "%s" does not seem to be configured (our\'s is "%2$s").',
          $otherAddress,
          $this->entity->getAddress(),
        )
      );
    }
    /** @var Entities\MusicianEmailAddress $otherEntity */
    $otherEntity = $matching->first();
    if ($this->entity->isPrimaryAddress() || $otherEntity->isPrimaryAddress()) {
      // cancel removal, this is the primary email address
      $this->addMessage(sprintf('Refusing to remove the primary email address "%s".', $this->entity->getMusician()->getEmail()), self::VERBOSITY_VERBOSE);

      $this->persist($this->entity);
      $musician->getEmailAddresses()->set($this->entity->getAddress(), $this->entity);

      if ($flush) {
        $this->flush();
      }
      return;
    }

    $this->addMessage(sprintf('Removing also the other google-address "%1$s" (%2$s).', $otherAddress, $this->entity->getAddress()), self::VERBOSITY_VERBOSE);
    $this->remove($otherEntity, flush: $flush);
  }
}
