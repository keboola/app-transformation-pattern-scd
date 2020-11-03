<?php

declare(strict_types=1);

return <<< SQL

SET CURRENT_DATE =  (SELECT CONVERT_TIMEZONE('\${timezone}', current_timestamp()))::DATE;
SET CURRENT_TIMESTAMP =  (SELECT CONVERT_TIMEZONE('\${timezone}', current_timestamp())::TIMESTAMP_NTZ);
SET CURRENT_DATE_TXT =  (SELECT TO_CHAR(\$CURRENT_DATE, 'YYYY-MM-DD'));
SET CURRENT_TIMESTAMP_TXT =  (SELECT TO_CHAR(\$CURRENT_TIMESTAMP, 'YYYY-MM-DD HH:Mi:SS'));

-- actual snapshot
CREATE OR REPLACE TABLE "records" AS
SELECT \${input_table_cols}
       , \$CURRENT_TIMESTAMP_TXT AS "snapshot_date"
       , 1 AS "actual"
       , 0 AS "is_deleted"
FROM "input_table";

-- last snapshot rows to update actual flag
CREATE OR REPLACE TABLE "last_current_records" AS
SELECT \${snap_table_cols}
       , "snapshot_date"
       , 0 AS "actual"
       \${is_deleted_flag}
FROM "current_snapshot"
WHERE "actual" = 1 ;

CREATE OR REPLACE TABLE "deleted_records" AS
SELECT \${snap_table_cols_w_alias}
       , \$CURRENT_TIMESTAMP_TXT AS "snapshot_date"
       , 1 AS "actual"
       , 1 AS "is_deleted"
FROM "current_snapshot" snap
LEFT JOIN "input_table" input ON \${snap_input_join_condition}
WHERE snap."actual" = 1 AND input.\${input_random_col} IS NULL;

-- final snapshot table

CREATE OR REPLACE TABLE "new_snapshot" AS
SELECT \${snap_primary_key_lower}
          ,\${snap_table_cols}
          ,\${snap_default_cols}
FROM "last_current_records"
\${deleted_snap_query}
UNION
SELECT     \${snap_primary_key}
          ,\${input_table_cols}
          ,\${snap_default_cols}
FROM "records" ;
SQL;
