#Code Target Translation
#EU   de     Europa
ALTER TABLE `cafevdb_musiker`.`GeoContinents` DROP PRIMARY KEY;
ALTER TABLE `cafevdb_musiker`.`GeoContinents` DROP `en`, DROP `de`, DROP `fr`;
ALTER TABLE `cafevdb_musiker`.`GeoContinents` ADD `Target` VARCHAR(2) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL AFTER `Code`, ADD `Translation` VARCHAR(1024) NOT NULL AFTER `Target`;
ALTER TABLE `cafevdb_musiker`.`GeoContinents` CHANGE `Code` `Code` VARCHAR(2) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL;
ALTER TABLE `cafevdb_musiker`.`GeoContinents` ADD PRIMARY KEY (`Code`, `ISO`);

#ISO Target    Data
#de  fr        Allemagne   # translation target
#de  ->        EU          # continent target
#de  @.        Deutschland # native name
ALTER TABLE `cafevdb_musiker`.`GeoCountries` DROP PRIMARY KEY;
ALTER TABLE `cafevdb_musiker`.`GeoCountries` DROP `NativeName`, DROP `en`, DROP `de`, DROP `fr`;
ALTER TABLE `cafevdb_musiker`.`GeoCountries` CHANGE `Continent` `Continent` VARCHAR(2) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL;
ALTER TABLE `cafevdb_musiker`.`GeoCountries` ADD `Target` VARCHAR(2) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL AFTER `Continent`, ADD `Data` VARCHAR(1024) NOT NULL AFTER `Target`;
ALTER TABLE `cafevdb_musiker`.`GeoCountries` DROP `Continent`;
ALTER TABLE `cafevdb_musiker`.`GeoCountries` ADD PRIMARY KEY (`ISO`, `Target`);

ALTER TABLE `cafevdb_musiker`.`GeoPostalCodes` DROP `en`, DROP `fr`, DROP `de`;
ALTER TABLE `GeoPostalCodes` CHANGE `Name` `Name` VARCHAR(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;
ALTER TABLE `GeoPostalCodes` CHANGE `Latitude` `Latitude` DOUBLE NOT NULL;
ALTER TABLE `GeoPostalCodes` CHANGE `Longitude` `Longitude` DOUBLE NOT NULL;

# GeoPostalCodeTranslations
# Id PostalCodeId Target Data
# 12
CREATE TABLE `cafevdb_musiker`.`GeoPostalCodeTranslations` ( `PostalCodeId` INT NOT NULL , `Target` VARCHAR(2) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL , `Translation` VARCHAR(1024) NOT NULL , PRIMARY KEY (`Id`)) ENGINE = InnoDB;
ALTER TABLE `cafevdb_musiker`.`GeoPostalCodeTranslations` ADD PRIMARY KEY (`PostalCodeId`, `Target`) USING BTREE;
ALTER TABLE GeoPostalCodeTranslations ADD CONSTRAINT FK_BC664719677674A7 FOREIGN KEY (PostalCodeId) REFERENCES GeoPostalCodes (Id);
