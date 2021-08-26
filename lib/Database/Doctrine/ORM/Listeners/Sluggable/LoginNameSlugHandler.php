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
use OCA\CAFEVDB\Wrapped\Gedmo\Sluggable\Util\Urlizer as Transliterator;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Service\ConfigService;

/**
 * Gedmo slug-handler which converts.
 *
 * @todo: use a more lightweight transliterator than pulling in all
 * this ConfigService stuff.
 */
class LoginNameSlugHandler implements SlugHandlerInterface
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

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

    $this->configService = \OC::$server->query(ConfigService::class);

    // disable transliteration, done in postSlugBuild()
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
    // nothing
  }

  /**
   * Callback on slug handlers right after the slug is built
   *
   * @param object $object
   * @param string $slug
   * @return void
   */
  public function postSlugBuild(SluggableAdapter $ea, array &$config, $object, &$slug)
  {
    $options = $config['handlers'][get_called_class()];
    $innerSeparator = $options['separator']?:'-';
    $outerSeparator = $config['separator']?:'.';

    // get the components
    $slugs = explode($outerSeparator, $slug);

    // prefer a set of components if all are non empty
    if (is_array($options['preferred'])) {
      $preferred = array_filter(array_intersect_key($slugs, array_flip($options['preferred'])));
      // use preferred fields if all are non empty
      if (count($preferred) == count($options['preferred'])) {
        $slugs = $preferred;
      }
    }

    // remove empty fields
    $slugs = array_filter($slugs);

    // transliterate them separately, again using $config['separator']
    $slugs = array_map(function($slugPart) use ($innerSeparator) {
      // use iconv for transliteration first place
      $slugPart = $this->transliterate($slugPart);

      // then pass down and replace other "inconvenient" characters
      return Transliterator::transliterate($slugPart, $innerSeparator);
    }, $slugs);

    // implode again using the outer separator
    $slug = implode($outerSeparator, $slugs);
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
    // nothing
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
    // not needed, using defaults for missing options
  }
}
