-- SCD4: This method snapshot full current state of the data to the snapshot table. --

-- The start and end dates DO NOT contain the time ("use_datetime" = false). --
SELECT
'date' AS "key",
CONVERT(VARCHAR(10), CONVERT(DATETIMEOFFSET, CAST(CURRENT_TIMESTAMP AS DATE)) AT TIME ZONE 'UTC', 20) AS "value"
INTO "properties";

-- Last state: Actual snapshot of the all rows  --
SELECT
    -- Monitored parameters. --
    input."Pk1" AS "pk1", input."pk2" AS "pk2", input."name" AS "name", input."Age" AS "age", input."Job" AS "job",
    -- The snapshot date is set to now. --
    (SELECT value FROM properties WHERE "key" = 'date') AS "snapshot_date",
    -- Actual flag is set to "1". --
    1 AS "actual"
INTO "last_state"
FROM "input_table" input;

-- Previous state: Set actual flag to "0" in the previous version of the records. --
SELECT
    -- Monitored parameters. --
    snapshot."pk1", snapshot."pk2", snapshot."name", snapshot."age", snapshot."job",
    -- The snapshot date is preserved. --
    "snapshot_date",
    -- Actual flag is set to "0". --
    0  AS "actual"
INTO "previous_state"
FROM "current_snapshot" snapshot
WHERE
    -- Only the last results are modified. --
    snapshot."actual" = 1
    -- Exclude records with the current date (and therefore with the same PK). --
    -- This can happen if time is not part of the date, eg. "2020-11-04". --
    -- Row for this PK is then already included in the "last_state". --
    -- TLDR: for each PK, we can have max one row in the new snapshot. --
    AND snapshot."snapshot_date" != (SELECT value FROM properties WHERE "key" = 'date');

-- Deleted records are generated: --
-- if "has_deleted_flag" = true OR "keep_del_active" = true --
-- Detection: Deleted records have actual flag set to "1" --
-- in the snapshot table, but are missing in input table. --
SELECT
    snapshot."pk1", snapshot."pk2", snapshot."name", snapshot."age", snapshot."job",
    -- The snapshot date is set to now. --
    (SELECT value FROM properties WHERE "key" = 'date') AS "snapshot_date",
    -- The actual flag is set to "1" ("keep_del_active" = true). --
    1 AS "actual"
INTO "deleted_records"
FROM "current_snapshot" snapshot
LEFT JOIN "input_table" input ON snapshot."pk1" = input."Pk1" AND snapshot."pk2" = input."pk2"
WHERE
    snapshot."actual" = 1 AND
    input."Pk1" IS NULL;

-- Merge partial results to the new snapshot. --
-- Incremental loading is used to load table in the storage, --
-- so old rows with the same primary key are overwritten. --
-- New last state: --
SELECT
    CONCAT("pk1", '|', "pk2", '|', "snapshot_date") AS "snapshot_pk",
    "pk1", "pk2", "name", "age", "job", "snapshot_date", "actual"
INTO "new_snapshot"
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
