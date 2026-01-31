<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine
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

declare(strict_types=1);

namespace OCA\CAFEVDB\Maintenance\Migrations;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\Migrations\AbstractTransactionalMigration;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version19700101000002 extends AbstractTransactionalMigration
{
  public const INSTRUMENT_FAMILY_NAMES = [
    'strings',
    'string',
    'plucked',
    'wind',
    'wood',
    'brass',
    'percussion',
    'keyboard',
    'miscellaneous',
    Entities\ProjectInstrument::NOT_AN_INSTRUMENT_FAMILY,
  ];
  public const INSTRUMENTS = [
    'violin' => [
      'sort' => 1,
      'families' => ['strings', 'string'],
    ],
    'viola' => [
      'sort' => 2,
      'families' => ['strings', 'string'],
    ],
    'violoncello' => [
      'sort' => 3,
      'families' => ['strings', 'string'],
    ],
    'double bass' => [
      'sort' => 4,
      'families' => ['strings', 'string'],
    ],
    'flute' => [
      'sort' => 10,
      'families' => ['wood', 'wind'],
    ],
    'piccolo' => [
      'sort' => 11,
      'families' => ['wood', 'wind'],
    ],
    'oboe' => [
      'sort' => 20,
      'families' => ['wood', 'wind'],
    ],
    'English horn' => [
      'sort' => 25,
      'families' => ['wood', 'wind'],
    ],
    'clarinet' => [
      'sort' => 30,
      'families' => ['wood', 'wind'],
    ],
    'bass clarinet' => [
      'sort' => 35,
      'families' => ['wood', 'wind'],
    ],
    'bassoon' => [
      'sort' => 40,
      'families' => ['wood', 'wind'],
    ],
    'natural horn' => [
      'sort' => 50,
      'families' => ['brass', 'wind'],
    ],
    'trumpet' => [
      'sort' => 60,
      'families' => ['brass', 'wind'],
    ],
    'trombone' => [
      'sort' => 70,
      'families' => ['brass', 'wind'],
    ],
    'bass trombone' => [
      'sort' => 71,
      'families' => ['brass', 'wind'],
    ],
    'tuba' => [
      'sort' => 80,
      'families' => ['brass', 'wind'],
    ],
    'harp' => [
      'sort' => 90,
      'families' => ['string', 'plucked'],
    ],
    'guitar' => [
      'sort' => 95,
      'families' => ['string', 'plucked'],
    ],
    'timpani' => [
      'sort' => 100,
      'families' => ['percussion'],
    ],
    'drum' => [
      'sort' => 105,
      'families' => ['percussion'],
    ],
    'bass drum' => [
      'sort' => 110,
      'families' => ['percussion'],
    ],
    'cymbals' => [
      'sort' => 201,
      'families' => ['percussion'],
    ],
    'glockenspiel' => [
      'sort' => 203,
      'families' => ['percussion'],
    ],
    'xylophone' => [
      'sort' => 400,
      'families' => ['percussion'],
    ],
    'piano' => [
      'sort' => 5000,
      'families' => ['keyboard'],
    ],
    'organ' => [
      'sort' => 5010,
      'families' => ['keyboard'],
    ],
    'harpsichord' => [
      'sort' => 5015,
      'families' => ['keyboard'],
    ],
    'celesta' => [
      'sort' => 5020,
      'families' => ['keyboard'],
    ],
    'bandoneon' => [
      'sort' => 5025,
      'families' => ['keyboard'],
    ],
    'accordion' => [
      'sort' => 5030,
      'families' => ['keyboard'],
    ],
  ];

  /** {@inheritdoc} */
  public function getDescription(): string
  {
    return $this->l->t('Add standard instrument families');
  }

  /** {@inheritdoc} */
  public function up(Schema $schema): void
  {
    // this up() migration is auto-generated, please modify it to your needs
  }

  /** {@inheritdoc} */
  public function down(Schema $schema): void
  {
    // this down() migration is auto-generated, please modify it to your needs
  }

  /** {@inheritdoc} */
  public function preUp(Schema $schema): void
  {
    // Otherwise the English names will be recorded as translations.
    $oldL10n = $this->entityManager->setTranslatableL10n(null);

    $families = [];
    foreach (self::INSTRUMENT_FAMILY_NAMES as $familyName) {
      $family = new Entities\InstrumentFamily()
        ->setFamily($familyName)
        ;
      $this->entityManager->persist($family);
      $families[$familyName] = $family;
    }

    $instruments = self::INSTRUMENTS;
    foreach (Entities\ProjectInstrument::NON_INSTRUMENTS as $nonInstrumentName) {
      $instruments[$nonInstrumentName] = [
        'families' => [Entities\ProjectInstrument::NOT_AN_INSTRUMENT_FAMILY],
        'sort' => 0x7fffffff,
      ];
    }

    foreach ($instruments as $name => $instrumentInfo) {
      $instrument = new Entities\Instrument()
        ->setName($name)
        ->setSortOrder($instrumentInfo['sort'])
        ;

      foreach ($instrumentInfo['families'] as $familyName) {
        $instrument->getFamilies()->set($familyName, $families[$familyName]);
        // not really necessary here ...
        $families[$familyName]->getInstruments()->set($name, $instrument);
      }
      $this->entityManager->persist($instrument);
    }

    $this->entityManager->flush();

    // restore locale settting
    $this->entityManager->setTranslatableL10n($oldL10n);
  }
}
