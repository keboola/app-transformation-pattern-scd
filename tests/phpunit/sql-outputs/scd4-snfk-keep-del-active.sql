-- SCD4: This method snapshot full current state of the data to the snapshot table. --

-- The start and end dates DO NOT contain the time ("use_datetime" = false). --
SET CURRENT_DATE = (SELECT CONVERT_TIMEZONE('UTC', current_timestamp()))::DATE;

SET CURRENT_DATE_TXT = (SELECT TO_CHAR($CURRENT_DATE, 'YYYY-MM-DD'));

-- Last state: Actual snapshot of the all rows  --
CREATE TABLE "last_state" AS
    SELECT
        -- Monitored parameters. --
        input."Pk1" AS "pk1", input."pk2" AS "pk2", input."name" AS "name", input."Age" AS "age", input."Job" AS "job",
        -- The snapshot date is set to now. --
        $CURRENT_DATE_TXT AS "snapshot_date",
        -- Actual flag is set to "1". --
        1 AS "actual"
    FROM "input_table" input;

-- Previous state: Set actual flag to "0" in the previous version of the records. --
CREATE TABLE "previous_state" AS
    SELECT
        -- Monitored parameters. --
        snapshot."pk1", snapshot."pk2", snapshot."name", snapshot."age", snapshot."job",
        -- The snapshot date is preserved. --
        "snapshot_date",
        -- Actual flag is set to "0". --
        0  AS "actual"
    FROM "current_snapshot" snapshot
    WHERE
        -- Only the last results are modified. --
        snapshot."actual" = 1
        -- Exclude records with the current date (and therefore with the same PK). --
        -- This can happen if time is not part of the date, eg. "2020-11-04". --
        -- Row for this PK is then already included in the "last_state". --
        -- TLDR: for each PK, we can have max one row in the new snapshot. --
        AND snapshot."snapshot_date" != $CURRENT_DATE_TXT;

-- Deleted records are generated: --
-- if "has_deleted_flag" = true OR "keep_del_active" = true --
-- Detection: Deleted records have actual flag set to "1" --
-- in the snapshot table, but are missing in input table. --
CREATE TABLE "deleted_records" AS
    SELECT
        snapshot."pk1", snapshot."pk2", snapshot."name", snapshot."age", snapshot."job",
        -- The snapshot date is set to now. --
        $CURRENT_DATE_TXT AS "snapshot_date",
        -- The actual flag is set to "1" ("keep_del_active" = true). --
        1 AS "actual"
    FROM "current_snapshot" snapshot
    LEFT JOIN "input_table" input ON snapshot."pk1" = input."Pk1" AND snapshot."pk2" = input."pk2"
    WHERE
        snapshot."actual" = 1 AND
        input."Pk1" IS NULL;

-- Merge partial results to the new snapshot. --
-- Incremental loading is used to load table in the storage, --
-- so old rows with the same primary key are overwritten. --
CREATE TABLE "new_snapshot" AS
    -- New last state: --
    SELECT
        CONCAT("pk1", '|', "pk2", '|', "snapshot_date") AS "snapshot_pk",
        "pk1", "pk2", "name", "age", "job", "snapshot_date", "actual"
    FROM "last_state"
        UNION
    -- Deleted records: --
    SELECT
        CONCAT("pk1", '|', "pk2", '|', "snapshot_date") AS "snapshot_pk",
        "pk1", "pk2", "name", "age", "job", "snapshot_date", "actual"
    FROM "deleted_records"
        UNION
    -- Modified previous state: --
    SELECT
        CONCAT("pk1", '|', "pk2", '|', "snapshot_date") AS "snapshot_pk",
        "pk1", "pk2", "name", "age", "job", "snapshot_date", "actual"
    FROM "previous_state";
