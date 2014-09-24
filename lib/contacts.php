<?php
/**Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
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

/**CamerataDB namespace to prevent name-collisions.
 */
namespace CAFEVDB
{

  /** Helper class for shared contacts. We could in principle tie
   * address-groups to projects, but for now this is just one flat
   * contact space. We only make sure the the default address book of
   * the share-holder user is shared with the orchestra group.
   */
  class Contacts
  {

    /**Find a matching addressbook by its displayname.
     *
     * @param[in] $displayName The display name.
     *
     * @param[in] $owner The owner of the address-book object.
     *
     * @param[in] $includeShared Include also shared addressbooks.
     *
     * @return The address-book object (row of the database, i.e. an array).
     */
    public static function addressBookByName($displayName, $owner = false, $includeShared = false)
    {
      if ($owner === false) {
        $owner = \OC_User::getUser();
      }

      $result = false;
      $app = new \OCA\Contacts\App($owner);
      $backend = $app->getBackend('local');
      $addressBooks = $backend->getAddressBooksForUser();
      if ($includeShared) {
        $backend = $app->getBackend('shared');
        $addressBooks = array_merge($addressBooks, $backend->getAddressBooksForUser());
      }  
      foreach ($addressBooks as $addressBook) {
        if ($addressBook['displayname'] == $displayName) {
          $result = $addressBook;
          break;
        }
      }
      return $result;
    }  

    /**Make sure there is a suitable shared addressbook with the given
     * name and/or id. Create one if necesary.
     *
     * @param[in] $displayName The display name. Mandatory.
     *
     * @param[in] $addressBookId The addressbook id. If set, the
     * corresponding address-book will be renamed to $displayName. If
     * unset, search for an address-book with $displayName as display
     * name or create one.
     *
     * @return The address-book id or false in case of error.
     */
    public static function checkSharedAddressBook($displayName, $addressBookId = false)
    {
      $sharegroup = Config::getAppValue('usergroup');
      $shareowner = Config::getValue('shareowner');

      // Make sure the dummy user owning all shared stuff is "alive"
      if (!ConfigCheck::checkShareOwner($shareowner)) {
        return false;
      }

      $app = new \OCA\Contacts\App($shareowner);
      $backend = $app->getBackend('local');

      if ($addressBookId === false) {
        // try to find one ...
        $sharedAddressBookInfo = self::addressBookByName($displayName, $shareowner);
      } else {
        try {
          // otherwise there should be one, in principle ...
          // the contacts up throws, and so we try and catch ...
          $sharedAddressBookInfo = $backend->getAddressBook($addressBookId);
        } catch (\Exception $e) {
          $sharedAddressBookInfo = false;
        }
        // the user interface primarily exhibits the name, so maybe we
        // have an orphan id, recheck with the display name
        if (!$sharedAddressBookInfo) {
          $sharedAddressBookInfo = self::addressBookByName($displayName, $shareowner);
        }
      }

      if (!$sharedAddressBookInfo) {
        try {
          // Well, then we create one ...
          $addressBookId = $backend->createAddressBook(array('displayname' => $displayName));
          $sharedAddressBookInfo = $backend->getAddressBook($addressBookId);
        } catch (\Exception $e) {
          $sharedAddressBookInfo = false;
        }
      } else {
        $addressBookId = $sharedAddressBookInfo['id'];
      }

      // Now there should be an addressbook, if not, bail out.
      if (!$sharedAddressBookInfo) {
        return false;
      }

      // Check that we can edit, simply set the item as shared    
      ConfigCheck::sudo($shareowner, function() use ($addressBookId, $sharegroup) {
          $result = ConfigCheck::groupShareObject($addressBookId, $sharegroup, 'addressbook');
          return $result;
        });

      // Finally check, that the display-name matches. Otherwise rename
      // the address-book
      if ($sharedAddressBookInfo['displayname'] != $displayName) {
        ConfigCheck::sudo($shareowner, function() use ($addressBookId, $displayName) {
            $result = $backend->updateAddressBook($addressBookId, array('displayname' => $displayName));
            return $result;
          });
      }

      return $addressBookId;
    }

    /**Fetch a list of contacts with email addresses for the current
     * user. The return value is a flat array of the form
     *
     * array('email@address.com' => 'John Doe');
     *
     * I.e.: the email address is the "unique" key, the data is the
     * real name (which may even be empty)
     * 
     * @todo We may want to add categories etc.
     */
    public static function emailContacts()
    {
      $result = array();

      $app = new \OCA\Contacts\App();
      $addressBooks = $app->getAddressBooksForUser();
      foreach ($addressBooks as $addressBook) {
        $contacts = $addressBook->getChildren();
        foreach ($contacts as $contact) {
          // $name = (string)$contact->N;
          $fullname = (string)$contact->FN;
          $emails = $contact->select('EMAIL');
          foreach ($emails as $email) {
            $result[(string)$email] = $fullname;
          }
        }
      }
      return $result;
    }    

    /** Split a comma separated address list into an array.
     */
    public static function parseAddrListToArray($list)
    {
      $t = str_getcsv($list);
      
      foreach($t as $k => $v) {
        $v = trim($v);
        if ($v == '') {
            unset($t[$k]);
            continue;
        }
        if (strpos($v,',') !== false) {
          $t[$k] = '"'.str_replace(' <','" <',$v);
        } else {
          $t[$k] = $v;
        }
      }
      
      $emails = array();
      foreach ($t as $addr) {
        if (strpos($addr, '<')) {
          preg_match('!(.*?)\s?<\s*(.*?)\s*>!', $addr, $matches);
          $emails[$matches[2]] = $matches[1];
        } else {
          $emails[$addr] = '';
        }
      }

      return $emails;
    }


  };

}

?>
