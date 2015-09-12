<?php
/* Orchestra member, musician and project management application.
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

  class AddressbookBackend extends \OCA\Contacts\Backend\AbstractBackend
  {
    const NAME = 'cafevdb';
    const MAIN_ADDRESS_BOOK = 'musicians';
    const MAIN_ADDRESS_BOOK_ID = 0;
    const MAIN_CONTACTS_TABLE = 'Musiker';

    /**
     * The name of the backend.
     *
     * @var string
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

    /**
     * Flag indicating whether the current user is a member of the
     * orchestra user-group.
     *
     * @var boolean
     */
    private $orchestraUser;

    public function __construct($userId = null)
    {
      Config::init();
      $this->userId = $userId ? $userId : \OCP\User::getUser();
      $this->shareGroup = Config::getAppValue('usergroup');
      $this->shareOwner = Config::getValue('shareowner');
      $this->orchestraUser = \OC_Group::inGroup($this->userId, $this->shareGroup);

      \OCP\Util::writeLog(Config::APP_NAME,
                          __METHOD__.': '.
                          $this->userId . '@' . $this->shareGroup .
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
     * \OCA\Contacts\Backend\AbstractBackend we need some more
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
          'lastmodified' => mySQL::fetchLastModified(self::MAIN_CONTACTS_TABLE),
          'owner' => $this->userId, //  $this->shareOwner,
          'uri' => self::makeURI(self::MAIN_ADDRESS_BOOK),
          'permissions' => \OCP\PERMISSION_READ,
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
          'lastmodified' => mySQL::fetchLastModified($projectName."View"),
          'owner' => $this->userId, // $this->shareOwner,
          'uri' => self::makeURI($projectName),
          'permissions' => \OCP\PERMISSION_READ,
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
        $numRows = mySQL::queryNumRows("FROM `Musiker` WHERE 1");
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
        $numRows = mySQL::queryNumRows("FROM `".$projectName."View` WHERE 1");
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

      if ((string)$addressBookId === (string)self::MAIN_ADDRESS_BOOK_ID) {
        return $this->getMusiciansContacts($options);
      } else {
        return $this->getProjectContacts($addressBookId, $options);
      }
    }

    private function getMusiciansContacts(array $options = array())
    {
      $addressBookId = self::MAIN_ADDRESS_BOOK_ID;

      $contacts = array();

      $myOptions = array('limit' => PHP_INT_MAX,
                         'offset' => 0,
                         'omitdata' => false);
      foreach ($options as $key => $value) {
        if ($value) {
          $myOptions[$key] = $value;
        }
      }
      $options = $myOptions;

      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);

      if ($options['omitdata']) {
        $query = "SELECT
 `UUID` AS `id`, CONCAT(`UUID`, '.vcf') AS `uri`, `Aktualisiert` AS `lastmodified`,
 '".$addressBookId."' as `parent`, CONCAT(`Vorname`, ' ', `Name`) AS `displayname`
 FROM `Musiker` WHERE 1 LIMIT ".$options['offset'].", ".$options['limit'];
        $result = mySQL::query($query, $handle);
        while ($row = mySQL::fetch($result)) {
          $row['permissions'] = \OCP\PERMISSION_ALL;
          $contacts[] = $row;
        }
      } else {
        $query = "SELECT * FROM `Musiker`
 LEFT JOIN
 ( SELECT `MusikerId`,GROUP_CONCAT(DISTINCT `Projekte`.`Name` ORDER BY `Projekte`.`Name` ASC SEPARATOR ', ') AS `Projekte`
   FROM `Besetzungen`
   LEFT JOIN `Projekte` ON `Projekte`.`Id` = `Besetzungen`.`ProjektId`
   GROUP BY `MusikerId`
 ) AS `Projects`
 ON `Musiker`.`Id` = `Projects`.`MusikerId`
 WHERE 1 LIMIT ".$options['offset'].", ".$options['limit'];

        //throw new \Exception($query);

        $result = mySQL::query($query, $handle);
        while ($row = mySQL::fetch($result)) {
          $vCard = VCard::vCard($row);
          $contacts[] = array(
            'id' => $row['UUID'],
            'uri' => $row['UUID'].'.vcf',
            'lastmodified' => $row['Aktualisiert'],
            'parent' => $addressBookId,
            'displayname' => $row['Vorname'].' '.$row['Name'],
            //'vcard' => $vCard,
            'carddata' => $vCard->serialize(),
            'permissions' => \OCP\PERMISSION_ALL
            );
        }

      }

      mySQL::close($handle);

      return $contacts;
    }

    private function getProjectContacts($addressBookId, array $options = array())
    {
      $projectId = self::projectId($addressBookId);
      $projectName = Projects::fetchName($projectId);
      if (empty($projectName) ||
          self::addressBookId($projectName, $projectId) !== $addressBookId) {
        return array();
      }

      $contacts = array();

      $myOptions = array('limit' => PHP_INT_MAX,
                         'offset' => 0,
                         'omitdata' => false);
      foreach ($options as $key => $value) {
        if ($value) {
          $myOptions[$key] = $value;
        }
      }
      $options = $myOptions;

      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);

      $mainTable = $projectName."View";

      if ($options['omitdata']) {
        $query = "SELECT
 `UUID` AS `id`, CONCAT(`UUID`, '.vcf') AS `uri`, `Aktualisiert` AS `lastmodified`,
 '".$addressBookId."' as `parent`, CONCAT(`Vorname`, ' ', `Name`) AS `displayname`
 FROM `".$mainTable."` WHERE 1 LIMIT ".$options['offset'].", ".$options['limit'];
        $result = mySQL::query($query, $handle);
        while ($row = mySQL::fetch($result)) {
          $row['permissions'] = \OCP\PERMISSION_ALL;
          $contacts[] = $row;
        }
      } else {
        $query = "SELECT *,`AllInstruments` AS `Instrumente` FROM `".$mainTable."`
 LEFT JOIN
 ( SELECT `MusikerId`,GROUP_CONCAT(DISTINCT `Projekte`.`Name` ORDER BY `Projekte`.`Name` ASC SEPARATOR ', ') AS `Projekte`
   FROM `Besetzungen`
   LEFT JOIN `Projekte` ON `Projekte`.`Id` = `Besetzungen`.`ProjektId`
   GROUP BY `MusikerId`
 ) AS `Projects`
 ON `".$mainTable."`.`MusikerId` = `Projects`.`MusikerId`
 WHERE 1 LIMIT ".$options['offset'].", ".$options['limit'];

        //throw new \Exception($query);

        $result = mySQL::query($query, $handle);
        while ($row = mySQL::fetch($result)) {
          $vCard = VCard::vCard($row);
          $contacts[] = array(
            'id' => $row['UUID'],
            'uri' => $row['UUID'].'.vcf',
            'lastmodified' => $row['Aktualisiert'],
            'parent' => $addressBookId,
            'displayname' => $row['Vorname'].' '.$row['Name'],
            //'vcard' => $vCard,
            'carddata' => $vCard->serialize(),
            'permissions' => \OCP\PERMISSION_ALL
            );
        }

      }

      mySQL::close($handle);

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
     * 	This is because the groups backend - \OCP\Tags - doesn't no about parent collections
     * 	only object IDs. Hence a hack is made with an optional 'noCollection'.
     *
     * @param string $addressBookId
     * @param string|array $id Contact ID
     * @param array $options - Optional (backend specific options)
     * @return array|null
     */
    public function getContact($addressBookId, $id, array $options = array())
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

      /* We simply can fetch the respective vCard from the global
       * Musiker table as all project tables are simply views into
       * this table. In principle, is never necessary to honour the
       * $addressBookId.
       */

      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);

      $table = self::MAIN_CONTACTS_TABLE;

      $query = "SELECT * FROM `".$table."`
 LEFT JOIN
 ( SELECT `MusikerId`,GROUP_CONCAT(DISTINCT `Projekte`.`Name` ORDER BY `Projekte`.`Name` ASC SEPARATOR ', ') AS `Projekte`
   FROM `Besetzungen`
   LEFT JOIN `Projekte` ON `Projekte`.`Id` = `Besetzungen`.`ProjektId`
   GROUP BY `MusikerId`
 ) AS `Projects`
 ON `".$table."`.`Id` = `Projects`.`MusikerId`
 WHERE `".$table."`.`UUID` LIKE '".$id."'";
      $result = mySQL::query($query, $handle);
      if ($result !== false && mySQL::numRows($result) == 1 && $row = mySQL::fetch($result)) {
        $vCard = VCard::vCard($row);
        $contact = array(
          'id' => $row['UUID'],
          'uri' => $row['UUID'].'.vcf',
          'lastmodified' => strtotime($row['Aktualisiert']),
          'parent' => (int)$addressBookId, // can be null if groups should ever be supported.
          'displayname' => $row['Vorname'].' '.$row['Name'],
          'carddata' => $vCard->serialize(),
          //'vcard' => $vCard, // would have to be the derived class from the contacts app
          'permissions' => \OCP\PERMISSION_ALL
          );
      } else {
        $contact = null;
      }

      mySQL::close($handle);

      return $contact;
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

      return strtotime(mySQL::selectSingleFromTable("`Aktualisiert`", $table, $where));
    }

  };

} // namespace
