<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Maintenance\Migrations;

use Throwable;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Exceptions;

/**
 * Ensure that a virtual "not-an-instrument" instrument family exists and add
 * one virtual "busyness relation" instrument.
 *
 * Why: while the status of person could be tracked by the
 * participation-status "associated" we also use the instrument-field in order
 * to duplicate rows in the frontend in case that one biological person plays
 * more than one instrument (in different parts of a performance).
 *
 * In order to capture the case where we have a participant which at the same
 * time provides payed-services for the orchestra we mis-use the instrument
 * field and duplicate this id.
 */
class EnsureNotAnInstrumentBusynessRelation extends AbstractMigration
{
  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Ensure a virtual "busy relation" "not-an-instrument" instrument');
  }

  /** {@inheritdoc} */
  public function execute():bool
  {
    // no need for vanilla SQL, we can simply use the entity-manager ...

    // just fetch all families and check for the untranslated name, could be
    // optimized, but so what

    $this->entityManager->beginTransaction();
    try {
      $oldLocale = null;
      /** @var Entities\InstrumentFamily $notAnInstrumentFamily */
      $notAnInstrumentFamily = $this->getDatabaseRepository(Entities\InstrumentFamily::class)->findOneBy([
        'family' => Entities\ProjectInstrument::NOT_AN_INSTRUMENT_FAMILY,
      ]);
      if ($notAnInstrumentFamily === null) {
        $notAnInstrumentFamily = new Entities\InstrumentFamily();
        $familyName = $this->l->t(Entities\ProjectInstrument::NOT_AN_INSTRUMENT_FAMILY);
        if ($familyName == Entities\ProjectInstrument::NOT_AN_INSTRUMENT_FAMILY) {
          $oldLocale = $this->entityManager->setTranslatableLocale(null);
        }
        $notAnInstrumentFamily->setFamily($this->l->t(Entities\ProjectInstrument::NOT_AN_INSTRUMENT_FAMILY));
        $this->persist($notAnInstrumentFamily);
      }

      $this->flush();

      if ($oldLocale) {
        $this->entityManager->setTranslatableLocale($oldLocale);
        $oldLocale = null;
      }

      $nonInstruments = $this->getDatabaseRepository(Entities\Instrument::class)->findBy([
        'families' => $notAnInstrumentFamily,
        'name' => Entities\ProjectInstrument::NON_INSTRUMENTS,
      ]);
      $nonInstrumentNames = array_map(fn(Entities\Instrument $instrument) => $instrument->getUntranslatedName(), $nonInstruments);
      $missing = array_diff(Entities\ProjectInstrument::NON_INSTRUMENTS, $nonInstrumentNames);
      foreach ($missing as $name) {
        $instrument = new Entities\Instrument();
        $l10nName = $this->l->t($name);
        if ($l10nName == $name) {
          $oldLocale = $this->entityManager->setTranslatableLocale(null);
        }
        $instrument->setName($name)->setSortOrder(0x7fffffff);
        $instrument->getFamilies()->add($notAnInstrumentFamily);
        $notAnInstrumentFamily->getInstruments()->add($instrument);
        $this->persist($instrument);
        $this->flush();
        if ($oldLocale) {
          $this->entityManager->setTranslatableLocale($oldLocale);
          $oldLocale = null;
        }
      }

      $this->flush();
      $this->entityManager->commit();
    } catch (Throwable $t) {
      try {
        $this->entityManager->rollback();
      } catch (Throwable $t2) {
        $t = new Exceptions\DatabaseMigrationException(
          $this->l->t('Rollback of Migration "%s" failed.', $this->description()),
          $t->getCode(),
          $t,
        );
      }
      throw new Exceptions\DatabaseMigrationException(
        $this->l->t('Transactional part of Migration "%s" failed.', $this->description()),
        $t->getCode(),
        $t,
      );
    }
    return true;
  }
}
