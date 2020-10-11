ALTER TABLE `cafevdb_musiker`.`ProjectEvents` CHANGE `Id` `Id` INT(11) NOT NULL;
ALTER TABLE `cafevdb_musiker`.`ProjectEvents` DROP PRIMARY KEY;
ALTER TABLE `cafevdb_musiker`.`ProjectEvents` DROP INDEX `ProjectId_EventId`;
ALTER TABLE `cafevdb_musiker`.`ProjectEvents` DROP INDEX `EventId_EventURI`;
ALTER TABLE `cafevdb_musiker`.`ProjectEvents` DROP INDEX `ProjectId_EventURI`, ADD PRIMARY KEY (`ProjectId`, `EventURI`) USING BTREE;
ALTER TABLE `cafevdb_musiker`.`ProjectEvents` DROP `Id`, DROP `EventId`;
