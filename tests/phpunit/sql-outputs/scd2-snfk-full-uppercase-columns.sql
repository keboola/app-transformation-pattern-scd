-- SCD2: This method tracks historical data --
-- by creating new records for new/modified data in the snapshot table. --

-- The start and end dates contain the time ("use_datetime" = true). --
SET CURRENT_TIMESTAMP = (SELECT CONVERT_TIMEZONE('UTC', current_timestamp())::TIMESTAMP_NTZ);

SET CURRENT_TIMESTAMP_TXT = (SELECT TO_CHAR($CURRENT_TIMESTAMP, 'YYYY-MM-DD HH:Mi:SS'));

SET CURRENT_TIMESTAMP_TXT_MINUS_SECOND = TO_CHAR(DATEADD(SECOND, -1, $CURRENT_TIMESTAMP), 'YYYY-MM-DD HH:Mi:SS');

-- Changed records: Input table rows, EXCEPT same rows present in the last snapshot. --
CREATE TABLE "changed_records" AS
    WITH "diff_records" AS (
        -- Actual state. --
        SELECT input."Pk1" AS "PK1", input."pk2" AS "PK2", input."name" AS "NAME", input."Age" AS "AGE", input."Job" AS "JOB"
        FROM "input_table" input

        EXCEPT

        -- The last snapshot. --
        SELECT snapshot."PK1", snapshot."PK2", snapshot."NAME", snapshot."AGE", snapshot."JOB"
        FROM "current_snapshot" snapshot
        WHERE "CUSTOM_ACTUAL" = 1
    )
    SELECT
        -- Monitored parameters. --
        "PK1", "PK2", "NAME", "AGE", "JOB",
        -- The start date is set to now. --
        $CURRENT_TIMESTAMP_TXT AS "CUSTOM_START_DATE",
        -- The end date is set to infinity. --
        '9999-12-31 00:00:00' AS "CUSTOM_END_DATE",
        -- Actual flag is set to "1". --
        1 AS "CUSTOM_ACTUAL",
        -- IsDeleted flag is set to "0". --
        0 AS "CUSTOM_IS_DELETED"
    FROM "diff_records";

-- Updated records: Set actual flag to "0" in the previous version. --
CREATE TABLE "updated_records" AS
    SELECT
        -- Monitored parameters. --
        snapshot."PK1", snapshot."PK2", snapshot."NAME", snapshot."AGE", snapshot."JOB",
        -- The start date is preserved. --
        snapshot."CUSTOM_START_DATE",
        -- The end date is set to now. --
        $CURRENT_TIMESTAMP_TXT_MINUS_SECOND AS "CUSTOM_END_DATE",
        -- Actual flag is set to "0", because the new version exists. --
        0 AS "CUSTOM_ACTUAL",
        -- IsDeleted flag is set to "0", because the new version exists. --
        0 AS "CUSTOM_IS_DELETED"
    FROM "current_snapshot" snapshot
    -- Join "changed_records" and "snapshot" table on the defined primary key
    JOIN "changed_records" changed ON
    snapshot."PK1" = changed."PK1" AND snapshot."PK2" = changed."PK2"

    WHERE
        -- Only previous actual results are modified. --
        snapshot."CUSTOM_ACTUAL" = 1
        -- Exclude records with the current date (and therefore with the same PK). --
        -- This can happen if time is not part of the date, eg. "2020-11-04". --
        -- Row for this PK is then already included in the "last_state". --
        -- TLDR: for each PK, we can have max one row in the new snapshot. --
        AND snapshot."CUSTOM_START_DATE" != $CURRENT_TIMESTAMP_TXT;

-- Deleted records are missing in input table, but have actual "1" in last snapshot. --
CREATE TABLE "deleted_records" AS
    SELECT
        -- Values of the monitored parameters. --
        snapshot."PK1", snapshot."PK2", snapshot."NAME", snapshot."AGE", snapshot."JOB",
        -- The start date is unchanged, it is part of the PK, --
        -- so old values are overwritten by incremental loading. --
        snapshot."CUSTOM_START_DATE",
        -- The end date is set to "'9999-12-31 00:00:00'" ("keep_del_active" = true). --
        '9999-12-31 00:00:00' AS "CUSTOM_END_DATE",
        -- The actual flag is set to "1" ("keep_del_active" = true). --
        1 AS "CUSTOM_ACTUAL",
        -- IsDeleted flag is set to "1". --
        1 AS "CUSTOM_IS_DELETED"
    FROM "current_snapshot" snapshot
    -- Join input and snapshot table on the defined primary key. --
    LEFT JOIN "input_table" input ON snapshot."PK1" = input."Pk1" AND snapshot."PK2" = input."pk2"
    WHERE
        -- Deleted records are calculated only from the actual records. --
        snapshot."custom_actual" = 1 AND
        -- Record is no more present in the input table. --
        input."Pk1" IS NULL;

-- Merge partial results to the new snapshot. --
-- Incremental loading is used to load table in the storage, --
-- so old rows with the same primary key are overwritten. --
CREATE TABLE "new_snapshot" AS
    -- Changed records: --
    SELECT
        CONCAT("PK1", '|', "PK2", '|', "CUSTOM_START_DATE") AS "SNAPSHOT_PK",
        "PK1", "PK2", "NAME", "AGE", "JOB", "CUSTOM_START_DATE", "CUSTOM_END_DATE", "CUSTOM_ACTUAL", "CUSTOM_IS_DELETED"
    FROM "changed_records"
        UNION
    -- Deleted records: --
    SELECT
        CONCAT("PK1", '|', "PK2", '|', "CUSTOM_START_DATE") AS "SNAPSHOT_PK",
        "PK1", "PK2", "NAME", "AGE", "JOB", "CUSTOM_START_DATE", "CUSTOM_END_DATE", "CUSTOM_ACTUAL", "CUSTOM_IS_DELETED"
    FROM "deleted_records"
        UNION
    -- Updated previous versions of the changed records: --
    SELECT
        CONCAT("PK1", '|', "PK2", '|', "CUSTOM_START_DATE") AS "SNAPSHOT_PK",
        "PK1", "PK2", "NAME", "AGE", "JOB", "CUSTOM_START_DATE", "CUSTOM_END_DATE", "CUSTOM_ACTUAL", "CUSTOM_IS_DELETED"
    FROM "updated_records";
