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

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Service\GeoCodingService;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Storage\DatabaseStorageUtil;

use OCA\CAFEVDB\Common\Util;

/** Table generator TaxExemptionNotice. */
class TaxExemptionNotices extends PMETableViewBase
{
  use FieldTraits\ParticipantFileFieldsTrait;

  const TEMPLATE = 'tax-exemption-notices';
  const TABLE = 'TaxExemptionNotices';

  protected $joinStructure = [
    self::TABLE => [
      'flags' => self::JOIN_MASTER,
      'entity' => Entities\TaxExemptionNotice::class,
    ],
  ];

  /**
   * @var array<int, string>
   * The translated names of the tax types.
   */
  private array $taxTypeNames;

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

    $taxTypes = array_values(Types\EnumTaxType::toArray());

    $this->taxTypeNames = [];
    foreach ($taxTypes as $tag) {
      $this->taxTypeNames[$tag] = $this->l->t($tag);
    }
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function shortTitle()
  {
    return $this->l->t('Tax Exemption Notices');
  }

  /** {@inheritdoc} */
  public function headerText()
  {
    $header = $this->shortTitle();

    return '<div class="' . $this->cssPrefix() . '-header-text">' . $header . '</div>';
  }

  /** {@inheritdoc} */
  public function render(bool $execute = true):void
  {
    $template        = $this->template;
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
      'template' => $template,
      'table' => $opts['tb'],
      'templateRenderer' => 'template:'.$template,
    ];

    // Name of field which is the unique key
    $opts['key'] = 'id';

    // Type of key field (int/real/string/date etc.)
    $opts['key_type'] = 'int';

    // Sorting field(s)
    $opts['sort_field'] = [ 'tax_type', 'assessment_period_start' ];

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

    $opts['fdd']['tax_type'] = [
      'name'     => $this->l->t('Type'),
      'css'      => [ 'postfix' => [ 'tax-type', ], ],
      'input'    => 'M',
      'select'   => 'D',
      'sort'     => $sort,
      'values2'  => $this->taxTypeNames,
    ];

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
      'css'      => [ 'postfix' => [ 'beneficiary-purpose', 'squeeze-subsequent-lines', ], ],
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
        'prefix' => '<div class="pme-cell-wrapper half-line-width"><div class="pme-cell-squeezer">',
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

        $taxType = $row[$this->queryField('tax_type')];
        $assessmentPeriodStart = $row[$this->queryField('assessment_period_start')];
        $assessmentPeriodEnd = $row[$this->queryField('assessment_period_end')];
        $fileName = $this->getLegacyTaxExemptionNoticeFileName(
          $taxType,
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

        $taxType = $row[$this->queryField('tax_type')];
        $assessmentPeriodStart = $row[$this->queryField('assessment_period_start')];
        $assessmentPeriodEnd = $row[$this->queryField('assessment_period_end')];

        /** @var Entities\DatabaseStorageFile $file */
        $file = $this->getDatabaseRepository(Entities\DatabaseStorageFile::class)->find($value);

        $downloadLink = $this->di(DatabaseStorageUtil::class)->getDownloadLink($file);

        $dir = $this->getFinanceFolderPath()
          . UserStorage::PATH_SEP . $this->getTaxAuthoritiesFolderName()
          . UserStorage::PATH_SEP . $this->getTaxExemptionNoticesFolderName();

        try {
          $filesAppLink = $this->userStorage->getFilesAppLink($dir, true);
          $filesAppTarget = md5($filesAppLink);
          $filesAppLink = '<a href="' . $filesAppLink . '" target="'.$filesAppTarget.'"
       title="'.$this->toolTipsService['page-renderer:upload:open-parent'].'"
       class="button operation open-parent tooltip-auto'.(empty($filesAppLink) ? ' disabled' : '').'"
       ></a>';
        } catch (\OCP\Files\NotFoundException $e) {
          $this->logInfo('No file found for ' . $dir);
          $filesAppLink = '';
        }
        return '<div class="flex-container">
'
          . $filesAppLink
          . '<a class="download-link ajax-download tooltip-auto inline-block clip-long-text"
   title="'.$this->toolTipsService['tax-exemption-notices:written-notice'].'"
   href="'.$downloadLink.'">' . $file->getName() . '</a>
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

    $opts = Util::arrayMergeRecursive($this->pmeOptions, $opts);

    if ($execute) {
      $this->execute($opts);
    } else {
      $this->pme->setOptions($opts);
    }
  }
}
