<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022-2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Controller\DTO;

/**
 * Intial state emitted for key CAFEVDB.
 */
class CAFEVDBInitialState extends \OCA\CAFEVDB\Toolkit\DTO\AbstractDTO
{
  /** {@inheritdoc} */
  public function __construct(
    public readonly string $appName,
    public readonly string $orchestra,
    public readonly string $orchestraLogo,
    public readonly bool $toolTipsEnabled,
    public readonly string $wysiwygEditor,
    public readonly string $language,
    public readonly string $cloudLanguage,
    public readonly string $locale,
    // app-locale
    public readonly string $currencySymbol,
    public readonly string $currencyCode,
    public readonly string $appLocale,
    //
    public readonly string $serverRoot,
    public readonly bool $expertMode,
    public readonly bool $financeMode,
    public readonly int $debugMode,
    public readonly string $debugQuerySqlFilter,
    public readonly bool $restoreHistory,
    public readonly int $userPermissions,
    public readonly bool $isGroupAdmin,
    public readonly string $sharedFolder,
    public readonly string $projectsFolder,
    public readonly string $wikiNameSpace,
    public readonly int $uploadMaxFileSize,
  ) {
  }

  /**
   * Initialize from the given array.
   *
   * @param array $data
   *
   * @return self
   *
   * @SuppressWarnings(PHPMD.UndefinedVariable)
   * @SuppressWarnings(PHPMD.UnusedLocalVariable)
   */
  public static function fromArray(array $data): self
  {
    static::initKeys();
    extract(array_intersect_key($data, array_flip(static::$keys[__CLASS__])));
    return new self(
      appLocale: $appLocale,
      appName: $appName,
      cloudLanguage: $cloudLanguage,
      currencyCode: $currencyCode,
      currencySymbol: $currencySymbol,
      debugMode: $debugMode,
      debugQuerySqlFilter: $debugQuerySqlFilter,
      expertMode: $expertMode,
      financeMode: $financeMode,
      isGroupAdmin: $isGroupAdmin,
      language: $language,
      locale: $locale,
      orchestra: $orchestra,
      orchestraLogo: $orchestraLogo,
      projectsFolder: $projectsFolder,
      restoreHistory: $restoreHistory,
      serverRoot: $serverRoot,
      sharedFolder: $sharedFolder,
      toolTipsEnabled: $toolTipsEnabled,
      uploadMaxFileSize: $uploadMaxFileSize,
      userPermissions: $userPermissions,
      wikiNameSpace: $wikiNameSpace,
      wysiwygEditor: $wysiwygEditor,
    );
  }
}
