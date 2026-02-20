<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine
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
use OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoSluggableListener;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Service\Registration;

/**
 * Gedmo slug-handler which converts.
 *
 * @todo: use a more lightweight transliterator than pulling in all
 * this ConfigService stuff.
 */
class InvoiceNumberHandler implements SlugHandlerInterface
{
  use \OCA\CAFEVDB\Toolkit\Traits\UtilTrait;

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

    // disable transliteration, done in postSlugBuild()
    $this->sluggable->setTransliterator(fn($slug) => $slug);
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function onChangeDecision(SluggableAdapter $eventAdapter, array &$config, $object, &$slug, &$needToChangeSlug, $otherSlugs)
  {
    if (empty($object->getInvoiceNumber())) {
      $needToChangeSlug = true;
      return;
    }
    $entityManager = $eventAdapter->getObjectManager();
    $uow = $entityManager->getUnitOfWork();
    $changeSet = $eventAdapter->getObjectChangeSet($uow, $object);
    if (isset($changeSet['invoiceNumber'])) {
      // manually set to something, just keep it, the user has to reset the
      // invoice number to empty if the person wants to establish
      // auto-generation again.
      $needToChangeSlug = false;
      return;
    }
    foreach (Entities\Invoice::INVOICE_NUMBER_FIELDS as $field) {
      if (isset($changeSet[$field])) {
        $needToChangeSlug = true;
        return;
      }
    }
  }

  /** {@inheritdoc} */
  public function postSlugBuild(SluggableAdapter $eventAdapter, array &$config, $object, &$slug)
  {
    if (!$object instanceof Entities\Invoice) {
      throw new InvalidArgumentException('This handler can only act on "' . Entities\Invoice::class . '" but this object is an instance of "' . get_class($object) . '".');
    }

    /** @var Entities\Invoice $object */
    $slug = $object->generateInvoiceNumber();
    $slugs = explode(Entities\Invoice::INVOICE_NUMBER_SEPARATOR, $slug);

    $appLocale = $this->sluggable->getAppContainer()->get(Registration::APP_LOCALE);

    // transliterate them separately, again using $config['separator']
    $slugs = array_map(function($slugPart) use ($appLocale) {
      // use iconv for transliteration first place
      return $this->transliterate($slugPart, $appLocale);
    }, $slugs);

    // implode again using the outer separator
    $slug = implode(Entities\Invoice::INVOICE_NUMBER_SEPARATOR, $slugs);
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
