<?php
/**
 * Copyright (c) 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * Wrapper to provide the hooks needed by the old OC calendar code.
 *
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

class OC_Calendar_App
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var \OCP\Calendar\IManager */
  private $calendarManager;

  /** @var \OCP\ITagManager */
  private $tagManager;

  /** @var string[] */
  private $categories;

  public function __construct(
    ConfigService $configService,
    \OCP\Calendar\IManager $calendarManager,
    \OCP\ITagManager $tagManager)
  {
    $this->configService = $configService;
    $this->calendarManager = $calendarManager;
    $this->tagManager = $tagManager;
  }

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
    $this->logError(print_r($categories, true));
    return $categories;
  }

  /**
   * @brief returns the default categories of ownCloud
   * @return (array) $categories
   */
  private function getDefaultCategories() {
    return [];
  }

  /**
   * @brief returns the vcategories object of the user
   * @return (object) $vcategories
   */
  private function getVCategories() {
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
   * scan events for categories.
   * @param $events VEVENTs to scan. null to check all events for the current user.
   */
  private function scanCategories($events = null)
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
      foreach($events as $event) {
        $this->logError("event loop");
        foreach($event['objects'] as $object) {
          foreach($object as $key => $categories) {
            if ($key != 'CATEGORIES') {
              continue;
            }
            $this->logError($key . " => " . is_array($categories[0][0]));
            while(is_array($categories)) {
              $this->logError(print_r($categories, true));
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

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
