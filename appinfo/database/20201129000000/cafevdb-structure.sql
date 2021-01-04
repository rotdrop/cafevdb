DELIMITER $$
CREATE OR REPLACE FUNCTION tokenCount(
  x TEXT CHARACTER SET utf8mb4,
  delim VARCHAR(12)
)
RETURNS INTEGER
DETERMINISTIC
RETURN CHAR_LENGTH(x) - CHAR_LENGTH(REPLACE(x, delim, '')) + 1$$
DELIMITER ;

DELIMITER $$
CREATE OR REPLACE FUNCTION splitString(
  x VARCHAR(1023) CHARACTER SET utf8mb4,
  delim VARCHAR(12),
  pos INT
)
RETURNS VARCHAR(255) CHARACTER SET utf8
DETERMINISTIC
RETURN REPLACE(SUBSTRING(SUBSTRING_INDEX(x, delim, pos),
       CHAR_LENGTH(SUBSTRING_INDEX(x, delim, pos -1)) + 1),
                        delim, '')$$
DELIMITER ;

DELIMITER $$
CREATE OR REPLACE PROCEDURE generateNumbers(IN min INT)
BEGIN
    CREATE TABLE IF NOT EXISTS numbers
        ( n INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY )
        ENGINE memory
        AS SELECT 1 n UNION ALL SELECT 2;
    SELECT COUNT(*) FROM numbers INTO @max;
    IF @max = 0 THEN
        INSERT INTO numbers SELECT 1 n UNION ALL SELECT 2;
        SET @max = 2;
    END IF;
    WHILE @max < min DO
        INSERT IGNORE INTO numbers SELECT (hi.n-1)*(SELECT COUNT(*) FROM numbers)+(lo.n-1)+1 AS n
          FROM numbers lo, numbers hi;
        SELECT COUNT(*) FROM numbers INTO @max;
    END WHILE;
END$$
DELIMITER ;
