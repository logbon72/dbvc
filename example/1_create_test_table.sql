CREATE TABLE `countries` (
  `ID` int NOT NULL AUTO_INCREMENT ,
  `Name` varchar(60) NOT NULL,
  `Code` varchar(5) DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB;