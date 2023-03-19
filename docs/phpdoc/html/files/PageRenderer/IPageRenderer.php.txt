<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2020, 2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\PageRenderer;

/** Page-renderer interface. */
interface IPageRenderer extends IRenderer
{
  /**
   * Short title for heading.
   *
   * @return string
   */
  public function shortTitle();

  /**
   * Header text informations.
   *
   * @return string
   */
  public function headerText();

  /**
   * Set table-navigation enable/disable.
   *
   * @param bool $enable
   *
   * @return void
   */
  public function navigation(bool $enable):void;

  /**
   * Run underlying table-manager (phpMyEdit for now).
   *
   * @param array $options Override options. Otherwise the state
   * set by render(false) are used.
   *
   * @return void
   */
  public function execute(array $options = []):void;
}
