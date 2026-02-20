<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Tests\Unit\Database\Legacy\PME;

use OCA\CAFEVDB\Database\Legacy\PME\DefaultOptions as PMEOptions;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;

/** Mock IAppData up to the point that we can fake file-access. */
trait GetPMEStubTrait
{
  private PHPMyEdit $pme;

  /** @return mixed */
  private function getPHPMyEditStub(): mixed
  {

    /** @var PHPMyEdit $pme */
    $this->pme = $this->createStub(PHPMyEdit::class);
    $pmeOptions = new PMEOptions([]);
    foreach ([PHPMyEdit::CGI_SYS_KEY, PHPMyEdit::CGI_DATA_KEY, PHPMyEdit::CGI_OPERATION_KEY] as $key) {
      $this->pme->cgi[PHPMyEdit::CGI_PREFIX_KEY][$key] = $pmeOptions['cgi'][PHPMyEdit::CGI_PREFIX_KEY][$key];
    }
    $this->pme->method('cgiSysName')->willReturnCallback(
      fn(string $suffix = ''): string
      =>
      $this->pme->cgi[PHPMyEdit::CGI_PREFIX_KEY][PHPMyEdit::CGI_SYS_KEY] . $suffix,
    );
    $this->pme->method('cgiDataName')->willReturnCallback(
      fn(string $suffix = ''): string
      =>
      $this->pme->cgi[PHPMyEdit::CGI_PREFIX_KEY][PHPMyEdit::CGI_DATA_KEY] . $suffix,
    );

    return $this->pme;
  }
}
