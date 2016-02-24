<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016 Claus-Justus Heine <himself@claus-justus-heine.de>
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

  class AddressbookBackend extends \OCA\Contacts\Backend\AbstractBackend
  {
    const NAME = 'cafevdb';
    const MAIN_ADDRESS_BOOK = 'musicians';
    const MAIN_ADDRESS_BOOK_ID = 'musicians';
    const MAIN_CONTACTS_TABLE = 'Musiker';

    /**
     * The name of the backend.
     */
    public $name = self::NAME;

    /**
     * The name of the pseudo-user owning all resources shared by the
     * orchestra group.
     */
    private $shareOwner;

    /**
     * The name of the user-group for the administrative board of the
     * orchestra.
     */
    private $shareGroup;

    /**Register us as backend with the contacts app. */
    static public function register()
    {
      \OCA\Contacts\App::$backendClasses[self::NAME] = 'CAFEVDB\AddressbookBackend';
    }

    /**Generate an addressbook-id from which the numeric project-id can be recovered*/
    static private function addressBookId($projectName, $projectId)
    {
      return $projectId;
    }

    /**Generate a ASCII only URI for the given addressbook name.*/
    static private function makeURI($name)
    {
      return strtolower(self::NAME.'_'.Util::translitToASCII($name));
    }

    /**Recover the project id*/
    static private function projectId($addressBookId)
    {
      return $addressBookId;
    }

    /**Recover the translit project name*/
    static private function projectName($addressBookId)
    {
      $projectId = self::projectId($addressBookId);
      return Projects::fetchName($projectId);
    }

    /**Construct a globally unique id, the JS-code of the contacts-id
     * unfortunately somehow assumes globally unique id, i.e. it does
     * not use the parent (address-book) id.
     */
    static private function contactId($addressBookId, $uuid)
    {
      return $addressBookId.';'.$uuid;
    }

    /**Extract from ADB_ID;UUID the ADB and UUID part, dual to
     * self::contactId().
     *
     * @return array('addressbook' => ADB_ID, 'uuid' => UUID)
     */
    static private function parseContactId($contactId)
    {
      $split = Util::explode(';', $contactId);
      if (count($split) != 2) {
        return null;
      }
      return array('addressbook' => $split[0],
                   'uuid' => $split[1]);
    }

    /**
     * Flag indicating whether the current user is a member of the
     * orchestra user-group.
     */
    private $orchestraUser;

    public function __construct($userId = null)
    {
      parent::__construct($userId);
      Config::init();
      $this->userid = $userId ? $userId : \OCP\User::getUser();
      $this->shareGroup = Config::getAppValue('usergroup');
      $this->shareOwner = Config::getValue('shareowner');
      $this->orchestraUser = \OC_Group::inGroup($this->userid, $this->shareGroup);

      \OCP\Util::writeLog(Config::APP_NAME,
                          __METHOD__.': '.
                          $this->userid . '@' . $this->shareGroup .
                          ' access ' . ($this->accessAllowed() ? 'granted' : 'denied') .
                          ', orchestra user ' . $this->shareOwner,
                          \OCP\Util::DEBUG);
    }

    /**
     * @brief Check whether the address data-base may be accessed.
     * @return boolean
     */
    private function accessAllowed()
    {
      return $this->orchestraUser;
    }

    /**Get all addressbooks for the given user. Regardless of the
     * "read-the-source-code" documentation of the base-class
     * OCA::Contacts::Backend::AbstractBackend we need some more
     * fields. Specifically, the local "database" backend provides the
     * following fields:
     *
     * ['id', 'diaplayname', 'description',
     *  'lastmodified',
     *  'owner',
     *  'uri']
     *
     * from its database table, and additionally
     *
     * ['permissions']
     *
     * In principle, 'backend' need only be set in for
     * getAddressBook(), but we simply set it as well and redirect
     * from this meta-function to getAddressBook($adbId) in order to
     * maintain consistency more easily.
     *
     */
    public function getAddressBooksForUser(array $options = array())
    {
      if (!$this->accessAllowed()) {
        return array(); // nothing available for this user
      }

      // For now just play around, later we will provide one address-book for each project.
      $adb = $this->__getAddressBook(self::MAIN_ADDRESS_BOOK_ID, $options);
      $addressBooks = array($adb['id'] => $adb);

      //$projects[$line['Id']] = array('Name' => $line['Name'], 'Jahr' => $line['Jahr']);
      $projects = Projects::fetchProjects();

      foreach ($projects as $projectId => $projectName) {
        $options = array_merge($options,
                               array('projectid' => $projectId, 'projectname' => $projectName));
        $adb = $this->__getAddressBook('', $options);
        $addressBooks[$adb['id']] = $adb;
      }

      \OCP\Util::writeLog(Config::APP_NAME,
                          __METHOD__.': '.
                          'Adbs: ' . print_r($addressBooks, true), \OCP\Util::DEBUG);

      return $addressBooks;
    }

    /**
     * @copydoc getAddressBooksForUser()
     */
    public function getAddressBook($addressBookId, array $options = array())
    {
      if (!$this->accessAllowed()) {
        return null;
      }
      return $this->__getAddressBook($addressBookId, $options);
    }

    /**Like getAddressBook(), but used only internally, disabling
     * multiple redundant access checks.
     */
    private function __getAddressBook($addressBookId, array $options = array(), $handle = false)
    {
      if ((string)$addressBookId === (string)self::MAIN_ADDRESS_BOOK_ID) {
        \OCP\Util::writeLog(Config::APP_NAME,
                            __METHOD__.': '. 'Called for global address book',
                            \OCP\Util::DEBUG);
        return array(
          'backend' => $this->name,
          'id' => self::MAIN_ADDRESS_BOOK_ID,
          'displayname' => L::t(self::MAIN_ADDRESS_BOOK),
          'description' => L::t('CAFeV DB orchestra address-book with all musicians.'),
          'lastmodified' =>  $this->lastModifiedAddressbook($addressBookId),
          'owner' => $this->userid, //  $this->shareOwner,
          'uri' => self::makeURI(self::MAIN_ADDRESS_BOOK),
          'permissions' => \OCP\PERMISSION_ALL, // \OCP\PERMISSION_READ|\OCP\PERMISSION_UPDATE,
          );
      } else {
        if (isset($options['projectid']) && isset($options['projectname'])) {
          $projectId = $options['projectid'];
          $projectName = $options['projectname'];
          $addressBookId = self::addressBookId($projectName, $projectId);
        } else {
          $projectId = self::projectId($addressBookId);
          $projectName = Projects::fetchName($projectId, $handle);
          if (empty($projectName) ||
              self::addressBookId($projectName, $projectId) !== $addressBookId) {
            \OCP\Util::writeLog(Config::APP_NAME,
                                __METHOD__.': '.
                                'No address book for id ' .
                                $addressBookId . ':' .
                                $projectName . ':' .
                                $projectId,
                                \OCP\Util::DEBUG);

            return null;
          }
        }

        \OCP\Util::writeLog(Config::APP_NAME,
                            __METHOD__.': '. 'Called for project ' .
                            $projectName . '@' . $projectId . ':' . $addressBookId,
                            \OCP\Util::DEBUG);

        return array(
          'backend' => $this->name,
          'id' => $addressBookId,
          'displayname' => $projectName,
          'description' => L::t('CAFeV DB project address-book with all participants for %s',
                                array($projectName)),
          'lastmodified' => $this->lastModifiedAddressbook($addressBookId),
          'owner' => $this->userid, // $this->shareOwner,
          'uri' => self::makeURI($projectName),
          'permissions' => \OCP\PERMISSION_ALL, // \OCP\PERMISSION_READ|\OCP\PERMISSION_UPDATE,
          );
      }
    }

    /**
     * Returns the number of contacts in a specific address book.
     *
     * @param string $addressBookId
     * @return null|integer
     */
    public function numContacts($addressBookId) {
      if (!$this->accessAllowed()) {
        \OCP\Util::writeLog(Config::APP_NAME, __METHOD__.': Access Denied', \OCP\Util::DEBUG);
        return 0;
      }

      if ((string)$addressBookId === (string)self::MAIN_ADDRESS_BOOK_ID) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
        $numRows = mySQL::queryNumRows("FROM `Musiker` WHERE 1", $handle);
        mySQL::close($handle);

        \OCP\Util::writeLog(Config::APP_NAME, __METHOD__.': '.$numRows, \OCP\Util::DEBUG);

        return $numRows;
      } else {
        $projectId = self::projectId($addressBookId);
        $projectName = Projects::fetchName($projectId);
        if ($empty($projectName) ||
            self::addressBookId($projectName, $projectId) !== $addressBookId) {

          \OCP\Util::writeLog(Config::APP_NAME, __METHOD__.': address book not found.', \OCP\Util::DEBUG);

        return 0;
        }

        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
        $numRows = mySQL::queryNumRows("FROM `".$projectName."View` WHERE 1", $handle);
        mySQL::close($handle);

        \OCP\Util::writeLog(Config::APP_NAME, __METHOD__.': '.$numRows, \OCP\Util::DEBUG);

        return $numRows;
      }

      return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getContacts($addressBookId, array $options = array())
    {
      if (!$this->accessAllowed()) {
        return array();
      }

      $myOptions = array('limit' => PHP_INT_MAX,
                         'offset' => 0,
                         'omitdata' => false);
      foreach ($options as $key => $value) {
        if ($value) {
          $myOptions[$key] = $value;
        }
      }
      $options = $myOptions;

      \OCP\Util::writeLog(Config::APP_NAME,
                          __METHOD__.': '.($options['omitdata'] ? 'without data' : 'with data'),
                          \OCP\Util::DEBUG);

      if ((string)$addressBookId === (string)self::MAIN_ADDRESS_BOOK_ID) {
        return $this->getMusiciansContacts($options);
      } else {
        return $this->getProjectContacts($addressBookId, $options);
      }
    }

    private function getMusiciansContacts(array $options = array())
    {
      \OCP\Util::writeLog(Config::APP_NAME, __METHOD__, \OCP\Util::DEBUG);

      $addressBookId = self::MAIN_ADDRESS_BOOK_ID;

      $contacts = array();

      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);

      $table = self::MAIN_CONTACTS_TABLE;

      if ($options['omitdata']) {
        $query = "SELECT
 `UUID` AS `id`, CONCAT(`UUID`, '.vcf') AS `uri`, `Aktualisiert` AS `lastmodified`,
 '".$addressBookId."' as `parent`, CONCAT(`Vorname`, ' ', `Name`) AS `displayname`
 FROM `".$table."` WHERE 1 LIMIT ".$options['offset'].", ".$options['limit'];
        $result = mySQL::query($query, $handle);
        while ($row = mySQL::fetch($result)) {
          $row['permissions'] = \OCP\PERMISSION_ALL;
          $contacts[] = $row;
        }
      } else {
        $query = "SELECT `".$table."`.*,
 GROUP_CONCAT(DISTINCT `Projects`.`Name` ORDER BY `Projects`.`Name` ASC SEPARATOR ',') AS `Projects`,
 CONCAT('data:',`ImageData`.`MimeType`,';base64,',`ImageData`.`Data`) AS `Portrait`
 FROM `".$table."`
 LEFT JOIN
 `ImageData`
 ON `".$table."`.`Id` = `ImageData`.`ItemId` AND `ImageData`.`ItemTable` = 'Musiker'
 LEFT JOIN
 `Besetzungen` AS `ProjectsInstrumentation`
 ON `".$table."`.`Id` = `ProjectsInstrumentation`.`MusikerId`
 LEFT JOIN
 `Projekte` AS `Projects`
 ON `ProjectsInstrumentation`.`ProjektId` = `Projects`.`Id`
 WHERE 1
 GROUP BY `".$table."`.`Id`
 LIMIT ".$options['offset'].", ".$options['limit'];

        //throw new \Exception($query);
        /*
        \OCP\Util::writeLog(Config::APP_NAME,
                            __METHOD__.
                            ': query: '.
                            $query,
                            \OCP\Util::ERROR);
        */

        $result = mySQL::query($query, $handle);
        while ($row = mySQL::fetch($result)) {
          $vCard = VCard::export($row);
          $contactId = self::contactId($addressBookId, $row['UUID']);
          $contacts[] = array(
            'id' => $contactId,
            'uri' => $contactId.'.vcf',
            'lastmodified' => strtotime($row['Aktualisiert']),
            'parent' => $addressBookId,
            'displayname' => $row['Vorname'].' '.$row['Name'],
            'vcard' => $vCard,
            //'carddata' => $vCard->serialize(),
            'permissions' => \OCP\PERMISSION_ALL
            );
          /* \OCP\Util::writeLog(Config::APP_NAME, */
          /*                     __METHOD__. */
          /*                     ': vCard: '. */
          /*                     $vCard->serialize(), */
          /*                     \OCP\Util::ERROR); */
        }

      }

      mySQL::close($handle);

      return $contacts;
    }

    private function getProjectContacts($addressBookId, array $options = array())
    {
      $start = microtime(true);
      $projectId = self::projectId($addressBookId);
      $projectName = Projects::fetchName($projectId);
      if (empty($projectName) ||
          self::addressBookId($projectName, $projectId) !== $addressBookId) {
        return array();
      }

      $contacts = array();

      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);

      $mainTable = $projectName."View";

      // a project view may contain musicians multiple time (one
      // musician may play more than one instrument in a given
      // project. The GROUP BY clause eliminates duplicates.

      if ($options['omitdata']) {
        $query = "SELECT
 CONCAT('".$addressBookId.";',`UUID`) AS `id`, CONCAT('".$addressBookId.";',`UUID`, '.vcf') AS `uri`, `Aktualisiert` AS `lastmodified`,
 '".$addressBookId."' as `parent`, CONCAT(`Vorname`, ' ', `Name`) AS `displayname`
 FROM `".$mainTable."`
 WHERE 1
 GROUP BY `".$mainTable."`.`MusikerId`
 LIMIT ".$options['offset'].", ".$options['limit'];
        $result = mySQL::query($query, $handle);
        while ($row = mySQL::fetch($result)) {
          $row['permissions'] = \OCP\PERMISSION_ALL;
          $contacts[] = $row;
        }
      } else {
        $query = "SELECT * FROM `".$mainTable."`
 WHERE 1
 GROUP BY `".$mainTable."`.`MusikerId`
 LIMIT ".$options['offset'].", ".$options['limit'];

        //throw new \Exception($query);

        $result = mySQL::query($query, $handle);
        while ($row = mySQL::fetch($result)) {
          $vCard = VCard::export($row);
          $contactId = self::contactId($addressBookId, $row['UUID']);
          $contacts[] = array(
            'id' => $contactId,
            'uri' => $contactId.'.vcf',
            'lastmodified' => strtotime($row['Aktualisiert']),
            'parent' => $addressBookId,
            'displayname' => $row['Vorname'].' '.$row['Name'],
            'vcard' => $vCard,
            //'carddata' => $data, //vCard->serialize(),
            'permissions' => \OCP\PERMISSION_ALL
            );
          /* \OCP\Util::writeLog(Config::APP_NAME, */
          /*                     __METHOD__. */
          /*                     ': vCard: '. */
          /*                     $vCard->serialize(), */
          /*                     \OCP\Util::ERROR); */
        }

      }

      mySQL::close($handle);

      $elapsed = microtime(true) - $start;

      \OCP\Util::writeLog(Config::APP_NAME,
                          __METHOD__.': elapsed: '.$elapsed,
                          \OCP\Util::DEBUG);

      return $contacts;
    }

    /**
     * Returns a specific contact.
     *
     * NOTE: The contact $id can be an array containing either
     * 'id' or 'uri' to be able to play seamlessly with the
     * CardDAV backend.
     *
     * NOTE: $addressbookid isn't always used in the query, so there's no access control.
     * 	This is because the groups backend - \\OCP\\Tags - doesn't know about parent collections
     * 	only object IDs. Hence a hack is made with an optional 'noCollection'.
     *
     * @param string $addressBookId
     * @param string|array $id Contact ID
     * @param array $options Optional (backend specific options)
     * @return array|null
     */
    public function getContact($addressBookId, $id, array $options = array())
    {
      if (!$this->accessAllowed()) {
        return null;
      }

      if (is_array($id)) {
        if (isset($id['id'])) {
          \OCP\Util::writeLog(Config::APP_NAME,
                              __METHOD__.': '. 'Called for id '.$id['id'],
                              \OCP\Util::DEBUG);
          $id = $id['id'];
        } elseif (isset($id['uri'])) {
          // the URI is the UUID.vcf, just strip the suffix
          \OCP\Util::writeLog(Config::APP_NAME,
                              __METHOD__.': '. 'Called for uri '.$id['uri'],
                              \OCP\Util::DEBUG);
          $id = substr($id['uri'], 0, -4);
        } else {
          throw new \Exception(
            __METHOD__ . ': If second argument is an array, either \'id\' or \'uri\' has to be set.'
            );
        }
      }

      /* \OCP\Util::writeLog(Config::APP_NAME, */
      /*                     __METHOD__.': '. 'Called for '.$id, */
      /*                     \OCP\Util::DEBUG); */

      $idParts = self::parseContactId($id);
      if (!isset($idParts['uuid']) || !isset($idParts['addressbook']) ||
          ($addressBookId && $idParts['addressbook'] !== $addressBookId)) {
        throw new \Exception(
          __METHOD__ . ': Invalid id: '.$id.' for book '.(string)$addressBookId.'.'
          );
      }

      if (!$addressBookId) {
        $addressBookId = $idParts['addressbook'];
      }
      $uuid = $idParts['uuid'];

      if ($row = $this->__getContact($uuid, $options)) {
        $vCard = VCard::export($row);
        $contactId = self::contactId($addressBookId, $row['UUID']);
        $contact = array(
          'id' => $contactId,
          'uri' => $contactId.'.vcf',
          'lastmodified' => strtotime($row['Aktualisiert']),
          'parent' => (int)$addressBookId, // can be null if groups should ever be supported.
          'displayname' => $row['Vorname'].' '.$row['Name'],
          //'carddata' => $vCard->serialize(),
          'vcard' => $vCard, // would have to be the derived class from the contacts app
          'permissions' => \OCP\PERMISSION_ALL
          );
        /* \OCP\Util::writeLog(Config::APP_NAME, */
        /*                     __METHOD__. */
        /*                     ': vCard: '. */
        /*                     $vCard->serialize(), */
        /*                     \OCP\Util::ERROR); */
      } else {
        $contact = null;
      }

      return $contact;
    }

    /**We simply can fetch the respective vCard from the global
     * Musiker table as all project tables are simply views into
     * this table. In principle, is never necessary to honour the
     * $addressBookId.
     *
     * This beast fetches one row from the Musiker table.
     *
     * @param[in] string $uuid Actually, the UUID of the contact, used
     * as id for the contacts app.
     *
     * @param[in] array $options Optional (backend specific options).
     *
     * @return The respective row from the Musiker table, or null on
     * error.
     */
    private function __getContact($uuid, array $options = array())
    {
      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);

      $table = self::MAIN_CONTACTS_TABLE;

      $query = "SELECT `".$table."`.*,
       GROUP_CONCAT(DISTINCT `Projects`.`Name` ORDER BY `Projects`.`Name` ASC SEPARATOR ',') AS `Projects`,
       CONCAT('data:',`ImageData`.`MimeType`,';base64,',`ImageData`.`Data`) AS `Portrait`
 FROM `".$table."`
 LEFT JOIN
 `ImageData`
 ON `".$table."`.`Id` = `ImageData`.`ItemId` AND `ImageData`.`ItemTable` = 'Musiker'
 LEFT JOIN
 `Besetzungen` AS `ProjectsInstrumentation`
 ON `".$table."`.`Id` = `ProjectsInstrumentation`.`MusikerId`
 LEFT JOIN
 `Projekte` AS `Projects`
 ON `ProjectsInstrumentation`.`ProjektId` = `Projects`.`Id`
 WHERE `".$table."`.`UUID` LIKE '".$uuid."'
 GROUP BY `".$table."`.`Id`";

      $result = mySQL::query($query, $handle);
      if ($result === false || mySQL::numRows($result) != 1 || !($row = mySQL::fetch($result))) {
        \OCP\Util::writeLog(Config::APP_NAME,
                            __METHOD__.
                            ': failed, query: '.$query.'.',
                            \OCP\Util::DEBUG);
        $row = null;
      }

      mySQL::close($handle);

      return $row;
    }

    /**
     * Creates a new contact
     *
     * In the Database and Shared backends contact be either a Contact object or a string
     * with carddata to be able to play seamlessly with the CardDAV backend.
     * If this method is called by the CardDAV backend, the carddata is already validated.
     * NOTE: It's assumed that this method is called either from the CardDAV backend, the
     * import script, or from the ownCloud web UI in which case either the uri parameter is
     * set, or the contact has a UID. If neither is set, it will fail.
     *
     * @param string $addressBookId
     * @param string $contact
     * @param array $options - Optional (backend specific options)
     * @return false|string The identifier for the new contact or false on error.
     */
    public function createContact($addressBookId, $contact, array $options = array())
    {
      \OCP\Util::writeLog(Config::APP_NAME,
                          __METHOD__.
                          ': adb: '.$addressBookId.' id: '.print_r($addressBookId, true),
                          \OCP\Util::DEBUG);

      if (!$this->accessAllowed()) {
        return null;
      }

      $uri = isset($options['uri']) ? $options['uri'] : null;

      if (!$contact instanceof \Sabre\VObject\Component\VCard &&
          !$contact instanceof \OCA\Contacts\VObject\VCard) {
        try {
          $contact = \Sabre\VObject\Reader::read(
            $contact,
            \Sabre\VObject\Reader::OPTION_FORGIVING|\Sabre\VObject\Reader::OPTION_IGNORE_INVALID_LINES
            );
        } catch(\Exception $e) {
          \OCP\Util::writeLog(Config::APP_NAME, __METHOD__.', exception: '.$e->getMessage(), \OCP\Util::ERROR);
          return false;
        }
      }

      try {
        $contact->validate(\OCA\Contacts\VObject\VCard::REPAIR|\OCA\Contacts\VObject\VCard::UPGRADE);
      } catch (\Exception $e) {
        \OCP\Util::writeLog('contacts', __METHOD__ . ' ' .
                            'Error validating vcard: ' . $e->getMessage(), \OCP\Util::ERROR);
        return false;
      }

      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);

      // always generate a UUID on our own?
      if (!isset($contact->UID)) {
        $uuid = Musicians::generateUUID($handle);
        $contact->UID = $uuid;
      }
      $uuid = (string)$contact->UID;

      // check if an entry with this uid already exists.
      //
      // musicians address-book -> error
      //
      // project address-book -> ok, but don't add a new musician to
      // the global musicians table
      if (!Musicians::findDuplicates(array('UUID' => $uuid), $handle)) {
        $now = new \DateTime;
        $contact->REV = $now->format(\DateTime::W3C);

        // generate the data-line
        $row = VCard::import($contact->serialize());

        if (isset($row['Portrait'])) {
          $img = new InlineImage(self::MAIN_CONTACTS_TABLE);
          if (!$img->store($me['Id'], $row['Portrait'], $handle)) {
            mySQL::close($handle);
            return false;
          }
          unset($row['Portrait']);
        }

        unset($row['Projects']);

        // the remaining part should contain valid fields for the Musiker table

        if (!mySQL::insert(self::MAIN_CONTACTS_TABLE, $row, $handle)) {
          mySQL::close($handle);
          return false;
        }
        $id = mySQL::newestIndex($handle);
        mySQL::logInsert(self::MAIN_CONTACTS_TABLE, $id, $row, $handle);
      } else if ((string)$addressBookId == (string)self::MAIN_ADDRESS_BOOK_ID) {
        // don't add duplicates
        mySQL::close($handle);
        return false;
      }

      if ((string)$addressBookId != (string)self::MAIN_ADDRESS_BOOK_ID) {
        // if this is not the main address-book, we also need to add the
        // contact into the "Besetzungen" table.

        $projectId = self::projectId($addressBookId);
        $result = Instrumentation::addMusicians($uuid, $projectId, $handle);
        if ($result === false ||
            !is_array($result) ||
            !isset($result['added']) || !isset($result['failed'])) {
          \OCP\Util::writeLog(Config::APP_NAME,
                              __METHOD__.
                              ': failed to add musician '.$uuid.' to project '.$projectId,
                              \OCP\Util::ERROR);
        }
        if (count($result['added']) != 1) {
          \OCP\Util::writeLog(Config::APP_NAME,
                              __METHOD__.
                              ': failed to add musician '.$uuid.' to project '.$projectId.
                              ' Reported error: '.print_r($result, true),
                              \OCP\Util::ERROR);
          mySQL::close($handle);
          return false;
        } else {
          \OCP\Util::writeLog(Config::APP_NAME,
                              __METHOD__.
                              ': added musician '.$uuid.' to project '.$projectId.
                              ' Reported result: '.print_r($result['added'][0], true),
                              \OCP\Util::DEBUG);
        }
      }

      mySQL::close($handle);

      $contactId = self::contactId($addressBookId, $uuid);

      return $contactId;
    }

    /**
     * Updates a contact
     *
     * @param string $addressBookId
     * @param mixed $id Contact ID
     * @param string $contact
     * @param array $options - Optional (backend specific options)
     * @see getContact
     * @return bool
     * @throws Exception if $contact is a string but can't be parsed as a VCard
     * @throws Exception if the Contact to update couldn't be found
     */
    public function updateContact($addressBookId, $id, $contact, array $options = array())
    {
      \OCP\Util::writeLog(Config::APP_NAME,
                          __METHOD__.
                          ': adb: '.$addressBookId.' id: '.print_r($id, true),
                          \OCP\Util::DEBUG);

      if (!$this->accessAllowed()) {
        return null;
      }

      if (!$contact instanceof \Sabre\VObject\Component\VCard &&
          !$contact instanceof \OCA\Contacts\VObject\VCard) {
        try {
          $contact = \Sabre\VObject\Reader::read(
            $contact,
            \Sabre\VObject\Reader::OPTION_FORGIVING|\Sabre\VObject\Reader::OPTION_IGNORE_INVALID_LINES
            );
        } catch(\Exception $e) {
          \OCP\Util::writeLog(Config::APP_NAME, __METHOD__.', exception: '.$e->getMessage(), \OCP\Util::ERROR);
          return false;
        }
      }

      if (is_array($id)) {
        if (isset($id['id'])) {
          $id = $id['id'];
        } elseif (isset($id['uri'])) {
          // the URI is the UUID.vcf, just strip the suffix
          $id = substr($id['uri'], 0, -4);
        } else {
          throw new \Exception(
            __METHOD__ . ' If second argument is an array, either \'id\' or \'uri\' has to be set.'
            );
        }
      }

      $idParts = self::parseContactId($id);
      if (!isset($idParts['uuid']) || !isset($idParts['addressbook']) ||
          ($addressBookId && $idParts['addressbook'] !== $addressBookId)) {
        throw new \Exception(
          __METHOD__ . ': Invalid id: '.$id.' for book '.(string)$addressBookId.'.'
          );
      }

      if (!$addressBookId) {
        $addressBookId = $idParts['addressbook'];
      }
      $uuid = $idParts['uuid'];

      // FILL IN HERE. Various checks need to be implemented ...
      $me = $this->__getContact($uuid);
      $them = VCard::import($contact->serialize());

      /* TODO:
       *
       * - check timestamps
       * - what about categories and instruments?
       * - discard UUID, keep our own
       * - set timestamp to current time
       *
       */

      // At this point, $me and $them should contain a row suitable
      // for (re-)insertion into the Musiker-table, where $them may
      // additionally contain image data, but $me not.

      // Check the timestamps. If $them contains one which is older,
      // then refuse the update. What if $them has no REV field? ATM,
      // we refuse to update.

      if (!isset($them['Aktualisiert'])) {
        \OCP\Util::writeLog(Config::APP_NAME,
                            __METHOD__.
                            ': "them" does not carry time-stamp: '.
                            $contact->serialize(),
                            \OCP\Util::DEBUG);
        return false;
      }

      $themStamp = strtotime($them['Aktualisiert']);
      $meStamp = strtotime($me['Aktualisiert']);

      \OCP\Util::writeLog(Config::APP_NAME,
                          __METHOD__.
                          ': them: '.$them['Aktualisiert'].' me: '.$me['Aktualisiert'],
                          \OCP\Util::DEBUG);

      if ($themStamp < $meStamp) {
        // this means that an out-of-date record attempts its way into
        // the data-base. Don't.
        \OCP\Util::writeLog(Config::APP_NAME,
                            __METHOD__.
                            " vCard from ".$them['Aktualisiert']." is older than ".$me['Aktualisiert'],
                            \OCP\Util::DEBUG);
        return false;
      }

      $noPhoto = $them;
      if (isset($noPhoto['Portrait'])) {
        unset($noPhoto['Portrait']);
      }
      \OCP\Util::writeLog(Config::APP_NAME,
                          __METHOD__.
                          ': their vcard: '.print_r($noPhoto, true),
                          \OCP\Util::DEBUG);

      $common = array_intersect($me, $them);

      /* \OCP\Util::writeLog(Config::APP_NAME, */
      /*                     __METHOD__. */
      /*                     ': common: '.print_r($common, true), */
      /*                     \OCP\Util::DEBUG); */

      $changed = array_diff($them, $common);

      /* \OCP\Util::writeLog(Config::APP_NAME, */
      /*                     __METHOD__. */
      /*                     ': changed: '.print_r($changed, true), */
      /*                     \OCP\Util::DEBUG); */
      foreach($changed as $key => $value) {
        $theirValue = isset($them[$key]) ? $them[$key] : "unset";
        $myValue = isset($me[$key]) ? $me[$key] : "unset";

        \OCP\Util::writeLog(Config::APP_NAME,
                            __METHOD__.
                            ': me: "'.$myValue.'" them: "'.$theirValue.'"',
                            \OCP\Util::DEBUG);
      }

      // In principle the $changed array should now contain all the
      // changed values for the Musiker table. Exception are the
      // "Projects" field, which is just ignored, and the Protrait
      // field, which goes to a separate table.

      // TODO: really insert it. Take care of the time-stamps AND the
      // changelog entry. We will overwrite the given time-stamp with
      // the actual one.

      if (empty($changed)) {
        return true; // don't care, could update time-stamp perhaps
      }

      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);

      $result = true;

      if (isset($changed['Portrait'])) {
        $img = new InlineImage(self::MAIN_CONTACTS_TABLE);
        $img->store($me['Id'], $changed['Portrait'], $handle) || $result = false;
        unset($changed['Portrait']);
      }

      unset($changed['Projects']);

      // the remaining part should contain valid fields for the Musiker table

      mySQL::update(self::MAIN_CONTACTS_TABLE, '`Id` = '.$me['Id'], $changed, $handle) || $result = false;
      mySQL::logUpdate(self::MAIN_CONTACTS_TABLE, 'Id', $me, $changed, $handle);

      mySQL::close($handle);

      return $result;
    }

    /**
     * Deletes a contact. Note: we do not allow to remove something
     * from the Musiker table. We simply pretend it worked, but do
     * nothing. We support removing musicians from specific projects.
     *
     * @param string $addressBookId
     * @param false|string $id
     * @param array $options - Optional (backend specific options)
     * @see getContact
     * @return bool
     */
    public function deleteContact($addressBookId, $id, array $options = array())
    {
      if (!$this->accessAllowed()) {
        return null;
      }

      if (is_array($id)) {
        if (isset($id['id'])) {
          \OCP\Util::writeLog(Config::APP_NAME,
                              __METHOD__.': '. 'Called for id '.$id['id'],
                              \OCP\Util::DEBUG);
          $id = $id['id'];
        } elseif (isset($id['uri'])) {
          // the URI is the UUID.vcf, just strip the suffix
          \OCP\Util::writeLog(Config::APP_NAME,
                              __METHOD__.': '. 'Called for uri '.$id['uri'],
                              \OCP\Util::DEBUG);
          $id = substr($id['uri'], 0, -4);
        } else {
          throw new \Exception(
            __METHOD__ . ': If second argument is an array, either \'id\' or \'uri\' has to be set.'
            );
        }
      }

      /* \OCP\Util::writeLog(Config::APP_NAME, */
      /*                     __METHOD__.': '. 'Called for '.$id, */
      /*                     \OCP\Util::DEBUG); */

      $idParts = self::parseContactId($id);
      if (!isset($idParts['uuid']) || !isset($idParts['addressbook']) ||
          ($addressBookId && $idParts['addressbook'] !== $addressBookId)) {
        throw new \Exception(
          __METHOD__ . ': Invalid id: '.$id.' for book '.(string)$addressBookId.'.'
          );
      }

      if (!$addressBookId) {
        $addressBookId = $idParts['addressbook'];
      }
      $uuid = $idParts['uuid'];

      if ((string)$addressBookId == (string)self::MAIN_ADDRESS_BOOK_ID) {
        \OCP\Util::writeLog(Config::APP_NAME,
                            __METHOD__.': deleting of '.$uuid.' from '.$addressBookId.' denied.',
                            \OCP\Util::DEBUG);
        return true; // cheat
      }

      /**Then $addressBookId must correspond to a project. Deleting
       * the contact may indeed more than one entry from the
       * Besetzungen table in case the musician plays more than one
       * instrument in the same project.
       */

      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);

      $projectId = self::projectId($addressBookId);
      $projectName = Projects::fetchName($projectId, $handle);
      if (empty($projectName) ||
          self::addressBookId($projectName, $projectId) !== $addressBookId) {
        mySQL::close($handle);
        return false;
      }

      /* as the project address-books also just use the UUIDs we
       * simply can fetch from the global Musiker table
       */
      $table = self::MAIN_CONTACTS_TABLE;
      $where = "WHERE `UUID` LIKE '".$uuid."'";
      $id = mySQL::selectSingleFromTable("`Id`", $table, $where, $handle);
      if ($id === false) {
        mySQL::close($handle);
        return false;
      }

      $old = Instrumentation::fetchByMusicianId($id, $projectId, $handle);
      if (!is_array($old)) {
        mySQL::close($handle);
        return false;
      }

      $query = "DELETE FROM `Besetzungen`
  WHERE `MusikerId` = ".$id." AND `ProjektId` = ".$projectId;
      $result = mySQL::query($query, $handle);
      if (!$result) {
        mySQL::close($handle);
        return false;
      }

      foreach ($old as $row) {
        mySQL::logDelete('Besetzungen', 'Id', $row, $handle);
      }

      mySQL::close($handle);

      return true;
    }

    /**As changing just anything potentially changes anything by
     * changing categories we simply return the last changelog stamp
     * for everything. This propably is just the most sane idea about
     * it.
     */
    public function lastModifiedAddressBook($addressBookId)
    {
      if (!$this->accessAllowed()) {
        return null;
      }

      $date = mySQL::selectSingleFromTable(
        "`updated`", 'changelog',
        "ORDER BY `id` DESC LIMIT 0, 1");

      \OCP\Util::writeLog(Config::APP_NAME,
                          __METHOD__.
                          ' last modified '.$date,
                          \OCP\Util::DEBUG);

      return strtotime($date);
    }

    /**
     * @brief Get the last modification time for a contact.
     *
     * Must return a UNIX time stamp or null if the backend
     * doesn't support it.
     *
     * @param string $addressBookId
     * @param mixed $id
     * @returns int | null
     */
    public function lastModifiedContact($addressBookId, $id)
    {
      if (!$this->accessAllowed()) {
        return null;
      }

      if (is_array($id)) {
        if (isset($id['id'])) {
          $id = $id['id'];
        } elseif (isset($id['uri'])) {
          // the URI is the UUID.vcf, just strip the suffix
          $id = substr($id['uri'], 0, -4);
        } else {
          throw new \Exception(
            __METHOD__ . ' If second argument is an array, either \'id\' or \'uri\' has to be set.'
            );
        }
      }

      $idParts = self::parseContactId($id);
      if (!isset($idParts['uuid']) || !isset($idParts['addressbook']) ||
          ($addressBookId && $idParts['addressbook'] !== $addressBookId)) {
        throw new \Exception(
          __METHOD__ . ': Invalid id: '.$id.' for book '.(string)$addressBookId.'.'
          );
      }

      if (!$addressBookId) {
        $addressBookId = $idParts['addressbook'];
      }
      $uuid = $idParts['uuid'];

      /* as the project address-books also just use the UUIDs we
       * simply can fetch from the global Musiker table
       */
      $where = "WHERE `UUID` LIKE '".$id."'";
      $table = self::MAIN_CONTACTS_TABLE;

      if ($addressBookId !== self::MAIN_ADDRESS_BOOK_ID) {
        $projectId = self::projectId($addressBookId);
        $projectName = Projects::fetchName($projectId);
        if (empty($projectName) ||
            self::addressBookId($projectName, $projectId) !== $addressBookId) {
          return null;
        }
      }

      $date = mySQL::selectSingleFromTable("`Aktualisiert`", $table, $where);

      \OCP\Util::writeLog(Config::APP_NAME,
                          __METHOD__.
                          ' last modified '.$date,
                          \OCP\Util::DEBUG);

      return strtotime($date);
    }

    // Mmmh. Nun ja.
    public function getSearchProvider($addressbook) {
      return new \OCA\Contacts\AddressbookProvider($addressbook);
    }

  };

} // namespace
