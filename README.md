# CakePHP-TableMaintenanceShell

A CakePHP v2.x console tool to run common MySQL database maintenance queries, including:

* CHECK
* ANALYZE
* OPTIMIZE
* REPAIR

Tables are locked with a `READ` lock for the `check` action, or `WRITE` locks for all other actions. This is to mimic the behavior of `mysqlcheck` as described [here](https://dev.mysql.com/doc/refman/5.6/en/mysqlcheck.html).

## Requirements

* CakePHP 2.x
* PHP 5.3+


## Installation

```shell
$ composer require loadsys/cakephp-tablemaintenanceshell
```

## Usage

```shell
Console/cake TableMaintenance.table_maintenance run {action} {table|ALL}
```

The `{action}` param can be any one of:

* `check`
* `analyze`
* `optimize`
* `repair`

The `{table}` param can be any valid table name, or the special word `ALL` meaning all tables.

Adding the `--quiet` or `-q` flag will suppress output unless an error exists.


## Contributing

### Reporting Issues

Please use [GitHub Isuses](https://github.com/loadsys/CakePHP-TableMaintenanceShell/issues) for listing any known defects or issues.

### Development

When developing this plugin, please fork and issue a PR for any new development.

## License

[MIT](https://github.com/loadsys/CakePHP-TableMaintenanceShell/blob/master/LICENSE.md)


## Copyright

[Loadsys Solutions](http://www.loadsys.com) 2018
