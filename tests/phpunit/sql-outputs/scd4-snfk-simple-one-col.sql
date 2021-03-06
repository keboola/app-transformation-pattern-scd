-- SCD4: This method snapshot full current state of the data to the snapshot table. --

-- The start and end dates DO NOT contain the time ("use_datetime" = false). --
SET CURRENT_DATE = (SELECT CONVERT_TIMEZONE('UTC', current_timestamp()))::DATE;

SET CURRENT_DATE_TXT = (SELECT TO_CHAR($CURRENT_DATE, 'YYYY-MM-DD'));

-- Last state: Actual snapshot of the all rows  --
CREATE TABLE "last_state" AS
    SELECT
        -- Monitored parameters. --
        input."Pk1" AS "pk1", input."name" AS "name",
        -- The snapshot date is set to now. --
        $CURRENT_DATE_TXT AS "snapshot_date",
        -- Actual flag is set to "1". --
        1 AS "actual"
    FROM "input_table" input;

-- Previous state: Set actual flag to "0" in the previous version of the records. --
CREATE TABLE "previous_state" AS
    SELECT
        -- Monitored parameters. --
        snapshot."pk1", snapshot."name",
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

-- Deleted records are not included in the snapshot, --
-- "has_deleted_flag" = false AND "keep_del_active" = false --
-- Merge partial results to the new snapshot. --
-- Incremental loading is used to load table in the storage, --
-- so old rows with the same primary key are overwritten. --
CREATE TABLE "new_snapshot" AS
    -- New last state: --
    SELECT
        CONCAT("pk1", '|', "snapshot_date") AS "snapshot_pk",
        "pk1", "name", "snapshot_date", "actual"
    FROM "last_state"
        UNION
    -- Modified previous state: --
    SELECT
        CONCAT("pk1", '|', "snapshot_date") AS "snapshot_pk",
        "pk1", "name", "snapshot_date", "actual"
    FROM "previous_state";
