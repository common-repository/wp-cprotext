ALTER TABLE __CPTXTABLENAME__ drop column ie8enabled;

CREATE TABLE __CPTIMGTABLENAME__ (
  id bigint NOT NULL AUTO_INCREMENT,
  imgId bigint NOT NULL,
  parentId bigint,
  srcpath text,
  destpath text,
  css mediumtext,
  html mediumtext,
  PRIMARY KEY pk_cptimg(id),
  FOREIGN KEY fk_cptimg(parentId) REFERENCES __CPTIMGTABLENAME__(id)
);

