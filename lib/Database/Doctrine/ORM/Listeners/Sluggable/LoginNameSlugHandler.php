<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 202-2022, 2025 Claus-Justus Heine
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

use Psr\Log\LoggerInterface;

use OCA\CAFEVDB\Wrapped\Gedmo\Sluggable\Handler\SlugHandlerInterface;
use OCA\CAFEVDB\Wrapped\Doctrine\Persistence\Mapping\ClassMetadata;
use OCA\CAFEVDB\Wrapped\Gedmo\Sluggable\Mapping\Event\SluggableAdapter;
use OCA\CAFEVDB\Wrapped\Gedmo\Sluggable\SluggableListener;

use OCA\CAFEVDB\Common\Transliterator;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoSluggableListener;

/**
 * Gedmo slug-handler which converts a persons name into an ASCII-only user-id.
 */
class LoginNameSlugHandler implements SlugHandlerInterface
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected SluggableListener $sluggable,
  ) {
    if (!($sluggable instanceof GedmoSluggableListener)) {
      throw new InvalidArgumentException('Listener has to be ' . GedmoSluggableListener::class . ', but got a ' . get_class($sluggable));
    }

    // disable transliteration, done in postSlugBuild()
    $this->sluggable->setTransliterator(fn($slug) => $slug);

    $this->logger = $this->sluggable->getAppContainer()->get(LoggerInterface::class);
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
    $fields = $config['fields'];
    $innerSeparator = $options['separator']?:'-';
    $outerSeparator = $config['separator']?:'.';

    $slugs = explode($outerSeparator, $slug);
    if (count($slugs) === count($fields)) {
    // get the components
      $slugs = array_filter(
        array_combine(
          $fields,
          $slugs,
        ),
      );
    }

    /** @var Transliterator $transliterator */
    $transliterator = $this->sluggable->getAppContainer()->get(Transliterator::class);

    $slug = $transliterator->generateUserIdSlug(
      names: $slugs,
      preferred: $options['preferred'],
      separator: $outerSeparator,
      wordSeparator: $innerSeparator,
    );
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
