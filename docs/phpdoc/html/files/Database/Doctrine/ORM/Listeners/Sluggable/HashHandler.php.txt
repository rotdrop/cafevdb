<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\Sluggable;

use OCA\CAFEVDB\Wrapped\Gedmo\Sluggable\Handler\SlugHandlerInterface;
use OCA\CAFEVDB\Wrapped\Doctrine\Persistence\Mapping\ClassMetadata;
use OCA\CAFEVDB\Wrapped\Gedmo\Sluggable\Mapping\Event\SluggableAdapter;
use OCA\CAFEVDB\Wrapped\Gedmo\Sluggable\SluggableListener;
use OCA\CAFEVDB\Wrapped\Gedmo\Exception\InvalidMappingException;

/**
 * Gedmo slug handler which simply computes a hash as slug. Currently
 * simplistic MD5.
 */
class HashHandler implements SlugHandlerInterface
{
  const HASH_LENGTH = 32;
  const OPTION_ALGORIGHM = 'algorithm';

  /**
   * @var string
   */
  private $hashAlgorithm;

  /**
   * @var SluggableListener
   */
  protected $sluggable;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(SluggableListener $sluggable)
  {
    $this->sluggable = $sluggable;
    $this->hashAlgorithm = 'md5';
    $this->sluggable->setTransliterator(fn($slug) => $slug);
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function onChangeDecision(SluggableAdapter $eventAdapter, array &$config, mixed $object, &$slug, &$needToChangeSlug, $otherSlugs)
  {
    $options = $config['handlers'][get_called_class()];
    $algorithm = !empty($options[self::OPTION_ALGORIGHM])
      ? $options[self::OPTION_ALGORIGHM]
      : $this->hashAlgorithm;
    $slug = substr(hash($algorithm, $otherSlugs['new']), 0, self::HASH_LENGTH);
  }

  /** {@inheritdoc} */
  public function postSlugBuild(SluggableAdapter $eventAdapter, array &$config, $object, &$slug)
  {
  }

  /** {@inheritdoc} */
  public function onSlugCompletion(SluggableAdapter $eventAdapter, array &$config, $object, &$slug)
  {
    return;
  }

  /** {@inheritdoc} */
  public function handlesUrlization()
  {
    return true;
  }

  /** {@inheritdoc} */
  public static function validate(array $options, ClassMetadata $meta)
  {
  }
}
