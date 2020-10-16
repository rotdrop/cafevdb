ALTER TABLE GeoPostalCodeTranslations DROP FOREIGN KEY FK_BC664719677674A7;

ALTER TABLE GeoPostalCodes MODIFY Id INT NOT NULL;
DROP INDEX Country ON GeoPostalCodes;
ALTER TABLE GeoPostalCodes DROP PRIMARY KEY;
ALTER TABLE GeoPostalCodes DROP Id;
ALTER TABLE GeoPostalCodes ADD PRIMARY KEY (Country, PostalCode);

ALTER TABLE GeoPostalCodeTranslations DROP PRIMARY KEY;
ALTER TABLE GeoPostalCodeTranslations ADD Country VARCHAR(4) NOT NULL, ADD PostalCode VARCHAR(32) NOT NULL, DROP PostalCodeId;
ALTER TABLE GeoPostalCodeTranslations ADD CONSTRAINT FK_BC664719C5E8C7D95373C966 FOREIGN KEY (postalCode, country) REFERENCES GeoPostalCodes (postalCode, country);
CREATE INDEX IDX_BC664719C5E8C7D95373C966 ON GeoPostalCodeTranslations (postalCode, country);
ALTER TABLE GeoPostalCodeTranslations ADD PRIMARY KEY (PostalCode, Country, Target);

ALTER TABLE Musiker ADD CONSTRAINT FK_F900DE10F5566A3FA800D5D8 FOREIGN KEY (postleitzahl, land) REFERENCES GeoPostalCodes (postalCode, country);
CREATE INDEX IDX_F900DE10F5566A3FA800D5D8 ON Musiker (postleitzahl, land);
