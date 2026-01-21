<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Entities;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;

use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\InstrumentsRepository;

/** Provide a mock for the instruments repository. */
trait MockInstrumentsRepositoryTrait
{
  private const DESCRIBE_ALL_RESULT = [
    'families' => [
      'Saiten,Streicher',
      'Blas,Holz',
      'Blas,Blech',
      'Saiten,Zupf',
      'Schlag',
      'Tasten',
      'Nicht-Instrumente',
    ],
    'byId' => [
      1 => 'Violine',
      2 => 'Viola',
      3 => 'Violoncello',
      4 => 'Kontrabass',
      5 => 'Flöte',
      6 => 'Pikkoloflöte',
      7 => 'Oboe',
      8 => 'Englischhorn',
      9 => 'Klarinette',
      10 => 'Bassklarinette',
      11 => 'Fagott',
      12 => 'Waldhorn',
      13 => 'Trompete',
      14 => 'Posaune',
      15 => 'Bass Posaune',
      16 => 'Tuba',
      17 => 'Harfe',
      18 => 'Gitarre',
      19 => 'Pauken',
      20 => 'Trommel',
      21 => 'Grosse Trommel',
      22 => 'Becken',
      23 => 'Glockenspiel',
      24 => 'Xylophon',
      25 => 'Piano',
      26 => 'Orgel',
      27 => 'Cembalo',
      28 => 'Celesta',
      29 => 'Bandoneon',
      30 => 'Akkordeon',
      31 => 'Beteiligte*r',
      32 => 'Geschäftspartner*in',
    ],
    'byName' => [
      'Violine' => 'Violine',
      'Viola' => 'Viola',
      'Violoncello' => 'Violoncello',
      'Kontrabass' => 'Kontrabass',
      'Flöte' => 'Flöte',
      'Pikkoloflöte' => 'Pikkoloflöte',
      'Oboe' => 'Oboe',
      'Englischhorn' => 'Englischhorn',
      'Klarinette' => 'Klarinette',
      'Bassklarinette' => 'Bassklarinette',
      'Fagott' => 'Fagott',
      'Waldhorn' => 'Waldhorn',
      'Trompete' => 'Trompete',
      'Posaune' => 'Posaune',
      'Bass Posaune' => 'Bass Posaune',
      'Tuba' => 'Tuba',
      'Harfe' => 'Harfe',
      'Gitarre' => 'Gitarre',
      'Pauken' => 'Pauken',
      'Trommel' => 'Trommel',
      'Grosse Trommel' => 'Grosse Trommel',
      'Becken' => 'Becken',
      'Glockenspiel' => 'Glockenspiel',
      'Xylophon' => 'Xylophon',
      'Piano' => 'Piano',
      'Orgel' => 'Orgel',
      'Cembalo' => 'Cembalo',
      'Celesta' => 'Celesta',
      'Bandoneon' => 'Bandoneon',
      'Akkordeon' => 'Akkordeon',
      'Beteiligte*r' => 'Beteiligte*r',
      'Geschäftspartner*in' => 'Geschäftspartner*in',
    ],
    'idGroups' => [
      1 => 'Saiten,Streicher',
      2 => 'Saiten,Streicher',
      3 => 'Saiten,Streicher',
      4 => 'Saiten,Streicher',
      5 => 'Blas,Holz',
      6 => 'Blas,Holz',
      7 => 'Blas,Holz',
      8 => 'Blas,Holz',
      9 => 'Blas,Holz',
      10 => 'Blas,Holz',
      11 => 'Blas,Holz',
      12 => 'Blas,Blech',
      13 => 'Blas,Blech',
      14 => 'Blas,Blech',
      15 => 'Blas,Blech',
      16 => 'Blas,Blech',
      17 => 'Saiten,Zupf',
      18 => 'Saiten,Zupf',
      19 => 'Schlag',
      20 => 'Schlag',
      21 => 'Schlag',
      22 => 'Schlag',
      23 => 'Schlag',
      24 => 'Schlag',
      25 => 'Tasten',
      26 => 'Tasten',
      27 => 'Tasten',
      28 => 'Tasten',
      29 => 'Tasten',
      30 => 'Tasten',
      31 => 'Nicht-Instrumente',
      32 => 'Nicht-Instrumente',
    ],
    'nameGroups' => [
      'Violine' => 'Saiten,Streicher',
      'Viola' => 'Saiten,Streicher',
      'Violoncello' => 'Saiten,Streicher',
      'Kontrabass' => 'Saiten,Streicher',
      'Flöte' => 'Blas,Holz',
      'Pikkoloflöte' => 'Blas,Holz',
      'Oboe' => 'Blas,Holz',
      'Englischhorn' => 'Blas,Holz',
      'Klarinette' => 'Blas,Holz',
      'Bassklarinette' => 'Blas,Holz',
      'Fagott' => 'Blas,Holz',
      'Waldhorn' => 'Blas,Blech',
      'Trompete' => 'Blas,Blech',
      'Posaune' => 'Blas,Blech',
      'Bass Posaune' => 'Blas,Blech',
      'Tuba' => 'Blas,Blech',
      'Harfe' => 'Saiten,Zupf',
      'Gitarre' => 'Saiten,Zupf',
      'Pauken' => 'Schlag',
      'Trommel' => 'Schlag',
      'Grosse Trommel' => 'Schlag',
      'Becken' => 'Schlag',
      'Glockenspiel' => 'Schlag',
      'Xylophon' => 'Schlag',
      'Piano' => 'Tasten',
      'Orgel' => 'Tasten',
      'Cembalo' => 'Tasten',
      'Celesta' => 'Tasten',
      'Bandoneon' => 'Tasten',
      'Akkordeon' => 'Tasten',
      'Beteiligte*r' => 'Nicht-Instrumente',
      'Geschäftspartner*in' => 'Nicht-Instrumente',
    ],
  ];

  /** @return InstrumentsRepository */
  public function getInstrumentsRepositoryMock(): InstrumentsRepository
  {
    $instrumentsRepository = $this->getMockBuilder(InstrumentsRepository::class)
      ->disableOriginalConstructor()
      ->getMock();
    $instrumentsRepository->method('describeAll')->willReturn(self::DESCRIBE_ALL_RESULT);

    return $instrumentsRepository;
  }
}
