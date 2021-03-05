<?php
declare(strict_types=1);
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This file based on ldap_contacts_backend, copyright 2020 Arthur Schiwon
 * <blizzz@arthur-schiwon.de>
 *
 * @license GNU AGPL version 3 or any later version
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\CAFEVDB\AddressBook;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ContactsService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;

class MusicianCardBackend implements ICardBackend
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  /** @var Repositories\MusiciansRepository */
  private $musiciansRepository;

  /** @var ContactsService */
  private $contactsService;

  public function __construct(
    ConfigService $configService
    , ContactsService $contactsService
    , EntityManager $entityManager
  ) {
    $this->configService = $configService;
    $this->l = $this->l10n();
    $this->entityManager = $entityManager;
    $this->musiciansRepository = $this->getDatabaseRepository(Entities\Musician::class);
    $this->contactsService = $contactsService;
  }

  public function getURI(): string {
    return $this->appName().'-musicians';
  }

  public function getDisplayName(): string {
    $name = $this->getConfigValue('musiciansaddressbook');
    if (empty($name)) {
      $name = $this->l->t('%s Musicians', ucfirst($this->getConfigValue('orchestra', 'unknown')));
    }
    return $name;
  }

  /**
   * @throws \Sabre\DAV\Exception\NotFound
   */
  public function getCard($name): MusicianCard
  {
    $uuid = $this->getUuidFromUri($name);
    $musician = $this->musiciansRepository->findOneBy([ 'uuid' => $uuid ]);
    if (empty($musician)) {
      throw new \Sabre\DAV\Exception\NotFound();
    }
    return $this->entryToCard($musician);
  }

  /*
   * @todo To support $searchProperties we would need the mapping from
   * VCard properties to data-base properties.
   */
  public function searchCards(string $pattern, array $properties): array
  {
    $this->logInfo('Search Pattern "'.$pattern.'"');
    $expr = self::criteriaExpr();
    $musicians = $this->musiciansRepository->matching(
      self::criteria()->where($expr->contains('displayName', $pattern))
                      ->orWhere($expr->contains('firstName', $pattern))
                      ->orWhere($expr->contains('surName', $pattern))
    );
    $vCards = [];
    foreach ($musicians as $musician) {
      $vCards[] = $this->entryToCard($musician);
    }
    return $vCards;
  }

  public function getCards(): array
  {
    // to appear in the contacts app, this must really return everything
    // as search is only by client in the presented contacts
    $musicians = $this->musiciansRepository->findAll();
    $vCards = [];
    foreach ($musicians as $musician) {
      $vCards[] = $this->entryToCard($musician);
    }
    return $vCards;
  }

  protected function getUriFromUuid($uuid)
  {
    return 'musician-'.$uuid.'.vcf';
  }

  protected function getUuidFromUri($uri)
  {
    return substr($uri, strlen('musician-'), 36);
  }

  protected function entryToCard(Entities\Musician $musician, ?int $lastModified = null): MusicianCard
  {
    $vCard = $this->contactsService->export($musician);
    $uuid = $musician['uuid'];
    $uri = $this->getUriFromUuid($uuid);
    if ($lastModified === null) {
      $info = reset($this->musiciansRepository->fetchLastModifiedDate([ 'uuid' => $uuid ]));
      $lastModified = strtotime($info['lastModified']);
    }
    return new MusicianCard($uri, $lastModified, $vCard);
  }

  public function getLastModified(?string $uri = null):int
  {
    $criteria = empty($uri) ? [] : [ 'uuid' => $this->getUuidFromUri($uri) ];
    $info = reset($this->musiciansRepository->fetchLastModifiedDate($criteria));
    return strtotime($info['lastModified']);
  }
}
