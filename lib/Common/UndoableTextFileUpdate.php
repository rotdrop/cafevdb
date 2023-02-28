<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Common;

use DateTime;
use Exception;

use OCP\AppFramework\IAppContainer;
use OCP\Files\Node as FileSystemNode;
use OCP\Files\FileInfo;
use OCP\IL10N;
use Psr\Log\LoggerInterface as ILogger;
use OCP\IDateTimeFormatter;

use OCA\CAFEVDB\Constants;
use OCA\CAFEVDB\Storage\UserStorage;

/**
 * Update the content of the given text-file with the given content. The file
 * is created if it does not exist.
 *
 * @SuppressWarnings(PHPMD.ShortMethodName)
 */
class UndoableTextFileUpdate extends AbstractFileSystemUndoable
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  /** @var IDateTimeFormatter */
  protected $dateTimeFormatter;

  /** @var string|callable */
  protected $name;

  /**
   * @var string
   * The content to inject into the text-file.
   */
  protected $content;

  /**
   * @var string
   * Optional content which is ok to replace
   */
  protected $replacableContent;

  /** @var string */
  protected $renamedName;

  /** @var string */
  protected $oldContent;

  /** @var bool */
  protected $nothingToUndo;

  /** @var bool */
  protected $gracefully;

  /** @var bool */
  protected $mkdir;

  /**
   * @param string|callable $name
   *
   * @param null|string $content Content to place into the text-file.
   *
   * @param string|null $replacableContent Optional old known content from a previous
   * run which is then simply replaced.
   *
   * @param bool $gracefully Do not throw if folder already exists.
   *
   * @param bool $mkdir Whether or not to create missing parent
   * directories. However, even if set to \false a regular which is in the way
   * will be removed.
   */
  public function __construct(mixed $name, ?string $content, ?string $replacableContent = null, bool $gracefully = false, bool $mkdir = true)
  {
    $this->name = $name;
    $this->content = $content ?? '';
    $this->replacableContent = $replacableContent;
    $this->gracefully = $gracefully;
    $this->mkdir = $mkdir;
    $this->reset();
  }

  /** {@inheritdoc} */
  public function initialize(IAppContainer $appContainer):void
  {
    parent::initialize($appContainer);
    $this->dateTimeFormatter = $appContainer->get(IDateTimeFormatter::class);
  }

  /** {@inheritdoc} */
  public function do():void
  {
    if (is_callable($this->name)) {
      $this->name = call_user_func($this->name);
    }
    $this->name = self::normalizePath($this->name);

    $this->renamedName = null;
    // - $folderName does not exist
    //   -> just create the new folder, delete on undo
    // - $folderName exists and is a folder
    //   -> just keep, do not delete on undo
    // - $folderName exists and is a file
    //   -> delete, undelete on undo, or just rename
    $components = explode(UserStorage::PATH_SEP, $this->name);
    if (empty($components)) {
      throw new Exception('Cannot create the root-node.');
    }

    if (!$this->mkdir && empty($this->userStorage->get(dirname($this->name)))) {
      $this->nothingToUndo = true;
      return;
    }

    // Make sure the dirname($name) exists. This will also NOT be undone.
    $prefix = array_slice($components, 0, count($components) - 1);
    $this->userStorage->ensureFolderChain($prefix);

    $content = trim($this->content);
    $replacableContent = trim($this->replacableContent);

    /** @var FileSystemNode $existingReadMeNode */
    $existingNode = $this->userStorage->get($this->name);
    if (!empty($existingNode)) {
      if ($existingNode->getType() != FileInfo::TYPE_FILE) {
        // garbled, rename the beast
        $this->renamedName = $this->renamedName($this->name);
        $this->userStorage->rename($this->name, $this->renamedName);
      } else {
        // Plain file. In order not to generate excessivly many README.md
        // files we just add to the content of the old README.md
        $oldContent = $this->oldContent = $existingNode->getContent();

        $separatorPos = strpos($oldContent, Constants::OLD_CONTENT_SEPARATOR);
        $separatorPos === false && $separatorPos = null;
        $oldContentHead = trim(substr($oldContent, 0, $separatorPos));

        if ($content == $oldContentHead) {
          $content = null;
          $this->nothingToUndo = true;
          return;
        } elseif (!empty($replacableContent) && $oldContentHead == $replacableContent) {
          if ($separatorPos !== null) {
            $content .= substr($oldContent, $separatorPos);
          }
        } elseif (!empty($oldContent)) {
          // annotate the old content
          if (empty($content)) {
            $content = '';
          } else {
            $content .= Constants::OLD_CONTENT_SEPARATOR;
          }
          $now = new DateTime;
          $content .= $this->l->t('The old ``%1$s`` content on %2$s at %3$s was:', [
            basename($this->name),
            $this->dateTimeFormatter->formatDate($now),
            $this->dateTimeFormatter->formatTime($now),
          ]);
          $content .= "\n\n" . $this->oldContent;
        }
      }
    }

    if (!empty($content)) {
      try {
        $this->userStorage->putContent($this->name, $content);
      } catch (\Throwable $t) {
        if (!$this->gracefully) {
          throw $t;
        }
        $this->logException($t, 'Unable to store contents of readme-file');
      }
    } else {
      $this->userStorage->delete($this->name);
    }
  }

  /** {@inheritdoc} */
  public function undo():void
  {
    if ($this->nothingToUndo) {
      return;
    }
    if (empty($this->oldContent)) {
      $this->userStorage->delete($this->name);
      if (!empty($this->renamedName)) {
        $this->userStorage->rename($this->renamedName, $this->name);
      }
    } else {
      $this->userStorage->putContent($this->name, $this->oldContent);
    }
  }

  /** {@inheritdoc} */
  public function reset():void
  {
    $this->renamedName = null;
    $this->oldContent = null;
    $this->nothingToUndo = false;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
