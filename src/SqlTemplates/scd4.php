<?php

declare(strict_types=1);

return <<< SQL

SET CURR_DATE =  (SELECT CONVERT_TIMEZONE('Europe/Prague', current_timestamp()))::DATE;
SET CURR_TIMESTAMP =  (SELECT CONVERT_TIMEZONE('Europe/Prague', current_timestamp())::TIMESTAMP_NTZ);
SET CURR_DATE_TXT =  (SELECT TO_CHAR(\$CURR_DATE, 'YYYY-MM-DD'));
SET CURR_TIMESTAMP_TXT =  (SELECT TO_CHAR(\$CURR_TIMESTAMP, 'YYYY-MM-DD HH:Mi:SS'));

-- actual snapshot
CREATE OR REPLACE TABLE records_snapshot AS
SELECT \${input_table_cols}
       , \$CURR_TIMESTAMP_TXT AS "snapshot_date"
       , 1 AS "actual"
       , 0 AS "is_deleted"
FROM "in_table";

-- last snapshot rows to update actual flag
CREATE OR REPLACE TABLE last_curr_records AS
SELECT \${snap_table_cols}
       , "snapshot_date"
       , 0 AS "actual"
       \${is_deleted_flag}
FROM "curr_snapshot"
WHERE "actual" = 1 ;

CREATE OR REPLACE TABLE deleted_records_snapshot AS
SELECT \${snap_table_cols_w_alias}
       , \$CURR_TIMESTAMP_TXT AS "snapshot_date"
       , 1 AS "actual"
       , 1 AS "is_deleted"
FROM "curr_snapshot" snap
LEFT JOIN "in_table" INPUT ON snap."id"=input."ID"
WHERE snap."actual" = 1
  AND input."ID" IS NULL;

-- final snapshot table

CREATE OR REPLACE TABLE "final_snapshot" AS
SELECT \${snap_primary_key_lower}
          ,\${snap_table_cols}
          ,\${snap_default_cols}
FROM last_curr_records
\${deleted_snap_query}
UNION
SELECT     \${snap_primary_key}
          ,\${input_table_cols}
          ,\${snap_default_cols}
FROM records_snapshot ;
SQL;
