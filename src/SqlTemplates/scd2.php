<?php

declare(strict_types=1);

return <<< SQL
    SET CURRENT_DATE = (SELECT CONVERT_TIMEZONE('\${timezone}', current_timestamp()))::DATE;
    SET CURRENT_TIMESTAMP = (SELECT CONVERT_TIMEZONE('\${timezone}', current_timestamp())::TIMESTAMP_NTZ);
    SET CURRENT_DATE_TXT = (SELECT TO_CHAR(\$CURRENT_DATE, 'YYYY-MM-DD'));
    SET CURRENT_TIMESTAMP_TXT = (SELECT TO_CHAR(\$CURRENT_TIMESTAMP, 'YYYY-MM-DD HH:Mi:SS'));


    CREATE OR REPLACE TABLE "changed_records" AS
    WITH
        diff_records AS (
            SELECT
                \${input_table_cols_w_alias}
            FROM "input_table" input
            MINUS
            SELECT
                \${snap_table_cols_w_alias}
            FROM "current_snapshot" snap
            WHERE
                "actual" = 1
        )
    SELECT
        \${input_table_cols}

      , \${current_date_value}   AS "start_date"
      , '9999-12-31 00:00:00' AS "end_date"
      , 1                     AS "actual"
      , 0 AS "is_deleted"
    FROM diff_records;

    CREATE OR REPLACE TABLE "deleted_records" AS
    SELECT
        \${snap_table_cols_w_alias},
        snap."start_date" AS "start_date",
        \${actual_deleted_timestamp} AS "end_date",
        \${actual_deleted_value} AS "actual",
        1 AS "is_deleted"
    FROM
        "current_snapshot" snap
    LEFT JOIN "input_table" input
        ON \${snap_input_join_condition}
    WHERE
        snap."actual" = 1 AND input.\${input_random_col} IS NULL;

    CREATE OR REPLACE TABLE "updated_snapshots" AS
    SELECT
        \${snap_table_cols_w_alias}

      , snap."start_date"
      , \${current_date_value} AS "end_date"
      , 0                   AS "actual"
      , 0 AS "is_deleted"
    FROM
        "current_snapshot" snap
            JOIN "changed_records" input
                 ON \${snap_input_join_condition}
    WHERE
        snap."actual" = 1;

    CREATE OR REPLACE TABLE "new_snapshot" AS
    SELECT
    \${snap_primary_key_lower}
      ,\${snap_table_cols}
      ,\${snap_default_cols}
    FROM "deleted_records"
    UNION
    SELECT
    \${snap_primary_key_lower}
      ,\${snap_table_cols}
      ,\${snap_default_cols}
    FROM "updated_snapshots"
    UNION
    SELECT
    \${snap_primary_key}
      ,\${input_table_cols}
      ,\${snap_default_cols}
    FROM "changed_records"
    ;
SQL;
