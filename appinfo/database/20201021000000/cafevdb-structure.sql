DELIMITER $$
CREATE FUNCTION `BIN2UUID`(`b` BINARY(16)) RETURNS char(36) CHARSET ascii
    NO SQL
    DETERMINISTIC
    SQL SECURITY INVOKER
BEGIN
  RETURN BIN_TO_UUID(b, 0);
END$$
DELIMITER ;

DELIMITER $$
CREATE FUNCTION `BIN_TO_UUID`(`b` BINARY(16), `f` BOOLEAN) RETURNS char(36) CHARSET ascii
    NO SQL
    DETERMINISTIC
    SQL SECURITY INVOKER
BEGIN
DECLARE hexStr CHAR(32);
SET hexStr = HEX(b);
RETURN LOWER(CONCAT(
        IF(f,SUBSTR(hexStr, 9, 8),SUBSTR(hexStr, 1, 8)), '-',
        IF(f,SUBSTR(hexStr, 5, 4),SUBSTR(hexStr, 9, 4)), '-',
        IF(f,SUBSTR(hexStr, 1, 4),SUBSTR(hexStr, 13, 4)), '-',
        SUBSTR(hexStr, 17, 4), '-',
        SUBSTR(hexStr, 21)
    ));
END$$
DELIMITER ;

DELIMITER $$
CREATE FUNCTION `UUID_TO_BIN`(`uuid` CHAR(36), `f` BOOLEAN) RETURNS binary(16)
    NO SQL
    DETERMINISTIC
    SQL SECURITY INVOKER
BEGIN
  RETURN UNHEX(CONCAT(
  IF(f,SUBSTRING(uuid, 15, 4),SUBSTRING(uuid, 1, 8)),
  SUBSTRING(uuid, 10, 4),
  IF(f,SUBSTRING(uuid, 1, 8),SUBSTRING(uuid, 15, 4)),
  SUBSTRING(uuid, 20, 4),
  SUBSTRING(uuid, 25))
  );
END$$
DELIMITER ;

DELIMITER $$
CREATE FUNCTION `UUID2BIN`(`uuid` CHAR(36)) RETURNS binary(16)
    NO SQL
    DETERMINISTIC
    SQL SECURITY INVOKER
BEGIN
  RETURN UUID_TO_BIN(uuid, 0);
END$$
DELIMITER ;
