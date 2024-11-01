CREATE TABLE __CPTXTABLENAME__ (
  id bigint unsigned NOT NULL,
  textId varchar(25) NOT NULL,
  enabled boolean NOT NULL,
  parentId bigint unsigned NOT NULL,
  font varchar(25) NOT NULL,
  version mediumint NOT NULL,
  plh mediumtext,
  css mediumtext NOT NULL,
  html mediumtext NOT NULL,
  ie8enabled boolean NOT NULL,
  eote mediumblob,
  eots mediumblob,
  PRIMARY KEY pk_cptx (id),
  UNIQUE KEY uk_textId (textId,font)
);

