<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022 Claus-Justus Heine
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

use OCA\CAFEVDB\PageRenderer\PMETableViewBase as BaseRenderer;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

/** Add the email address traits. */
trait MusicianEmailTrait
{
  /**
   * @param string $musicianIdField The field name of the column with the
   * musician id.
   *
   * @param string $tableTab The id of the column-group the fields should be
   * attached to.
   *
   * @return array
   */
  public function renderMusicianEmailFields(string $musicianIdField = 'id', string $tableTab = 'contact'):array
  {
    $joinStructure = [
      BaseRenderer::MUSICIAN_EMAILS_TABLE => [
        'entity' => Entities\MusicianEmailAddress::class,
        'identifier' => [
          'musician_id' => $musicianIdField,
          'address' => 'email',
        ],
        'column' => 'address',
      ],
      BaseRenderer::MUSICIAN_EMAILS_TABLE . BaseRenderer::VALUES_TABLE_SEP . 'all' => [
        'entity' => Entities\MusicianEmailAddress::class,
        'identifier' => [
          'musician_id' => $musicianIdField,
          'address' => false,
        ],
        'column' => 'address',
      ],

    ];

    $generator = function(&$fdd) use ($musicianIdField, $tableTab, $addCss) {

      list(, $allEmailsFddName) = $this->makeJoinTableField(
        $fdd, BaseRenderer::ALL_EMAILS_TABLE, 'address', Util::arrayMergeRecursive(
          $this->defaultFDD['email'], [
            'name'   => $this->l->t('Em@ils'),
            'tab'    => [ 'id' => 'contact' ],
            'sql'    => 'CONCAT_WS(",", $main_table.email, GROUP_CONCAT(DISTINCT IF($join_col_fqn = $main_table.email, NULL, $join_col_fqn)))',
            'select' => 'M',
            'css'    => [
              'postfix' => [
                'selectize',
                'no-chosen',
                $addCSS,
              ],
            ],
            'values' => [
              'description' => BaseRenderer::trivialDescription(),
            ],
            'display' => [
              'attributes' => [
                'placeholder' => $this->l->t('e.g. someone@somewhere.tld'),
                'data-placeholder' => $this->l->t('e.g. someone@somewhere.tld'),
                'data-selectize-options' => [
                  'create' => [
                    'url' => 'validate/musicians/email',
                    'post' => [
                      'failure' => 'error', // vs. message
                    ],
                    'inputField' => $this->pme->cgiDataName('email'),
                  ],
                  'valueField' => 'email',
                  'labelField' => 'email',
                  'persist' => true,
                  'plugins' => [
                    'drag_drop',
                  ],
                ],
              ],
            ],
          ]));
      $fdd[$allEmailsFddName]['values|ACP'] = Util::arrayMergeRecursive(
        $fdd[$allEmailsFddName]['values'], [
          'filters' => '$table.musician_id = $record_id[id]',
        ],
      );

      $fdd['email'] = Util::arrayMergeRecursive(
        $this->defaultFDD['email'], [
          'name'  => $this->l->t('Principal Em@il'),
          'tab'   => [ 'id' => 'contact' ],
          'input' => 'RM',
          'select' => 'T',
          'select|LF' => 'T',
          'sql'    => '$main_table.email',
          'values' => [
            'table'  => BaseRenderer::MUSICIAN_EMAILS_TABLE,
            'column' => 'address',
            'description' => BaseRenderer::trivialDescription('$table.address'),
            'join'   => [ 'reference' => $this->joinTables[BaseRenderer::MUSICIAN_EMAILS_TABLE], ],
          ],
          'css'   => [
            'postfix' => [
              // 'selectize',
              'no-chosen',
              'duplicates-indicator',
              $addCSS,
            ],
          ],
          'css|VD' => [ 'postfix' => [ 'email', ], ],
          'display' => [
            'attributes' => [
              'placeholder' => $this->l->t('e.g. someone@somewhere.tld'),
              'data-placeholder' => $this->l->t('e.g. someone@somewhere.tld'),
              'data-selectize-options' => [
                'create' => false,
              ],
            ],
          ],
        ]);
      $fdd['email']['values|ACP'] = Util::arrayMergeRecursive(
        $fdd['email']['values'], [
          'filters' => '$table.musician_id = $record_id[id]',
        ],
      );



    };

    return [ $joinStructure, $generator ];
  }
}
