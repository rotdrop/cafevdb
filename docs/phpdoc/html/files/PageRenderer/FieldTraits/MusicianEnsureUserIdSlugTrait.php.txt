<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2024 Claus-Justus Heine
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

namespace OCA\CAFEVDB\PageRenderer\FieldTraits;

use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;

/**
 * Provide an SQL snippets for use in conditionals in order to determine
 * whether the given musician is a participant.
 */
trait MusicianEnsureUserIdSlugTrait
{
  /**
   * Possibly regenerate the user-id slug.
   *
   * @param PHPMyEdit $pme The phpMyEdit instance.
   *
   * @param string $op The operation, 'insert', 'update' etc.
   *
   * @param string $step 'before' or 'after'.
   *
   * @param array $oldValues Self-explanatory.
   *
   * @param array $changed Set of changed fields, may be modified by the callback.
   *
   * @param null|array $newValues Set of new values, which may also be modified.
   *
   * @return bool If returning @c false the operation will be terminated
   *
   * @todo This would rather belong to some service class.
   */
  public function ensureUserIdSlug(PHPMyEdit &$pme, string $op, string $step, array &$oldValues, ?array &$changed, ?array &$newValues):bool
  {
    $tag = 'user_id_slug';
    if (!empty($pme->fdn[self::joinTableMasterFieldName(self::MUSICIANS_TABLE)])) {
      $tag = $this->joinTableFieldName(static::MUSICIANS_TABLE, $tag);
    }
    if (empty($newValues[$tag])) {
      // force regeneration by setting the slug to a "magic" value.
      $newValues[$tag] = \Gedmo\Sluggable\SluggableListener::PLACEHOLDER_SLUG;
      $changed[] = $tag;
      $changed = array_values(array_unique($changed));
    }

    return true;
  }
}
