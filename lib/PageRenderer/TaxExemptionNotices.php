<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2024-2026 Claus-Justus Heine
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

use Spatie\TypeScriptTransformer\Attributes as TSAttributes;

use DateTime;

use OCP\IRequest;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumTaxType as TaxType;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Settings\ConfigConstants;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Storage\DatabaseStorageUtil;
use OCA\CAFEVDB\Storage\UserStorage;

/** Table generator TaxExemptionNotice. */
#[TSAttributes\TypeScript]
class TaxExemptionNotices extends PMETableViewBase
{
  use FieldTraits\FinanceModeNavigationItemTrait;
  use FieldTraits\ParticipantFileFieldsTrait;
  use FieldTraits\ProjectEntityTrait;
  use FieldTraits\QueryFieldTrait;

  public const TEMPLATE = EnumTemplate::TAX_EXEMPTION_NOTICES->value;

  #[TSAttributes\Hidden]
  public const TABLE = DatabaseTables::TAX_EXEMPTION_NOTICES_TABLE;

  protected $joinStructure = [
    self::TABLE => [
      'flags' => self::JOIN_MASTER,
      'entity' => Entities\TaxExemptionNotice::class,
    ],
    DatabaseTables::TAX_EXEMPTION_ITEMS_TABLE => [
      'entity' => null,
      'identifier' => [
        'tax_exemption_notice_id' => 'id',
        'taxation_statutory_source_id' => false,
      ],
      'column' => 'tax_exemption_notice_id',
      'flags' => self::JOIN_READONLY,
    ],
    DatabaseTables::TAXATION_STATUTORY_SOURCES_TABLE => [
      'table' => DatabaseTables::TAXATION_STATUTORY_SOURCES_TABLE,
      'entity' => Entities\TaxationStatutorySource::class,
      'identifier' => [
        'id' => [
          'table' => DatabaseTables::TAX_EXEMPTION_ITEMS_TABLE,
          'column' => 'taxation_statutory_source_id',
        ],
      ],
      'column' => 'id',
      'association' => 'taxationStatutorySources',
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
    protected UserStorage $userStorage,
  ) {
    parent::__construct(
      configService: $configService,
      entityManager: $entityManager,
      request: $request,
      pme: $phpMyEdit,
      pageNavigation: $pageNavigation,
      toolTipsService: $toolTipsService,
    );

    if (empty($this->projectId)) {
      $this->projectId = (int)$this->getConfigValue(ConfigConstants::EXECUTIVE_BOARD_PROJECT_ID_KEY, 0);
      $this->projectName = $this->getConfigValue(ConfigConstants::EXECUTIVE_BOARD_PROJECT_KEY, '');
    }

    $this->findProject(enforce: false);
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function shortTitle(): string
  {
    return $this->l->t('Tax Exemption Notices');
  }

  /** {@inheritdoc} */
  public function render(bool $execute = true):void
  {
    $recordsPerPage  = $this->recordsPerPage;

    $opts            = [];

    $opts['css']['postfix'] = [
      self::TEMPLATE,
      self::CSS_TAG_PROJECT_PARTICIPANT_FIELDS_DISPLAY,
      self::CSS_TAG_SHOW_HIDE_DISABLED,
      self::CSS_TAG_DIRECT_CHANGE,
    ];

    // Number of records to display on the screen
    // Value of -1 lists all records in a table
    $opts['inc'] = $recordsPerPage;

    $opts['tb'] = self::TABLE;

    //$opts['debug'] = true;

    $opts['cgi']['persist'] = [
      PersistentCGIKeys::TEMPLATE => static::TEMPLATE,
      PersistentCGIKeys::TABLE => $opts['tb'],
      PersistentCGIKeys::TEMPLATE_RENDERER => DataConstants::RENDERER_PREFIX_TAG . static::TEMPLATE,
    ];

    // Name of field which is the unique key
    $opts['key'] = 'id';

    // Type of key field (int/real/string/date etc.)
    $opts['key_type'] = 'int';

    $opts['groupby_fields'] = [ 'id' ];
    $opts['groupby_where'] = true;

    // Sorting field(s)
    $opts['sort_field'] = [
      // 'tax_type',
      'assessment_period_start',
    ];

    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    $opts['options'] = 'ACPVD';
    $sort = true; // too few entries

    // Number of lines to display on multiple selection filters
    $opts['multiple'] = '6';

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
      'name'     => 'id',
      'select'   => 'T',
      'input'    => 'R',
      'input|LFAP' => 'RH', // always auto-increment
      'options'  => 'LFAVCPD',
      'maxlen'   => 11,
      'default'  => 0, // auto increment
      'sort'     => true,
    ];

    // add some translations for enum values
    array_walk($this->joinStructure, function(&$joinInfo, $table) {
      switch ($table) {
        case DatabaseTables::TAXATION_STATUTORY_SOURCES_TABLE:
          $joinInfo['sql'] = $this->makeEnumTranslationsJoin($joinInfo, [ 'tax_type' => TaxType::class ]);
          break;
        default:
          break;
      }
    });

    // define join tables
    $this->defineJoinStructure($opts);

    list(, $fieldName) = $this->makeJoinTableField(
      $opts['fdd'], DatabaseTables::TAXATION_STATUTORY_SOURCES_TABLE, 'id', [
        'name' => $this->l->t('Context'),
        'css'      => [ 'postfix' => [ 'taxation-statutoryx-sources', CssClasses::SQUEEZE_SUBSEQUENT_LINES, CssClasses::CLIP_LONG_TEXT,  ], ],
        'display|LVFD' => [ 'popup' => 'data' ],
        'sort' => true,
        'sql' => 'GROUP_CONCAT(DISTINCT $join_col_fqn ORDER BY $order_by)',
        'filter' => [
          'having' => true,
        ],
        'select' => 'M',
        'values' => [
          'description' => [
            'columns' => [ 'l10n_tax_type', 'law' ],
            'divs' => [ ' (', ")" ],
            'ifnull' => [ false, false ],
            'cast' => [ false, false ],
          ],
          'orderby' => '$description ASC',
        ],
        'values2glue' => ',<br/>',
        'display|LF' => [
          'prefix' => '<div class="' . CssClasses::PME_CELL_WRAPPER . '"><div class="' . CssClasses::PME_CELL_SQUEEZER . '">',
          'postfix' => '</div></div>',
          'popup' => 'data',
        ],
      ]);

    list(, $fieldName) = $this->makeJoinTableField(
      $opts['fdd'], DatabaseTables::TAXATION_STATUTORY_SOURCES_TABLE, 'tax_type', [
        'name' => $this->l->t('Tax Types'),
        'css' => [ 'postfix' => [ 'tax-types', ], ],
        'sql' => 'GROUP_CONCAT(DISTINCT $join_col_fqn)',
        'select' => 'M',
        'input' => 'HR',
      ]);

    $opts['fdd'][$fieldName]['values|ACP'] = array_merge(
      $opts['fdd'][$fieldName]['values'],
      [ 'filters' => '$table.deleted IS NULL' ]);

    if ($this->showDisabled) {
      // soft-deletion
      $opts['fdd']['deleted'] = array_merge(
        $this->defaultFDD['deleted'], [
          'name' => $this->l->t('Deleted'),
        ]
      );
    }

    $yearAutocomplete = range((new DateTime)->format('Y')-10, (new DateTime)->format('Y'), 1);

    $opts['fdd']['assessment_period_start'] = [
      'name'     => $this->l->t('From'),
      'css'      => [ 'postfix' => [ 'assessment_period_start', 'year-autocomplete' ], ],
      'input'    => 'M',
      'select'   => 'N',
      'display' => [
        'attributes' => [
          'placeholder' => $this->l->t('YYYY'),
          'data-autocomplete' => $yearAutocomplete,
        ],
      ],
      'maxlen'   => 6,
      'sort'     => $sort,
    ];

    $opts['fdd']['assessment_period_end'] = [
      'name'     => $this->l->t('To'),
      'css'      => [ 'postfix' => [ 'assessment_period_end', 'year-autocomplete', ], ],
      'input'    => 'M',
      'select'   => 'N',
      'display' => [
        'attributes' => [
          'placeholder' => $this->l->t('YYYY'),
          'data-autocomplete' => $yearAutocomplete,
        ],
      ],
      'maxlen'   => 6,
      'sort'     => $sort,
    ];

    $opts['fdd']['tax_office'] = [
      'name'     => $this->l->t('Tax Office'),
      'css'      => [ 'postfix' => [ 'tax-office', ], ],
      'input'    => 'M',
      'select'   => 'T',
      'maxlen'   => 256,
      'sort'     => $sort,
    ];

    $opts['fdd']['tax_number'] = [
      'name'     => $this->l->t('Tax Number'),
      'css'      => [ 'postfix' => [ 'tax-number', ], ],
      'input'    => 'M',
      'select'   => 'T',
      'maxlen'   => 256,
      'sort'     => $sort,
    ];

    $opts['fdd']['date_issued'] = array_merge(
      $this->defaultFDD['date'],
      [
        'name' => $this->l->t('Issued at'),
        'input' => 'M', // required
      ]);

    $opts['fdd']['membership_fees_are_donations'] = [
      'name' => $this->l->t('Membership Fees'),
      'css'      => [ 'postfix' => [ 'membership-fees', ], ],
      'select|CAP' => 'O',
      'select|LVDF' => 'T',
      'sort' => true,
      'default' => false,
      'escape' => false,
      'sqlw' => 'IF($val_qas = "", 0, 1)',
      'values2|CAP' => [ 1 => '' ], // empty label for simple checkbox
      'values2|LVDF' => [
        0 => '',
        1 => '&#10004;',
      ],
      'tooltip' => $this->toolTipsService['page-renderer:tax-exemption-notices:membership-fees'],
    ];

    $opts['fdd']['beneficiary_purpose'] = [
      'name'     => $this->l->t('Beneficiary Purpose'),
      'css'      => [ 'postfix' => [ 'beneficiary-purpose', CssClasses::SQUEEZE_SUBSEQUENT_LINES, ], ],
      'select'   => 'T',
      'maxlen'   => 512,
      'sql|LFVD' => 'REPLACE($join_col_fqn, "\n", "<br/>")',
      'textarea' => [
        'css' => 'wysiwygeditor',
        'rows' => 5,
        'cols' => 50,
      ],
      'display|LFVD' => [
        'popup' => 'data',
        'prefix' => '<div class="' . CssClasses::PME_CELL_WRAPPER . ' half-line-width"><div class="' . CssClasses::PME_CELL_SQUEEZER . '">',
        'postfix' => '</div></div>',
      ],
      'escape'   => false,
      'sort'     => $sort,
    ];

    $opts['fdd']['written_notice_id'] = [
      'name' => $this->l->t('Written Notice'),
      'input|A' => 'HR',
      'css'      => [ 'postfix' => [ 'written-notice', ], ],
      'options' => 'LFACDPV',
      'php|CP' => function($value, $action, $k, $row, $recordId, $pme) {

        if ($pme->hidden($k) || empty($row)) {
          return '';
        }

        $taxTypes = $row[$this->joinQueryField(DatabaseTables::TAXATION_STATUTORY_SOURCES_TABLE, 'tax_type')];
        $taxTypes = explode(',', $taxTypes);
        $assessmentPeriodStart = $row[$this->queryField('assessment_period_start')];
        $assessmentPeriodEnd = $row[$this->queryField('assessment_period_end')];
        $fileName = $this->getLegacyTaxExemptionNoticeFileName(
          $taxTypes,
          $assessmentPeriodStart,
          $assessmentPeriodEnd,
        );

        $dir = $this->getTaxExemptionNoticesPath();

        return '<div class="file-upload-wrapper">
  <table class="file-upload">'
          . $this->dbFileUploadRowHtml(
            $value,
            fieldId: $recordId['id'],
            optionKey: $recordId['id'],
            subDir: null,
            fileBase: $dir . UserStorage::PATH_SEP . $fileName,
            overrideFileName: true,
            musician: null,
            project: null,
            inputValueName: 'written_notice_id',
          )
          . '
  </table>
</div>';
      },
      'php|LFVD' => function($value, $action, $k, $row, $recordId, $pme) {
        if (empty($value)) {
          return $value;
        }

        /** @var Entities\DatabaseStorageFile $file */
        $file = $this->getDatabaseRepository(Entities\DatabaseStorageFile::class)->find($value);

        $downloadLink = $this->di(DatabaseStorageUtil::class)->getDownloadLink($file);

        $dir = $this->getTaxExemptionNoticesPath();

        try {
          $filesAppLink = $this->userStorage->getFilesAppLink($dir, true);
          $filesAppTarget = md5($filesAppLink);
          $filesAppLink = '<a href="' . $filesAppLink . '" target="'.$filesAppTarget . '"
       title="'.$this->toolTipsService['page-renderer:upload:open-parent'].'"
       class="button operation open-parent tooltip-auto' . (empty($filesAppLink) ? ' disabled' : '') . '"
       ></a>';
        } catch (\OCP\Files\NotFoundException $e) {
          $this->logInfo('No file found for ' . $dir);
          $filesAppLink = '';
        }
        return '<div class="flex-container">
'
          . $filesAppLink
          . '<a class="download-link ajax-download tooltip-auto inline-block ' . CssClasses::CLIP_LONG_TEXT . '"
   title="' . $this->toolTipsService['page-renderer:tax-exemption-notices:written-notice'] . '"
   href="' . $downloadLink . '">' . $file->getName() . '</a>
</div>';
      },
    ];

    if ($this->showDisabled) {
      $opts['fdd']['deleted'] = array_merge(
        $this->defaultFDD['deleted'], [
          'tab'  => [ 'id' => 'overview' ],
          'name' => $this->l->t('Invalid Since'),
          'select' => 'T',
          'dateformat' => 'medium',
          'timeformat' => null,
          'css' => [ 'postfix' => [ 'invalidation-date', 'date', ], ],
          'tooltip' => $this->toolTipsService['page-renderer:tax-exemption-notices:deleted'],
        ],
      );
    }

    $opts['fdd']['updated'] =
      array_merge(
        $this->defaultFDD['datetime'],
        [
          'tab' => ['id' => 'miscinfo'],
          'name' => $this->l->t('Last Updated'),
          'nowrap' => true,
          'options' => 'LFVCD',
          'input' => 'R',
          'timeformat' => 'medium',
        ]
      );

    $opts['fdd']['created'] =
      array_merge(
        $this->defaultFDD['datetime'],
        [
          'tab' => ['id' => 'miscinfo'],
          'name' => $this->l->t('Created'),
          'nowrap' => true,
          'options' => 'LFVCD',
          'input' => 'R',
          'timeformat' => 'medium',
        ]
      );

    // redirect all updates through Doctrine\ORM.
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_INSERT][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeInsertDoInsertAll' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeUpdateDoUpdateAll' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_DELETE][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'beforeDeleteSimplyDoDelete' ];

    $opts = Util::arrayMergeRecursive($this->generateBasePMEOptions(), $opts);

    if ($execute) {
      $this->execute($opts);
    } else {
      $this->pme->setOptions($opts);
    }
  }
}
