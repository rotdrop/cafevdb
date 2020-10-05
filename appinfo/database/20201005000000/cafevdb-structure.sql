ALTER TABLE `ProjectEvents` ADD `EventURI` VARCHAR(1024) CHARACTER SET ascii COLLATE ascii_general_ci NULL DEFAULT NULL AFTER `EventId`;

ALTER TABLE `cafevdb_musiker`.`ProjectEvents` DROP INDEX `ProjectId`, ADD UNIQUE `ProjectId_EventId` (`Id`, `ProjectId`, `EventId`) USING BTREE;
ALTER TABLE `cafevdb_musiker`.`ProjectEvents` ADD UNIQUE `ProjectId_EventURI` (`Id`, `ProjectId`, `EventURI`);
ALTER TABLE `cafevdb_musiker`.`ProjectEvents` ADD UNIQUE `EventId_EventURI` (`Id`, `EventId`, `EventURI`) USING BTREE;
