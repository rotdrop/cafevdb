<?php
declare(strict_types=1);
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2021, 2022, 2023 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 *
 * This file based on ldap_contacts_backend, copyright 2020 Arthur Schiwon
 * <blizzz@arthur-schiwon.de>
 *
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
 */

namespace OCA\CAFEVDB\AddressBook;

use \Sabre\DAV\Exception\NotFound as SabreNotFoundException;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ContactsService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;
use OCA\CAFEVDB\Common\Uuid;

/** Generate VCards from the musicians database. */
class MusicianCardBackend implements ICardBackend
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  /** @var Repositories\MusiciansRepository */
  private $musiciansRepository;

  /** @var ContactsService */
  private $contactsService;

  /** {@inheritdoc} */
  public function __construct(
    ConfigService $configService,
    ContactsService $contactsService,
    EntityManager $entityManager,
  ) {
    $this->configService = $configService;
    $this->l = $this->l10n();
    $this->entityManager = $entityManager;
    $this->contactsService = $contactsService;
    if ($this->entityManager->connected()) {
      $this->musiciansRepository = $this->getDatabaseRepository(Entities\Musician::class);
    }
  }

  /** {@inheritdoc} */
  public function getURI(): string
  {
    return $this->appName().'-musicians';
  }

  /** {@inheritdoc} */
  public function getDisplayName(): string
  {
    $name = $this->getConfigValue('musiciansaddressbook');
    if (empty($name)) {
      $name = $this->l->t('%s Musicians', ucfirst($this->getConfigValue('orchestra', 'unknown')));
    }
    return $name;
  }

  /**
   * {@inheritdoc}
   *
   * @throws SabreNotFoundException
   */
  public function getCard(string $name):MusicianCard
  {
    $uuid = $this->getUuidFromUri($name);
    $musician = $this->musiciansRepository->findOneBy([ 'uuid' => $uuid ]);
    if (empty($musician)) {
      throw new SabreNotFoundException;
    }
    return $this->entryToCard($musician);
  }

  /**
   * {@inheritdoc}
   *
   * @todo To support $searchProperties we would need the mapping from
   * VCard properties to data-base properties.
   */
  public function searchCards(string $pattern, array $properties): array
  {
    $this->logDebug('PAT / PROP ' . $pattern . ' / ' . print_r($properties, true));

    if (empty($pattern)) {
      $musicians = $this->musiciansRepository->findAll();
    } else {
      $empty = true;
      $likePattern = '%' . $pattern . '%';
      $criteria = [ [ '(|' => true ] ];
      if (array_search('FN', $properties) !== false) {
        $empty = false;
        $criteria[] = [ 'displayName' => $likePattern ];
        $criteria[] = [ 'nickName' => $likePattern ];
        $criteria[] = [ 'firstName' => $likePattern ];
        $criteria[] = [ 'surName' => $likePattern ];
      }
      if (array_search('EMAIL', $properties) !== false) {
        $empty = false;
        $criteria[] = [ 'email#CONVERT(%s USING utf8mb4)' => $likePattern ];
      }
      if (array_search('UID', $properties) !== false) {
        $empty = false;
        if (strpos($pattern, '%') !== false) {
          // Probably expensive. We only do a pattern match if $pattern contains
          // wildcards.
          $criteria[] = [ 'uuid#BIN2UUID(%s)' => $pattern ];
        } elseif (Uuid::asUuid($pattern) !== null) {
          // only pass exact search term if it is a UUID.
          $criteria[] = [ 'uuid' => $pattern ];
        } else {
          $empty = true;
        }
      }
      if (array_search('CATEGORIES', $properties) !== false) {
        // this could search through instruments and projects
      }
      if (array_search('ORG', $properties) !== false) {
        // this could return all if the pattern matches the orchestra name
      }
      if ($empty) {
        $musicians = [];
      } else {
        $this->logDebug('SEARCH CRITS ' . print_r($criteria, true) . ' ' . $pattern);
        $musicians = $this->musiciansRepository->findBy($criteria);
        $this->logDebug('FOUND ' . count($musicians) . ' FOR ' . 'PAT / PROP ' . $pattern . ' / ' . print_r($properties, true));
      }
    }
    $vCards = [];
    foreach ($musicians as $musician) {
      $vCards[] = $this->entryToCard($musician);
    }
    return $vCards;
  }

  /** {@inheritdoc} */
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

  /** {@inheritdoc} */
  public function getUriFromUuid($uuid)
  {
    return 'musician-'.$uuid.'.vcf';
  }

  /** {@inheritdoc} */
  protected function getUuidFromUri($uri)
  {
    return substr($uri, strlen('musician-'), 36);
  }

  /** {@inheritdoc} */
  protected function entryToCard(Entities\Musician $musician, ?int $lastModified = null): MusicianCard
  {
    $vCard = $this->contactsService->export($musician);
    $uuid = $musician['uuid'];
    $uri = $this->getUriFromUuid($uuid);
    if ($lastModified === null) {
      $info = $this->musiciansRepository->fetchLastModifiedDate([ 'uuid' => $uuid ]);
      $lastModified = strtotime($info['lastModified']) ?: null;
    }
    return new MusicianCard($uri, $lastModified, $vCard);
  }

  /** {@inheritdoc} */
  public function getLastModified(?string $uri = null):int
  {
    $criteria = empty($uri) ? [] : [ 'uuid' => $this->getUuidFromUri($uri) ];
    $info = $this->musiciansRepository->fetchLastModifiedDate($criteria);
    return (empty($info) || empty($info['lastModified'])) ? 0 : strtotime($info['lastModified']);
  }
}
