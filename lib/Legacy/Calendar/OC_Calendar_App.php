<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2023 Claus-Justus Heine
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

/********************************************************************
 *
 * Compat Layer
 *
 * - comment everyting not needed
 * - make it non-static
 * - inject our general config stuff
 */
namespace OCA\CAFEVDB\Legacy\Calendar;

use OCA\CAFEVDB\Service\ConfigService;

// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps

/**
 * Wrapper to provide the hooks needed by the old OC calendar code.
 *
 * @SuppressWarnings(PHPMD.CamelCaseClassName)
 */
class OC_Calendar_App
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var \OCP\Calendar\IManager */
  private $calendarManager;

  /** @var \OCP\ITagManager */
  private $tagManager;

  /** @var string[] */
  private $categories;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ConfigService $configService,
    \OCP\Calendar\IManager $calendarManager,
    \OCP\ITagManager $tagManager,
  ) {
    $this->configService = $configService;
    $this->calendarManager = $calendarManager;
    $this->tagManager = $tagManager;
  }
  // phpcs:enable

  /**
   * @brief returns the categories of the vcategories object
   * @return (array) $categories
   */
  public function getCategoryOptions()
  {
    $getNames = function($tag) {
      return $tag['name'];
    };
    $categories = $this->getVCategories()->getTags();
    $categories = array_map($getNames, $categories);
    $this->logDebug(__METHOD__.": ".print_r($categories, true));
    return $categories;
  }

  /**
   * @brief returns the default categories of ownCloud
   * @return (array) $categories
   */
  private function getDefaultCategories()
  {
    return [];
  }

  /**
   * @brief returns the vcategories object of the user
   * @return (object) $vcategories
   */
  private function getVCategories()
  {
    if (is_null($this->categories)) {
      $categories = $this->tagManager->load('event');
      if ($categories->isEmpty('event')) {
        $this->scanCategories();
      }
      $this->categories = $this->tagManager->load('event', $this->getDefaultCategories());
    }
    return $this->categories;
  }

  /**
   * Scan events for categories.
   *
   * @param mixed $events VEVENTs to scan. null to check all events for the current user.
   *
   * @return void
   */
  private function scanCategories(mixed $events = null):void
  {
    if (is_null($events)) {
      $events = $this->calendarManager->search('');
    }
    if (is_array($events) && count($events) > 0) {
      $vcategories = $this->tagManager->load('event');
      $getName = function($tag) {
        return $tag['name'];
      };
      $tags = array_map($getName, $vcategories->getTags());
      $vcategories->delete($tags);
      foreach ($events as $event) {
        $this->logDebug(__METHOD__.": event loop");
        foreach ($event['objects'] as $object) {
          foreach ($object as $key => $categories) {
            if ($key != 'CATEGORIES') {
              continue;
            }
            $this->logDebug(__METHOD__.": ".$key . " => " . is_array($categories[0][0]));
            while (is_array($categories)) {
              $this->logDebug(__METHOD__.": ".print_r($categories, true));
              $categories = $categories[0];
            }
            if (!empty($categories)) {
              $categories = explode(',', $categories);
              $vcategories->addMultiple($categories, true, $event['id']);
            }
          }
        }
      }
    }
  }
}
