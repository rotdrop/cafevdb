<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2022, 2024, 2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB\PageRenderer\PME;

use OCP\IURLGenerator;

use OCA\CAFEVDB\Controller\EnumPersonalSettingsKey;
use OCA\CAFEVDB\Database\Legacy\PME\DefaultOptions;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Settings\ConfigConstants;

/** Default Legacy PME options. */
class Config extends DefaultOptions
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** {@inheritdoc} */
  public function __construct(
    protected ConfigService $configService,
    ToolTipsService $toolTipsService,
    IURLGenerator $urlGenerator,
  ) {
    $this->l = $this->l10n();

    $debugMode = $this->getUserValue(EnumPersonalSettingsKey::DEBUG_MODE, 0);
    $debugMode = filter_var($debugMode, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) || 0;
    $deselectInvisibleMiscRecs = $this->getUserValue(EnumPersonalSettingsKey::DESELECT_INVISIBLE_MISC_RECS, false);
    $deselectInvisibleMiscRecs = filter_var($deselectInvisibleMiscRecs, FILTER_VALIDATE_BOOLEAN);

    $options = [
      'language' => locale_get_primary_language($this->l10n()->getLanguageCode()),
      'url' => [
        'images' => $urlGenerator->imagePath($this->appName(), ''),
      ],
      'page_name' => $urlGenerator->linkToRoute($this->appName() . '.vueApp.index'),
      'tooltips' => $toolTipsService,
      'inc' => $this->getUserValue(EnumPersonalSettingsKey::PAGE_ROWS_DEFAULT, 20),
      'debug' => 0 != ($debugMode & ConfigConstants::DEBUG_QUERY),
      'misc' => [
        'css' => [ 'minor' => 'email tooltip-right' ],
        'deselect_invisible' => $deselectInvisibleMiscRecs,
      ],
      'labels' => [ 'Misc' => $this->l->t('Em@il') ],
    ];
    parent::__construct($options);
  }
}
