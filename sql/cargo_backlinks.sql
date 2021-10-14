CREATE TABLE /*_*/cargo_backlinks (
  cbl_query_page_id INT UNSIGNED DEFAULT 0 NOT NULL,
  cbl_result_page_id INT UNSIGNED DEFAULT 0 NOT NULL,
  PRIMARY KEY(cbl_query_page_id, cbl_result_page_id)
) /*$wgDBTableOptions*/;