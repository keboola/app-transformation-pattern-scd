-- SCD2: This method tracks historical data --
-- by creating new records for new/modified data in the snapshot table. --

-- The start and end dates contain the time ("use_datetime" = true). --
SET CURRENT_TIMESTAMP = (SELECT DATEADD(DAY,7,CONVERT_TIMEZONE('UTC',CURRENT_TIMESTAMP()))::TIMESTAMP_NTZ);

SET CURRENT_TIMESTAMP_MINUS_SECOND = DATEADD(SECOND, -1, $CURRENT_TIMESTAMP);

-- Changed records: Input table rows, EXCEPT same rows present in the last snapshot. --
CREATE TABLE "changed_records" AS
    WITH "diff_records" AS (
        -- Actual state. --
        SELECT input."Pk1" AS "pk1", input."pk2" AS "pk2", input."name" AS "name", input."Age" AS "age", input."Job" AS "job"
        FROM "input_table" input

        EXCEPT

        -- The last snapshot. --
        SELECT snapshot."pk1", snapshot."pk2", snapshot."name", snapshot."age", snapshot."job"
        FROM "current_snapshot" snapshot
        WHERE "custom_actual" = 1
    )
    SELECT
        -- Monitored parameters. --
        "pk1", "pk2", "name", "age", "job",
        -- The start date is set to now. --
        $CURRENT_TIMESTAMP::TIMESTAMP_NTZ AS "custom_start_date",
        -- The end date is set to infinity. --
        '9999-12-31 00:00:00'::TIMESTAMP_NTZ AS "custom_end_date",
        -- Actual flag is set to "1". --
        1 AS "custom_actual"
    FROM "diff_records";

-- Updated records: Set actual flag to "0" in the previous version. --
CREATE TABLE "updated_records" AS
    SELECT
        -- Monitored parameters. --
        snapshot."pk1", snapshot."pk2", snapshot."name", snapshot."age", snapshot."job",
        -- The start date is preserved. --
        snapshot."custom_start_date",
        -- The end date is set to now. --
        $CURRENT_TIMESTAMP_MINUS_SECOND::TIMESTAMP_NTZ AS "custom_end_date",
        -- Actual flag is set to "0", because the new version exists. --
        0 AS "custom_actual"
    FROM "current_snapshot" snapshot
    -- Join "changed_records" and "snapshot" table on the defined primary key
    JOIN "changed_records" changed ON
    snapshot."pk1" = changed."pk1" AND snapshot."pk2" = changed."pk2"

    WHERE
        -- Only previous actual results are modified. --
        snapshot."custom_actual" = 1
        -- Exclude records with the current date (and therefore with the same PK). --
        -- This can happen if time is not part of the date, eg. "2020-11-04". --
        -- Row for this PK is then already included in the "last_state". --
        -- TLDR: for each PK, we can have max one row in the new snapshot. --
        AND snapshot."custom_start_date" != $CURRENT_TIMESTAMP;

-- Deleted records are missing in input table, but have actual "1" in last snapshot. --
CREATE TABLE "deleted_records" AS
    SELECT
        -- Values of the monitored parameters. --
        snapshot."pk1", snapshot."pk2", snapshot."name", snapshot."age", snapshot."job",
        -- The start date is unchanged, it is part of the PK, --
        -- so old values are overwritten by incremental loading. --
        snapshot."custom_start_date",
        -- The end date is set to "$CURRENT_TIMESTAMP_MINUS_SECOND" ("keep_del_active" = false). --
        $CURRENT_TIMESTAMP_MINUS_SECOND::TIMESTAMP_NTZ AS "custom_end_date",
        -- The actual flag is set to "0" ("keep_del_active" = false). --
        0 AS "custom_actual"
    FROM "current_snapshot" snapshot
    -- Join input and snapshot table on the defined primary key. --
    LEFT JOIN "input_table" input ON snapshot."pk1" = input."Pk1" AND snapshot."pk2" = input."pk2"
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
        CONCAT("pk1", '|', "pk2", '|', "custom_start_date") AS "snapshot_pk",
        "pk1", "pk2", "name", "age", "job", "custom_start_date", "custom_end_date", "custom_actual"
    FROM "changed_records"
        UNION
    -- Deleted records: --
    SELECT
        CONCAT("pk1", '|', "pk2", '|', "custom_start_date") AS "snapshot_pk",
        "pk1", "pk2", "name", "age", "job", "custom_start_date", "custom_end_date", "custom_actual"
    FROM "deleted_records"
        UNION
    -- Updated previous versions of the changed records: --
    SELECT
        CONCAT("pk1", '|', "pk2", '|', "custom_start_date") AS "snapshot_pk",
        "pk1", "pk2", "name", "age", "job", "custom_start_date", "custom_end_date", "custom_actual"
    FROM "updated_records"; 