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
use OCA\CAFEVDB\Exception\RecordNotFoundException;

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
    return $this->l->t('Musicians');
  }

  /**
   * @throws RecordNotFound
   */
  public function getCard($name): MusicianCard
  {
    $musician = $this->musiciansRepository->findByName($name);
    if (empty($musician)) {
      throw new RecordNotFoundException();
    }
    return $this->entryToCard($musician);
  }

  public function searchCards(string $pattern, int $limit = 0): array
  {
    $musicians = $this->musiciansRepository->matching(self::cExpr()->contains('displayName', $pattern));
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
    $this->logInfo("#CARDS: ".count($vCards));
    return $vCards;
  }

  protected function entryToCard(Entities\Musician $musician): MusicianCard
  {
    $vCardData = $this->contactsService->export($musician);
    $this->logInfo('VCARD is '.get_class($vCardData));
    return new MusicianCard($vCardData);
  }

}
