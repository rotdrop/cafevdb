<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Service;

use Psr\Container\ContainerInterface;

use OCA\Redaxo\Service\RPC;

/**
 * Thin adapter around the RPC client of the Redaxo app. The Redaxo classes
 * are resolved lazily by name, so that this class can be mocked or stubbed
 * without the Redaxo app being installed.
 */
class WebPagesRPC
{
  public const ON_ERROR_THROW = 'throw';
  public const ON_ERROR_RETURN = 'return';

  private ?object $rpc = null;

  /** {@inheritdoc} */
  public function __construct(
    private ContainerInterface $appContainer,
  ) {
  }

  /** @return object The Redaxo RPC service. */
  private function rpc(): object
  {
    return $this->rpc ??= $this->appContainer->get(RPC::class);
  }

  /**
   * @param ?string $how
   *
   * @return string Currently active error handling policy.
   */
  public function errorReporting(?string $how = null): string
  {
    return $this->rpc()->errorReporting($how);
  }

  /**
   * @param mixed $articleId
   *
   * @param bool $editMode
   *
   * @return string
   */
  public function redaxoURL(mixed $articleId = null, bool $editMode = false): string
  {
    return $this->rpc()->redaxoURL($articleId, $editMode);
  }

  /**
   * @return bool
   */
  public function ping(): bool
  {
    return $this->rpc()->ping();
  }

  /**
   * @param int $parentId
   *
   * @param int $level
   *
   * @return null|array
   */
  public function getCategories(int $parentId = -1, int $level = 0): ?array
  {
    return $this->rpc()->getCategories($parentId, $level);
  }

  /**
   * @param bool $onlyActive
   *
   * @return null|array
   */
  public function getTemplates(bool $onlyActive = false): ?array
  {
    return $this->rpc()->getTemplates($onlyActive);
  }

  /**
   * @return null|array
   */
  public function getModules(): ?array
  {
    return $this->rpc()->getModules();
  }

  /**
   * @param int $articleId
   *
   * @param int $destCat
   *
   * @return bool
   */
  public function moveArticle(int $articleId, int $destCat): bool
  {
    return $this->rpc()->moveArticle($articleId, $destCat);
  }

  /**
   * @param int $articleId
   *
   * @param int $categoryId
   *
   * @return bool
   */
  public function deleteArticle(int $articleId, int $categoryId): bool
  {
    return $this->rpc()->deleteArticle($articleId, $categoryId);
  }

  /**
   * @param string $name
   *
   * @param int $categoryId
   *
   * @param int $templateId
   *
   * @param int $position
   *
   * @return null|array
   */
  public function addArticle(string $name, int $categoryId, int $templateId, int $position = 10000): ?array
  {
    return $this->rpc()->addArticle($name, $categoryId, $templateId, $position);
  }

  /**
   * @param int $articleId
   *
   * @param int $blockId
   *
   * @param int $sliceId
   *
   * @return bool
   */
  public function addArticleBlock(int $articleId, int $blockId, int $sliceId = -1): bool
  {
    return $this->rpc()->addArticleBlock($articleId, $blockId, $sliceId);
  }

  /**
   * @param int $articleId
   *
   * @param string $name
   *
   * @return bool
   */
  public function setArticleName(int $articleId, string $name): bool
  {
    return $this->rpc()->setArticleName($articleId, $name);
  }

  /**
   * @param string $nameRe
   *
   * @param int $categoryId
   *
   * @return null|array
   */
  public function articlesByName(string $nameRe, int $categoryId): ?array
  {
    return $this->rpc()->articlesByName($nameRe, $categoryId);
  }

  /**
   * @param string|array $idList
   *
   * @param int $categoryId
   *
   * @return null|array
   */
  public function articlesById(string|array $idList, int $categoryId): ?array
  {
    return $this->rpc()->articlesById($idList, $categoryId);
  }
}
