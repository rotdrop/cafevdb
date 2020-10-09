ALTER TABLE `cafevdb_musiker`.`Besetzungen` DROP INDEX `Id`, ADD PRIMARY KEY (`Id`) USING BTREE;
ALTER TABLE `cafevdb_musiker`.`GeoPostalCodes` ADD `Id` INT NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (`Id`);
ALTER TABLE `cafevdb_musiker`.`ProjectExtraFields` DROP INDEX `Id`, ADD PRIMARY KEY (`Id`) USING BTREE;
