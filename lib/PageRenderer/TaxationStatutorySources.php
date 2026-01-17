<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2026 Claus-Justus Heine
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

use OCP\IRequest;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\GeoCodingService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Settings\ConfigConstants;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;

/** Table generator for Instruments table. */
class TaxationStatutorySources extends PMETableViewBase
{
  use FieldTraits\FinanceModeNavigationItemTrait;
  use FieldTraits\QueryFieldTrait;

  const TEMPLATE = 'taxation-statutory-sources';
  const TABLE = self::TAXATION_STATUTORY_SOURCES_TABLE;


  protected $joinStructure = [
    self::TABLE => [
      'entity' => Entities\TaxationStatutorySource::class,
      'flags' => self::JOIN_MASTER,
    ],
    self::TABLE . self::VALUES_TABLE_SEP . 'L10NTaxTypes' => [
      'entity' => null,
      'flags' => self::JOIN_READONLY,
      'identifier' => [
        'value' => 'tax_type',
      ],
      'column' => 'l10n_value',
    ],
    self::INVOICES_TABLE => [
      'entity' => Entities\Invoices::class,
      'flags' => self::JOIN_READONLY,
      'identifier' => [
        'id' => false,
        'taxation_statutory_source_id' => 'id',
      ],
      'column' => 'taxation_statutory_source_id',
    ],
    self::TAX_EXEMPTION_ITEMS_TABLE => [
      'entity' => null,
      'identifier' => [
        'tax_exemption_notice_id' => false,
        'taxation_statutory_source_id' => 'id',
      ],
      'column' => 'taxation_statutory_source_id',
      'flags' => self::JOIN_READONLY,
    ],
    self::TAX_EXEMPTION_NOTICES_TABLE => [
      'entity' => Entities\TaxExemptionNotice::class,
      'identifier' => [
        'id' => [
          'table' => self::TAX_EXEMPTION_ITEMS_TABLE,
          'column' => 'tax_exemption_notice_id',
        ],
      ],
      'column' => 'id',
      'flags' => self::JOIN_READONLY,
    ],
  ];

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ConfigService $configService,
    EntityManager $entityManager,
    IRequest $request,
    PHPMyEdit $phpMyEdit,
    PageNavigation $pageNavigation,
    ToolTipsService $toolTipsService,
    protected GeoCodingService $geoCodingService,
  ) {
    parent::__construct(
      self::TEMPLATE,
      //
      $configService,
      $entityManager,
      $request,
      $phpMyEdit,
      $pageNavigation,
      $toolTipsService,
    );
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function shortTitle(): string
  {
    return $this->l->t('Taxation Statutory Sources');
  }

  /** {@inheritdoc} */
  public function render(bool $execute = true):void
  {
    $template        = $this->template;
    $recordsPerPage  = $this->recordsPerPage;
    $expertMode      = $this->expertMode;

    $opts            = [];

    $opts['css']['postfix'] = [
      'direct-change',
      'show-hide-disabled',
    ];

    // Number of records to display on the screen
    // Value of -1 lists all records in a table
    $opts['inc'] = $recordsPerPage;

    $opts['tb'] = self::TABLE;

    //$opts['debug'] = true;

    $opts['cgi']['persist'] = array(
      PersistentCGIKeys::TEMPLATE => $template,
      'table' => $opts['tb'],
      'templateRenderer' => 'template:'.$template,
      );

    // Name of field which is the unique key
    $opts['key'] = [ 'id' => 'int' ];

    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    $opts['options'] = 'ACPVDPF';

    // Number of lines to display on multiple selection filters
    $opts['multiple'] = '4';

    // Navigation style: B - buttons (default), T - text links, G - graphic links
    // Buttons position: U - up, D - down (default)
    //$opts['navigation'] = 'DB';

    // Display special page elements
    $opts['display'] = [
      'form'  => true,
      //'query' => true,
      'sort'  => true,
      'time'  => true,
      'tabs'  => false,
      'navigation' => 'VCPD'
    ];

    $opts['fdd']['id'] = [
      'name'      => 'id',
      'select'    => 'N',
      'input'     => 'RH',
      'options'  => 'VCPDL',
      'maxlen'    => 11,
      'size'      => 11,
      'align'     => 'right',
      'sort'      => true,
      'default'  => '0',  // auto increment
      ];

    array_walk($this->joinStructure, function(&$joinInfo, $table) {
      switch ($table) {
        case self::TABLE . self::VALUES_TABLE_SEP . 'L10NTaxTypes':
          $joinInfo['sql'] = $this->makeEnumTranslationsTable(Types\EnumTaxType::class);
          break;
      }
    });

    // define join tables
    $this->defineJoinStructure($opts);

    // Sorting field(s)
    $opts['sort_field'] = [
      'country',
      'tax_type',
      // self::joinTableFieldName(self::TABLE . self::VALUES_TABLE_SEP . 'L10NTaxTypes', 'l10n_value'),
      'law',
    ];

    $countries = $this->geoCodingService->countryNames();
    $countryGroups = $this->geoCodingService->countryContinents();

    $opts['fdd']['country'] = [
      'name'   => $this->l->t('Country'),
      'select' => 'D',
      'input' => 'M',
      'sort' => true,
      'default' => $this->getConfigValue(ConfigConstants::STREET_ADDRESS_COUNTRY),
      'values2' => $countries,
      'valueGroups' => $countryGroups,
    ];

    $opts['fdd']['tax_type'] = [
      'name'   => $this->l->t('Tax Type'),
      'input' => 'M',
      'select' => 'D',
      'maxlen' => 64,
      'sort'   => true,
      // 'sql' => '$join_col_fqn',
      'values' => [
        'column' => 'value',
        'description' => [
          'columns' => [ '$table.l10n_value' ],
          'ifnull' => [ false, ],
          'cast' => [ false, ],
        ],
        'join' => [ 'reference' => $this->joinTables[self::TABLE . self::VALUES_TABLE_SEP . 'L10NTaxTypes'], ],
      ],
    ];

    $opts['fdd']['rate'] = [
      'name'   => $this->l->t('Tax Rate'),
      'input' => 'M',
      'select' => 'N',
      'maxlen' => 16,
      'sort'   => true,
      'default' => 0,
      'align' => 'right',
      'sql|LFVD' => '$column * 100',
      'mask|LFVD' => '%d%%',
      'display|ACP' => [
        'attributes' => [
          'step' => '0.01',
          'type' => 'number',
        ],
      ],
    ];

    // $this->makeJoinTableField(
    //   $opts['fdd'], self::TABLE . self::VALUES_TABLE_SEP . 'L10NTaxTypes', 'l10n_value', [
    //     'input' => 'RH',
    //   ]);

    $opts['fdd']['law'] = [
      'name'   => $this->l->t('Legal Basis'),
      'input' => 'M',
      'select' => 'T',
      'maxlen' => 255,
      'sort'   => true,
    ];

    $opts['fdd']['hint'] = [
      'name'   => $this->l->t('Hint'),
      'select' => 'T',
      'maxlen' => 1024,
      'sort'   => true,
    ];

    $opts['fdd']['law_text'] = [
      'name' => $this->l->t('Wording of the Law'),
      'input' => 'VR',
      'sql' => '$table.law',
      'sort' => false,
      'php'   =>  function($value, $op, $field, $row, $recordId, $pme) {
        $country = $row[$this->queryField('country')];
        switch ($country) {
          case 'DE':
            $abbreviation = array_pop(explode(' ', $value));
            $link = sprintf(
              'https://www.gesetze-im-internet.de/cgi-bin/htsearch?method=and&suche=suchen&config=Titel_bmjhome2005&words=%s',
              $abbreviation,
            );
            $target = md5($this->appName() . self::TEMPLATE . 'Wording of the Law');
            return '<a href="' . $link . '" target="' . $target . '">www.gesetze-im-internet.de</a>';
          default:
            break;
        }
        return $value;
      },
    ];

    if ($this->showDisabled) {
      // soft-deletion
      $opts['fdd']['deleted'] = array_merge(
        $this->defaultFDD['deleted'], [
          'name' => $this->l->t('Deleted'),
        ]
      );
    }

    if (!$this->showDisabled) {
      $opts['filters']['AND'][] = '$table.deleted IS NULL';
    }

    $opts['groupby_fields'] = [ 'id' ];

    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'beforeUpdateDoUpdateAll' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_INSERT][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'beforeInsertDoInsertAll' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_DELETE][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'beforeDeleteSimplyDoDelete' ];

    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_SELECT][PHPMyEdit::TRIGGER_DATA][] =
      function(&$pme, $op, $step, &$row) use ($expertMode) {
        if (!$expertMode && !empty($row[$this->joinQueryField(self::INSTRUMENTS_TABLE, 'id')])) {
          $pme->options = str_replace('D', '', $pme->options);
        }
        return true;
      };

    $opts = Util::arrayMergeRecursive($this->generateBasePMEOptions(), $opts);

    if ($execute) {
      $this->execute($opts);
    } else {
      $this->pme->setOptions($opts);
    }
  }
}
