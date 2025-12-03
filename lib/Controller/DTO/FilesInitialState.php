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
 *
 * @phpcs:disable PEAR.Commenting.ClassComment.Missing
 * @phpcs:disable PEAR.Commenting.FunctionComment.Missing
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses
 * @phpcs:disable Squiz.Commenting.ClassComment.Missing
 * @phpcs:disable Squiz.Commenting.FunctionComment.Missing
 */

namespace OCA\CAFEVDB\Controller\DTO;

use Spatie\TypeScriptTransformer\Attributes as TSAttributes;

use OCA\CAFEVDB\Toolkit\DTO\AbstractDTO;

#[TSAttributes\InlineTypeScriptType]
class FilesInitialStateSharingFilesFolders extends AbstractDTO
{
  /** {@inheritdoc} */
  public function __construct(
    public readonly string $root,
    public readonly string $balances,
    public readonly string $donationReceipts,
    public readonly string $finance,
    public readonly string $invoices,
    public readonly string $projectBalances,
    public readonly string $projectManagement,
    public readonly string $templates,
  ) {
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
      root: $root,
      balances: $balances,
      donationReceipts: $donationReceipts,
      finance: $finance,
      invoices: $invoices,
      projectBalances: $projectBalances,
      projectManagement: $projectManagement,
      templates: $templates,
    );
  }
}

#[TSAttributes\InlineTypeScriptType]
class FilesInitialStateSharingFilesSubFolders extends AbstractDTO
{
  /** {@inheritdoc} */
  public function __construct(
    public readonly string $supportingDocuments,
    public readonly string $projectParticipants,
  ) {
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
      supportingDocuments: $supportingDocuments,
      projectParticipants: $projectParticipants,
    );
  }
}

#[TSAttributes\InlineTypeScriptType]
class FilesInitialStateSharingFiles extends AbstractDTO
{
  public readonly FilesInitialStateSharingFilesFolders $folders;
  public readonly FilesInitialStateSharingFilesSubFolders $subFolders;

  public function __construct(
    array|FilesInitialStateSharingFilesFolders $folders,
    array|FilesInitialStateSharingFilesSubFolders $subFolders,
  ) {
    $this->folders = is_array($folders) ? FilesInitialStateSharingFilesFolders::fromArray($folders) : $folders;
    $this->subFolders = is_array($subFolders) ? FilesInitialStateSharingFilesSubFolders::fromArray($subFolders) : $subFolders;
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
      folders: $folders,
      subFolders: $subFolders,
    );
  }
}

#[TSAttributes\InlineTypeScriptType]
class FilesInitialStateSharing extends AbstractDTO
{
  public readonly FilesInitialStateSharingFiles $files;

  public function __construct(
    array|FilesInitialStateSharingFiles $files,
  ) {
    $this->files = is_array($files) ? FilesInitialStateSharingFiles::fromArray($files) : $files;
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
      files: $files,
    );
  }
}

#[TSAttributes\InlineTypeScriptType]
class FilesInitialStatePersonal extends AbstractDTO
{
  /** {@inheritdoc} */
  public function __construct(
    public readonly string $userId,
    public readonly int $musicianId,
  ) {
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
      userId: $userId,
      musicianId: $musicianId,
    );
  }
}

#[TSAttributes\InlineTypeScriptType]
class FilesInitialStateContacts extends AbstractDTO
{
  /** {@inheritdoc} */
  public function __construct(
    // @todo Also generate type AddressBookInfo via DTO.
    #[TSAttributes\LiteralTypeScriptType('Record<number, AddressBookInfo>')]
    public readonly array $addressBooks,
  ) {
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
      addressBooks: $addressBooks,
    );
  }
}

/**
 * Intial state emitted for key "files". Consistency between class members
 * and config / enum keys is ensured by unit testing with the fromArray
 * method.
 */
class FilesInitialState extends AbstractDTO
{
  public readonly FilesInitialStateSharing $sharing;
  public readonly FilesInitialStatePersonal $personal;
  public readonly FilesInitialStateContacts $contacts;

  /** {@inheritdoc} */
  public function __construct(
    array|FilesInitialStateSharing $sharing,
    array|FilesInitialStatePersonal $personal,
    array|FilesInitialStateContacts $contacts,
    public readonly int $debugMode,
  ) {
    $this->sharing = is_array($sharing) ? FilesInitialStateSharing::fromArray($sharing) : $sharing;
    $this->personal = is_array($personal) ? FilesInitialStatePersonal::fromArray($personal) : $personal;
    $this->contacts = is_array($contacts) ? FilesInitialStateContacts::fromArray($contacts) : $contacts;
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
      sharing: $sharing,
      personal: $personal,
      contacts: $contacts,
      debugMode: $debugMode,
    );
  }
}
