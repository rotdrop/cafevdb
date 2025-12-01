<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020-2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Settings;

use Spatie\TypeScriptTransformer\Attributes as TSAttributes;

use OCA\CAFEVDB\Service\DTO\FontFileNames;
use OCA\CAFEVDB\Toolkit\DTO\AbstractDTO;

/** Initialstate-DTO for admin-settings. */
class AdminInitialState extends AbstractDTO
{
  /** @var array<string, FontFileNames> */
  #[TSAttributes\LiteralTypeScriptType("Partial<typeof Service.FontService.FONT_FILE_NAMES>")]
  public readonly array $officeFonts;

  /** {@inheritdoc} */
  public function __construct(
    #[TSAttributes\LiteralTypeScriptType('typeof Service.AuthorizationService.GROUP_SUFFIX_LIST')]
    public readonly array $authorizationGroupSuffixes,
    public readonly string $cloudUserBackend,
    public readonly bool $haveCloudUserBackendConfig,
    public readonly bool $isAdmin,
    public readonly bool $isSubAdmin,
    /** @var array<string, FontFileNames|array> */
    array $officeFonts,
    public readonly string $officeFontsFolder,
    public readonly string $personalAppSettingsLink,
    public readonly string $sharedFolder,
    /** @var array<string> */
    public readonly array $userAndGroupBackends,
  ) {
    $this->officeFonts = array_map(
      fn(FontFileNames|array $value) => is_array($value) ? FontFileNames::fromArray($value) : $value,
      $officeFonts,
    );
  }

  /**
   * Initialize from the given array.
   *
   * @param array $data
   *
   * @return self
   */
  public static function fromArray(array $data): self
  {
    static::initKeys();
    extract(array_intersect_key($data, array_flip(static::$keys[__CLASS__])));
    return new self(
      authorizationGroupSuffixes: $authorizationGroupSuffixes,
      cloudUserBackend: $cloudUserBackend,
      haveCloudUserBackendConfig: $haveCloudUserBackendConfig,
      isAdmin: $isAdmin,
      isSubAdmin: $isSubAdmin,
      officeFonts: $officeFonts,
      officeFontsFolder: $officeFontsFolder,
      personalAppSettingsLink: $personalAppSettingsLink,
      sharedFolder: $sharedFolder,
      userAndGroupBackends: $userAndGroupBackends,
    );
  }
}
