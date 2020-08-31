# Snapshot (SCD) SQL generator

[![Build Status](https://travis-ci.com/keboola/app-transformation-pattern-scd.svg?branch=master)](https://travis-ci.com/keboola/app-transformation-pattern-scd)

> Generate code for SCD type 2 or 4 on any input dataset.

## Configuration Options

- scd_type: enum {`scd2`, `scd4`} (required)
- primary_key: string (required)
- monitored_parameters: string (optional)
- deleted_flag: boolean (optional) - default value is `false`
- use_datetime: boolean (optional) - default value is `false`
- keep_del_active: boolean (optional) - default value is `false`
- timezone: string (required)

## SCD type
Type of the snapshot generated snapshot / slowly changing dimension.

### SCD type 2
This is a most common type of slowly changing dimension. It produces a row whenever some of the monitored attributes is changed. For more information about type 2 [see here](https://en.wikipedia.org/wiki/Slowly_changing_dimension#Type_2:_add_new_row).

### SCD type 4
This is the simplest type of snapshot, that tracks the state of the records each run (day), regardless the actual changes.

The `Monitored columns` parameter in this case works just a selector of which column should be included in the snapshot.

## Example configuration

#### SCD2 type
```json
{
  "action": "generate",
  "parameters": {
    "scd_type": "scd2",
    "primary_key": "zipcode",
    "timezone": "Europe/Prague"
  }
}
```

#### SCD4 type with monitored_parameters
```json
{
  "action": "generate",
  "parameters": {
    "scd_type": "scd4",
    "primary_key": "zipcode",
    "monitored_parameters": "usergender, usercity",
    "timezone": "Europe/Prague"
  }
}
```

## Development
 
Clone this repository and init the workspace with following command:

```
git clone https://github.com/keboola/app-transformation-pattern-scd
cd app-transformation-pattern-scd
docker-compose build
docker-compose run --rm dev composer install --no-scripts
```

Run the test suite using this command:

```
docker-compose run --rm dev composer tests
```
 