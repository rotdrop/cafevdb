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

namespace OCA\CAFEVDB\PageRenderer;

use DateTime;

use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;

use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\Finance\FinanceService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Storage\DatabaseStorageUtil;
use OCA\CAFEVDB\Storage\UserStorage;

use OCA\CAFEVDB\Common\Util;

/** Table generator for DonationReceipt entities. */
class DonationReceipts extends PMETableViewBase
{
  use \OCA\CAFEVDB\Storage\Database\DatabaseStorageNodeNameTrait;
  use FieldTraits\MusicianInProjectTrait;
  use FieldTraits\MusicianPublicNameTrait;
  use FieldTraits\ParticipantFileFieldsTrait;
  use FieldTraits\QueryFieldTrait;

  public const TEMPLATE = 'donation-receipts';
  public const TABLE = 'DonationReceipts';
  public const AMOUNT_CHECK_FAILURE = 'amount-check-failure';

  public const FORM_DATA = [
    'template' => self::TEMPLATE,
    'table' => self::TABLE,
    'self-test-failure' => self::AMOUNT_CHECK_FAILURE,
  ];

  protected $joinStructure = [
    self::TABLE => [
      'flags' => self::JOIN_MASTER,
      'entity' => Entities\DonationReceipt::class,
    ],
    self::COMPOSITE_PAYMENTS_TABLE => [
      'entity' => Entities\CompositePayment::class,
      'identifier' => [
        'id' => 'donation_id',
      ],
      'column' => 'subject',
      'flags' => self::JOIN_READONLY,
    ],
    self::PROJECT_PAYMENTS_TABLE => [
      'entity' => Entities\ProjectPayment::class,
      'identifier' => [
        'id' => false,
        'composite_payment_id' => [
          'table' => self::COMPOSITE_PAYMENTS_TABLE,
          'column' => 'id',
        ],
      ],
      'column' => 'id',
      'flags' => self::JOIN_READONLY,
    ],
    self::TAX_EXEMPTION_NOTICES_TABLE => [
      'entity' => Entities\TaxExemptionNotice::class,
      'identifier' => [
        'id' => 'tax_exemption_notice_id',
      ],
      'column' => 'id',
      'flags' => self::JOIN_READONLY,
    ],
    self::PROJECTS_TABLE => [
      'entity' => Entities\Project::class,
      'identifier' => [
        'id' => [
          'table' => self::COMPOSITE_PAYMENTS_TABLE,
          'column' => 'project_id',
        ],
      ],
      'column' => 'id',
      'flags' => self::JOIN_READONLY,
    ],
    self::MUSICIANS_TABLE => [
      'entity' => Entities\Musician::class,
      'identifier' => [
        'id' => [
          'table' => self::COMPOSITE_PAYMENTS_TABLE,
          'column' => 'musician_id',
        ],
      ],
      'column' => 'id',
      'flags' => self::JOIN_READONLY,
    ],
    self::PROJECT_PARTICIPANTS_TABLE => [
      'entity' => Entities\ProjectParticipant::class,
      'identifier' => [
        'musician_id' => [
          'table' => self::MUSICIANS_TABLE,
          'column' => 'id',
        ],
        'project_id' => false,
      ],
      'column' => 'musician_id',
      'flags' => self::JOIN_READONLY,
    ],
  ];

  /** @var UserStorage */
  protected $userStorage;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ConfigService $configService,
    RequestParameterService $requestParameters,
    EntityManager $entityManager,
    PHPMyEdit $phpMyEdit,
    ToolTipsService $toolTipsService,
    PageNavigation $pageNavigation,
    UserStorage $userStorage,
  ) {
    parent::__construct(self::TEMPLATE, $configService, $requestParameters, $entityManager, $phpMyEdit, $toolTipsService, $pageNavigation);

    $this->userStorage = $userStorage;
    if ($this->projectId > 0) {
      $this->project = $this->getDatabaseRepository(Entities\Project::class)->find($this->projectId);
    }
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function shortTitle()
  {
    return $this->l->t('Donation Receipts');
  }

  /** {@inheritdoc} */
  public function render(bool $execute = true):void
  {
    $template        = $this->template;
    $recordsPerPage  = $this->recordsPerPage;

    $opts            = [];

    $projectMode = false;

    $opts['groupby_fields'] = [ 'id' ];
    $opts['groupby_where'] = true;

    /**
     * Standard css classes for displaying records and coloring of invalid records.
     *
     * @param string $name Always 'row'.
     *
     * @param null $position Always \null.
     *
     * @param null $divider Always \null.
     *
     * @param null|array $row Data row, ist \null in list mode.
     *
     * @param PHPMyEdit $pme
     *
     * @return array Array of css classes.
     */
    $opts['css']['postfix'] = function(string $name, null $position, null $divider, ?array $row, PHPMyEdit $pme):array {
      $classes = [
        self::TEMPLATE,
        self::CSS_TAG_PROJECT_PARTICIPANT_FIELDS_DISPLAY,
        self::CSS_TAG_SHOW_HIDE_DISABLED,
        self::CSS_TAG_DIRECT_CHANGE,
      ];
      if ($row !== null && !($row[$this->joinQueryField(self::PROJECT_PAYMENTS_TABLE, 'amount_check')] ?? true)) {
        $classes[] = self::AMOUNT_CHECK_FAILURE;
      }
      return $classes;
    };

    /**
     * Coloring of invalid rows.
     *
     * @param string $name Always 'row'.
     *
     * @param null $position Always \null.
     *
     * @param string $divider Always 'next'.
     *
     * @param array $row Data row
     *
     * @param PHPMyEdit $pme
     *
     * @return array Array of css classes.
     */
    $opts['css']['row'] = function(string $name, null $position, string $divider, array $row, PHPMyEdit $pme):array {
      if ($row[$this->joinQueryField(self::PROJECT_PAYMENTS_TABLE, 'amount_check')] ?? true) {
        return [];
      }
      return [
        $this->cssPrefix() . '-' . self::AMOUNT_CHECK_FAILURE,
      ];
    };

    // Number of records to display on the screen
    // Value of -1 lists all records in a table
    $opts['inc'] = $recordsPerPage;

    $opts['tb'] = self::TABLE;

    //$opts['debug'] = true;

    $opts['cgi']['persist'] = [
      'template' => $template,
      'table' => $opts['tb'],
      'templateRenderer' => 'template:'.$template,
    ];

    // Name of field which is the unique key
    $opts['key'] = 'id';

    // Type of key field (int/real/string/date etc.)
    $opts['key_type'] = 'int';

    // Sorting field(s)
    $opts['sort_field'] = [
      'id',
      $this->joinTableFieldName(self::PROJECTS_TABLE, 'year'),
      $this->joinTableFieldName(self::PROJECTS_TABLE, 'name'),
    ];

    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    $opts['options'] = 'ACPVD';

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

    // Display special page elements
    $opts['display'] =  Util::arrayMergeRecursive(
      $opts['display'] ?? [], [
        'form'  => true,
        'sort'  => true,
        'time'  => true,
        'tabs'  => [
          [
            'id' => 'payment',
            'tooltip' => $this->toolTipsService['page-renderer:donation-receipts:tabs:payment'],
            'name' => $this->l->t('Payment'),
          ], [
            'id' => 'document',
            'tooltip' => $this->toolTipsService['page-renderer:donation-receipts:tabs:document'],
            'name' => $this->l->t('Supporting Document'),
          ], [
            'id' => 'miscinfo',
            'tooltip' => $this->toolTipsService['page-renderer:tab:miscinfo'],
            'name' => $this->l->t('Miscellaneous Data'),
          ], [
            'id' => 'tab-all',
            'tooltip' => $this->toolTipsService['page-renderer:tab:showall'],
            'name' => $this->l->t('Display all columns'),
          ],
        ],
      ]);
    if ($this->addOperation()) {
      $opts['display']['tabs'] = false;
    }

    // global data to be attached to the form
    $opts['data']['form'] = self::FORM_DATA;

    $opts['fdd']['id'] = [
      'name'     => 'id',
      'select'   => 'T',
      'align'    => 'right',
      'input'    => ($this->expertMode ? 'R' : 'RH'),
      'input|LFAP' => 'RH', // always auto-increment
      'options'  => 'LFAVCPD',
      'maxlen'   => 11,
      'default'  => 0, // auto increment
      'sort'     => true,
    ];

    $joinTables = $this->defineJoinStructure($opts);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'id',
      [
        'name' => $this->l->t('Musician'),
        'tab'  => [ 'id' => 'tab-all' ],
        'css' => [ 'postfix' => [ 'musician-id', 'allow-empty', ], ],
        'sql' => static::musicianPublicNameSql(),
        'select|AFL' => 'D',
        'select|CVD' => 'T',
        'input' => 'R',
        'input|A' => 'M',
        'values' => [
          'description' => [
            'columns' => [ static::musicianPublicNameSql() ],
            'divs' => [],
            'ifnull' => [ false, false ],
            'cast' => [ false ],
          ],
          'filters' => (!$projectMode
                        ? null
                        : static::musicianInProjectSql($this->projectId)),
          // 'having' => [
          //   'SUM(' . $joinTables[self::PROJECT_PAYMENTS_TABLE] . '.is_donation) > 0',
          // ],
          'data' => [
            'musician-id' => '$table.id',
            'projects' => 'JSON_ARRAYAGG(DISTINCT ' . $joinTables[self::PROJECT_PARTICIPANTS_TABLE] . '.project_id)',
          ],
        ],
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'user_id_slug', [
        'name' => $this->l->t('User Id'),
        'input' => 'RH',
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'uuid', [
        'name' => $this->l->t('User Id'),
        'input' => 'RH',
      ]);

    $displayControls = fn(string $id) => [
      'attributes' => function($op, $k, $row, $pme) use ($id) {
        $mailingDate = $row[$this->queryField('mailing_date')];
        if (empty($mailingDate)) {
          return [];
        }
        return [ 'readonly' => true, 'disabled' => true ];
      },
      'postfix' => function($op, $pos, $k, $row, $pme) use ($id) {
        $mailingDate = $row[$this->queryField('mailing_date')];
        if (empty($mailingDate)) {
          return '';
        }
        return '<input id="' . $id . '"
  type="checkbox"
  checked
  class="pme-input pme-input-lock lock-unlock locked-disabled"
/><label
    class="pme-input pme-input-lock lock-unlock"
    title="' . $this->toolTipsService['pme:input:lock:unlock'] . '"
    for="' . $id . '"></label>';
      },
    ];

    $this->makeJoinTableField(
      $opts['fdd'], self::COMPOSITE_PAYMENTS_TABLE, 'id',
      [
        'name'         => $this->l->t('Payment Id'),
        'name|ACFLP'   => $this->l->t('Payment'),
        'tab'          => [ 'id' => 'payment', ],
        'css'          => [ 'postfix' => [ 'composite-payment-id', 'allow-empty', ], ],
        'select|FL'       => 'T',
        'select|ACP' => 'D',
        'align'        => 'right',
        'input'        => 'M',
        'maxlen'       => 11,
        'default'      => null,
        'sort'         => true,
        'values|ACP' => [
          'description' => [
            'columns' => ($projectMode
                          ? [ static::musicianPublicNameSql($joinTables[self::MUSICIANS_TABLE]),
                              'id',
                              'subject', ]
                          : [ static::musicianPublicNameSql($joinTables[self::MUSICIANS_TABLE]),
                              'id',
                              'subject',
                              $joinTables[self::PROJECTS_TABLE] . '.name',
                          ]),
            'divs' => ' - ',
            'ifnull' => false,
            'cast' => false,
          ],
          'groups' => ($projectMode ? null : $joinTables[self::PROJECTS_TABLE] . '.name'),
          'orderby' => ($joinTables[self::PROJECTS_TABLE] . '.year DESC, '
                        . $joinTables[self::PROJECTS_TABLE] . '.name ASC, '
                        . static::musicianPublicNameSql($joinTables[self::MUSICIANS_TABLE]) . ' ASC'
          ),
          'filters' => [
            'AND' => [
              (!$projectMode ? '1' : '$table.project_id = ' . $this->projectId),
            ],
          ],
          // 'having' => [
          //   'SUM(' . $joinTables[self::PROJECT_PAYMENTS_TABLE] . '.is_donation) > 0',
          // ],
          'data' => [
            'payment-id' => '$table.id',
            'subject' => '$table.subject',
            'project-id' => $joinTables[self::PROJECTS_TABLE] . '.id',
            'musician-id' => $joinTables[self::MUSICIANS_TABLE] . '.id',
            'amount' => $this->paymentAmountSql($joinTables[self::PROJECT_PAYMENTS_TABLE]),
            'amount-waived' => $this->paymentAmountWaivedSql($joinTables[self::PROJECT_PAYMENTS_TABLE]),
            'status' => $this->paymentStatusSql($joinTables[self::PROJECT_PAYMENTS_TABLE]),
          ],
        ],
        'display|ACP' => $displayControls('lock-unlock-composite-payment-id'),
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::COMPOSITE_PAYMENTS_TABLE, 'subject',
      [
        'tab' => [ 'id' => 'payment', ],
        'name' => $this->l->t('Subject'),
        'css'  => [ 'postfix' => [ 'composite-payment-subject', 'squeeze-subsequent-lines', 'clip-long-text', ], ],
        'sql|LFVD' => 'REPLACE($join_col_fqn, \'; \', \'<br/>\')',
        'select' => 'T',
        'input' => 'R',
        'display' => [ 'popup' => 'data' ],
        'escape' => true,
        'sort' => true,
        'maxlen' => FinanceService::SEPA_PURPOSE_LENGTH,
        'size' => FinanceService::SEPA_PURPOSE_LENGTH,
        'textarea|ACP' => [
          'css' => 'constrained',
          'rows' => 4,
          'cols' => 35,
        ],
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::COMPOSITE_PAYMENTS_TABLE, 'date_of_receipt',
      Util::arrayMergeRecursive(
        $this->defaultFDD['date'],
        [
          'tab' => [ 'id' => 'payment' ],
          'name' => $this->l->t('Date of Receipt'),
          'input' => 'R',
          'align' => 'center',
        ]
      ),
    );

    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECT_PAYMENTS_TABLE, 'amount',
      Util::arrayMergeRecursive(
        $this->defaultFDD['money'],
        [
          'tab' => [ 'id' => 'payment', ],
          'css' => [ 'postfix' => [ 'project-payment', 'amount', ], ],
          'name' => $this->l->t('Amount'),
          'input' => 'R',
          'sql' => $this->paymentAmountSql(),
        ],
      ));

    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECT_PAYMENTS_TABLE, 'amount_waived',
      Util::arrayMergeRecursive(
        $this->defaultFDD['money'],
        [
          'tab' => [ 'id' => 'payment', ],
          'css' => [ 'postfix' => [ 'project-payment', 'amount-waived', ], ],
          'name' => $this->l->t('Amount Waived'),
          'input' => 'VSR',
          'sql' => $this->paymentAmountWaivedSql(),
          'values' => [
            'column' => 'amount',
          ],
          'tooltip' => $this->toolTipsService['page-renderer:donation-receipts:amount-waived'],
        ],
      ));

    /**
     * Sanity checks: things must not be too complicated. Either all donation
     * amounts are positive and all non-donation amounts are negative, or
     * something is fishy. If the composite payments contains sub-payments
     * with amount < 0 then is donation is a waving of reimbursement and then
     * the sum of all negative amount + the sum of all positive amounts must
     * sum up to 0.
     */
    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECT_PAYMENTS_TABLE, 'amount_check',
      Util::arrayMergeRecursive(
        [
          'tab' => [ 'id' => 'tab-all', ],
          'css' => [ 'postfix' => [ 'project-payment', 'status', ], ],
          'name' => $this->l->t('Status'),
          'input' => 'VSR',
          'select' => 'O',
          'align|LF' => 'center',
          'sort' => true,
          'default' => null,
          'sql' => $this->paymentStatusSql(),
          'values' => [
            'column' => 'is_donation', // Cheat. That one has only two values ;)
            'description' => PHPMyEdit::TRIVIAL_DESCRIPION,
          ],
          'values2|CAP' => [
            0 => $this->l->t('inconsistent donation'),
            1 => $this->l->t('consistency checks passed'),
          ], // empty label for simple checkbox
          'values2|LVDF' => [
            0 => '',
            1 => '&#10004;',
          ],
          'tooltip' => $this->toolTipsService['page-renderer:donation-receipts:amount-check'],
          'display' => [ 'popup' => 'tooltip' ],
        ],
      ));

    /* list(, $projectIdKey) = */ $this->makeJoinTableField(
      $opts['fdd'], self::PROJECTS_TABLE, 'id',
      [
        'name' => $this->l->t('Project'),
        'tab'  => [ 'id' => 'payment' ],
        'css' => [ 'postfix' => [ 'project-id', 'allow-empty', ], ],
        'sql' => '$join_table.name',
        'select|AFL' => 'D',
        'select|CVD' => 'T',
        'input|A' => 'M',
        'select' => 'D',
        'maxlen' => 20,
        'size' => 16,
        'input' => $projectMode ? 'HR' : 'R',
        'default' => ($projectMode ? $this->projectId : null),
        'values' => [
          'description' => [
            'columns' => [ '$table.name' ],
            'cast' => [ false ],
            'ifnull' => [ false ],
          ],
          // 'having' => [
          //   'SUM(' . $joinTables[self::PROJECT_PAYMENTS_TABLE] . '.is_donation) > 0',
          // ],
          'groups'      => 'year',
          'orderby'     => '$table.year DESC, $table.name ASC',
          'data'        => [ 'project-id' => '$table.id', ],
        ],
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECTS_TABLE, 'name',
      [
        'name'  => $this->l->t('Project Name'),
        'input' => 'VHR',
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECTS_TABLE, 'year',
      [
        'name'  => $this->l->t('Project Year'),
        'input' => 'VHR',
      ]);

    $opts['fdd']['supporting_document_id'] = [
      'name' => $this->l->t('Local Copy'),
      'tab' => [ 'id' => 'document' ],
      'input|A' => 'HR',
      'css'      => [ 'postfix' => [ 'local-copy', 'supporting-document', ], ],
      'options' => 'LFACDPV',
      'php|CP' => function($value, $action, $k, $row, $recordId, $pme) {

        if ($pme->hidden($k) || empty($row)) {
          return '';
        }

        $donationId = $row[$this->joinQueryField(self::COMPOSITE_PAYMENTS_TABLE, 'id')];
        $dateOfReceipt = $row[$this->joinQueryField(self::COMPOSITE_PAYMENTS_TABLE, 'date_of_receipt')];
        $musicianName = $row[$this->joinQueryField(self::MUSICIANS_TABLE, 'id')];
        $projectName = $row[$this->joinQueryField(self::PROJECTS_TABLE, 'name')];

        $fileName = $this->getLegacyDonationReceiptFileName(
          $donationId,
          $musicianName,
          $projectName,
        );

        $dir = $this->getDonationReceiptsPath();
        $year = substr($dateOfReceipt, 0, 4);
        $dir .= UserStorage::PATH_SEP . $year;

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

        $dateOfReceipt = $row[$this->joinQueryField(self::COMPOSITE_PAYMENTS_TABLE, 'date_of_receipt')];
        $year = substr($dateOfReceipt, 0, 4);
        $dir = $this->getTaxExemptionNoticesPath()
          . UserStorage::PATH_SEP . $year;

        try {
          $filesAppLink = $this->userStorage->getFilesAppLink($dir, true);
          $filesAppTarget = md5($filesAppLink);
          $filesAppLink = '<a href="' . $filesAppLink . '" target="'.$filesAppTarget.'"
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
          . '<a class="download-link ajax-download tooltip-auto inline-block clip-long-text"
   title="' . $this->toolTipsService['page-renderer:donation-receipts:supporting-document'] . '"
   href="' . $downloadLink . '">' . $file->getName() . '</a>
</div>';
      },
    ];

    $this->makeJoinTableField(
      $opts['fdd'], self::TAX_EXEMPTION_NOTICES_TABLE, 'id',
      [
        'tab'    => [ 'id' => 'document' ],
        'name'   => $this->l->t('Tax Exemption Notice'),
        'select|DV' => 'T',
        'select|ACFLP' => 'D',
        'align'        => 'right',
        'input'        => 'M',
        'maxlen'       => 11,
        'default'      => null,
        'sort'         => true,
        'values' => [
          'description' => [
            'columns' => [
              'tax_type',
              'tax_office',
              'assessment_period_start',
              'assessment_period_end',
            ],
            'divs' => ' - ',
            'ifnull' => false,
            'cast' => false,
          ],
        ],
        'display|ACP' => $displayControls('lock-unlock-tax-exemption-notice'),
      ]);

    $opts['fdd']['mailing_date'] = Util::arrayMergeRecursive(
      $this->defaultFDD['date'],
      [
        'tab' => [ 'id' => 'document' ],
        'name' => $this->l->t('Mailing Date'),
        'display|ACP' => $displayControls('lock-unlock-mailing-date'),
      ],
    );

    if ($this->showDisabled) {
      $opts['fdd']['deleted'] = array_merge(
        $this->defaultFDD['deleted'], [
          'tab'  => [ 'id' => 'tab-all' ],
          'name' => $this->l->t('Invalidated'),
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
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_INSERT][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeInsertSanitizeFields' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_INSERT][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeInsertDoInsertAll' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeUpdateSanitizeFields' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeUpdateDoUpdateAll' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_DELETE][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'beforeDeleteSimplyDoDelete' ];

    // $opts[PHPMyEdit::OPT_TRIGGERS]['*'][PHPMyEdit::TRIGGER_DATA][] = function(PHPMyEdit $pme, string $op, string $step, array &$row) {
    //   if (!$row[$this->joinQueryField(self::PROJECT_PAYMENTS_TABLE, 'amount_check'))) {
    //   }
    // };

    $opts = Util::arrayMergeRecursive($this->pmeOptions, $opts);

    if ($execute) {
      $this->execute($opts);
    } else {
      $this->pme->setOptions($opts);
    }
  }

  /**
   * Remap some join-table fields used to "pretty-print" to the real join-columns.
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
   */
  public function beforeInsertSanitizeFields(PHPMyEdit &$pme, string $op, string $step, array &$oldValues, array &$changed, array &$newValues):bool
  {
    $this->debugPrintValues($oldValues, $changed, $newValues, null, 'before');

    $remapFields = [
      $this->joinTableFieldName(self::COMPOSITE_PAYMENTS_TABLE, 'id') => 'donation_id',
      $this->joinTableFieldName(self::TAX_EXEMPTION_NOTICES_TABLE, 'id') => 'tax_exemption_notice_id',
    ];

    foreach ($remapFields as $source => $target) {
      $newValues[$target] = $newValues[$source];
      $changed[] = $target;
    }

    $this->debugPrintValues($oldValues, $changed, $newValues, null, 'after');

    return true;
  }

  /**
   * Remap some input values.
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
   */
  public function beforeUpdateSanitizeFields(PHPMyEdit &$pme, string $op, string $step, array &$oldValues, array &$changed, array &$newValues):bool
  {
    $this->debugPrintValues($oldValues, $changed, $newValues, null, 'before');

    if (empty($changed)) {
      // don't start manipulations if nothing has changed.
      return true;
    }

    $remapFields = [
      $this->joinTableFieldName(self::COMPOSITE_PAYMENTS_TABLE, 'id') => 'donation_id',
      $this->joinTableFieldName(self::TAX_EXEMPTION_NOTICES_TABLE, 'id') => 'tax_exemption_notice_id',
    ];

    foreach ($remapFields as $source => $target) {
      $newValues[$target] = $newValues[$source];
      $changed[] = $target;
    }

    $unsetTags = [
      'supporting_document_id',
    ];
    foreach ($unsetTags as $tag) {
      unset($newValues[$tag]);
      unset($oldValues[$tag]);
      Util::unsetValue($changed, $tag);
    }

    $this->debugPrintValues($oldValues, $changed, $newValues, null, 'after');

    return true;
  }

  /**
   * @param null|string $table
   *
   * @return string
   */
  protected function paymentAmountSql(?string $table = null):string
  {
    $amountColumn = $table === null ? '$join_col_fqn' : $table . '.amount';
    $table = $table ?? '$join_table';

    return 'SUM(IF(' . $table . '.is_donation, ' . $amountColumn . ', 0))';
  }

  /**
   * @param null|string $table
   *
   * @return string
   */
  protected function paymentAmountWaivedSql(?string $table = null):string
  {
    $amountColumn = $table === null ? '$join_col_fqn' : $table . '.amount';
    $table = $table ?? '$join_table';

    return 'SUM(IF(NOT ' . $table . '.is_donation, ' . $amountColumn . ', 0))';
  }

  /**
   * @param null|string $table
   *
   * @return string
   */
  protected function paymentStatusSql(?string $table = null):string
  {
    $amountColumn = $table === null ? '$join_col_fqn' : $table . '.amount';
    $table = $table ?? '$join_table';

    return '(SUM(COALESCE(' . $table . '.is_donation, 0)) > 0)'
      . ' AND '
      . '(SUM(IF(NOT ' . $table . '.is_donation, ' . $amountColumn . ', 0)) = SUM(IF(' . $amountColumn . ' < 0, ' . $amountColumn . ', 0)))'
      . ' AND '
      . '(SUM(IF(' . $table . '.is_donation, ' . $amountColumn . ', 0)) = SUM(IF(' . $amountColumn . ' > 0, ' . $amountColumn . ', 0)))'
      . ' AND '
      . '(SUM(IF(' . $table . '.is_donation AND ' . $amountColumn . ' < 0, 1, 0)) = 0)'
      . ' AND '
      . '(SUM(IF(NOT ' . $table . '.is_donation AND ' . $amountColumn . ' > 0, 1, 0)) = 0)'
      . ' AND '
      . '(SUM(IF(' . $amountColumn . ' < 0, ' . $amountColumn . ', 0)) = 0 OR (
SUM(IF(' . $amountColumn . ' < 0, ' . $amountColumn . ', 0))
+
SUM(IF(' . $amountColumn . ' > 0, ' . $amountColumn . ', 0))
) = 0)';
  }
}
