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
use OCA\CAFEVDB\Common\Util;

/** Add the email address traits. */
trait MusicianEmailsTrait
{
  /**
   * @param string $musicianIdField The field name of the column with the
   * musician id. If null assume that the musicians table is the master table
   * in the join and the column name is 'id'.
   *
   * @param string $tableTab The id of the column-group the fields should be
   * attached to.
   *
   * @param array $css Additional css-classes to add to the field definition
   * HTML entities.
   *
   * @return array
   */
  public function renderMusicianEmailFields(
    ?string $musicianIdField = null,
    string $tableTab = 'contact',
    array $css = [],
  ):array {
    if ($musicianIdField === null) {
      $musicianIdField = 'id';
      $address = 'email';
    } else {
      $address = [
        'table' => BaseRenderer::MUSICIANS_TABLE,
        'column' => 'email',
      ];
    }

    $joinStructure = [
      BaseRenderer::MUSICIAN_EMAILS_TABLE => [
        'entity' => Entities\MusicianEmailAddress::class,
        'identifier' => [
          'musician_id' => $musicianIdField,
          'address' => $address,
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

    $generator = function(&$fdd) use ($musicianIdField, $tableTab, $address, $css) {

      if ($address == 'email') {
        $emailField = '$main_table.email';
      } else {
        $emailField = $this->joinTables[BaseRenderer::MUSICIANS_TABLE] . '.' . 'email';
      }

      list(, $allEmailsFddName) = $this->makeJoinTableField(
        $fdd,
        BaseRenderer::MUSICIAN_EMAILS_TABLE . BaseRenderer::VALUES_TABLE_SEP . 'all',
        'address',
        Util::arrayMergeRecursive(
          $this->defaultFDD['email'], [
            'name'   => $this->l->t('Em@ils'),
            'tab'    => [ 'id' => $tableTab ],
            'sql'    => 'CONCAT_WS(",", ' . $emailField . ', GROUP_CONCAT(DISTINCT IF($join_col_fqn = ' . $emailField .  ', NULL, $join_col_fqn)))',
            'input' => 'M',
            'select|FL' => 'T',
            'select' => 'M',
            'css'    => [
              'postfix' => array_merge([
                'selectize',
                'no-chosen',
              ], $css),
            ],
            'values' => [
              'description' => BaseRenderer::trivialDescription(),
            ],
            'display|LF' => [
              'popup' => 'data',
              'select' => 'M',
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
            'tooltip' => $this->toolTipsService['page-renderer:musicians:emails:all'],
          ]));
      $fdd[$allEmailsFddName]['values|ACP'] = Util::arrayMergeRecursive(
        $fdd[$allEmailsFddName]['values'], [
          'filters' => '$table.musician_id = $record_id[' . $musicianIdField . ']',
        ],
      );

      $emailFieldDescription = Util::arrayMergeRecursive(
        $this->defaultFDD['email'], [
          'name'  => $this->l->t('Principal Em@il'),
          'tab'   => [ 'id' => $tableTab ],
          'options' => 'ACDPV',
          'input' => 'RM',
          'select' => 'T',
          'select|LF' => 'T',
          'sql'    => $emailField,
          'values' => [
            'table'  => BaseRenderer::MUSICIAN_EMAILS_TABLE,
            'column' => 'address',
            'description' => BaseRenderer::trivialDescription('$table.address'),
            'join'   => [ 'reference' => $this->joinTables[BaseRenderer::MUSICIAN_EMAILS_TABLE], ],
          ],
          'css'   => [
            'postfix' => array_merge([
              'selectize',
              'no-chosen',
              'duplicates-indicator',
            ], $css),
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
          'tooltip' => $this->toolTipsService['page-renderer:musicians:emails:principal'],
        ]);

      if ($address == 'email') {
        $fdd['email'] = $emailFieldDescription;
        $emailFddName = 'email';
      } else {
        list(, $emailFddName) = $this->makeJoinTableField($fdd, BaseRenderer::MUSICIANS_TABLE, 'email', $emailFieldDescription);
      }
      $fdd[$emailFddName]['values|ACP'] = Util::arrayMergeRecursive(
        $fdd[$emailFddName]['values'], [
          'filters' => '$table.musician_id = $record_id[' . $musicianIdField . ']',
        ],
      );
    };

    return [ $joinStructure, $generator ];
  }
}
