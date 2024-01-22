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

use InvalidArgumentException;

use OCA\CAFEVDB\Wrapped\Gedmo\Sluggable\Handler\SlugHandlerInterface;
use OCA\CAFEVDB\Wrapped\Doctrine\Persistence\Mapping\ClassMetadata;
use OCA\CAFEVDB\Wrapped\Gedmo\Sluggable\Mapping\Event\SluggableAdapter;
use OCA\CAFEVDB\Wrapped\Gedmo\Sluggable\SluggableListener;
use OCA\CAFEVDB\Wrapped\Gedmo\Sluggable\Util\Urlizer as Transliterator;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoSluggableListener;

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
   * @var GedmoSluggableListener
   */
  protected GedmoSluggableListener $sluggable;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(SluggableListener $sluggable)
  {
    if (!($sluggable instanceof GedmoSluggableListener)) {
      throw new InvalidArgumentException('Listener has to be ' . GedmoSluggableListener::class . ', but got a ' . get_class($sluggable));
    }
    $this->sluggable = $sluggable;

    $this->configService = $this->sluggable->getAppContainer()->get(ConfigService::class);

    // disable transliteration, done in postSlugBuild()
    $this->sluggable->setTransliterator(fn($slug) => $slug);
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function onChangeDecision(SluggableAdapter $eventAdapter, array &$config, $object, &$slug, &$needToChangeSlug, $otherSlugs)
  {
    // nothing
  }

  /** {@inheritdoc} */
  public function postSlugBuild(SluggableAdapter $eventAdapter, array &$config, $object, &$slug)
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

  /** {@inheritdoc} */
  public function onSlugCompletion(SluggableAdapter $eventAdapter, array &$config, $object, &$slug)
  {
    // nothing
  }

  /** {@inheritdoc} */
  public function handlesUrlization()
  {
    return true;
  }

  /** {@inheritdoc} */
  public static function validate(array $options, ClassMetadata $meta)
  {
    // not needed, using defaults for missing options
  }
}
