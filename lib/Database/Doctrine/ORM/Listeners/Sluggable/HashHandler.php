<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library se Doctrine\ORM\Tools\Setup;is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
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

  /**
   * Construct the slug handler
   */
  public function __construct(SluggableListener $sluggable)
  {
    $this->sluggable = $sluggable;
    $this->hashAlgorithm = 'md5';
    $this->sluggable->setTransliterator(function($slug) { return $slug; });
  }

  /**
   * Callback on slug handlers before the decision
   * is made whether or not the slug needs to be
   * recalculated
   *
   * @param object $object
   * @param string $slug
   * @param bool   $needToChangeSlug
   *
   * @return void
   */
  public function onChangeDecision(SluggableAdapter $ea, array &$config, $object, &$slug, &$needToChangeSlug, $otherSlugs)
  {
    $options = $config['handlers'][get_called_class()];
    $algorithm = !empty($options[self::OPTION_ALGORIGHM])
      ? $options[self::OPTION_ALGORIGHM]
      : $this->hashAlgorithm;
    $slug = substr(hash($algorithm, $otherSlugs['new']), 0, self::HASH_LENGTH);
  }

  /**
   * Callback on slug handlers right after the slug is built
   *
   * @param object $object
   * @param string $slug
   *
   * @return void
   */
  public function postSlugBuild(SluggableAdapter $ea, array &$config, $object, &$slug)
  {
  }

  /**
   * Callback for slug handlers on slug completion
   *
   * @param object $object
   * @param string $slug
   *
   * @return void
   */
  public function onSlugCompletion(SluggableAdapter $ea, array &$config, $object, &$slug)
  {
    return;
  }

  /**
   * @return bool whether or not this handler has already urlized the slug
   */
  public function handlesUrlization()
  {
    return true;
  }

  /**
   * Validate handler options
   */
  public static function validate(array $options, ClassMetadata $meta)
  {
  }
}
