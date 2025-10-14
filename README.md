# component-creator

```
composer create-project hyperf/component-creator
```
# utils-hyperf

The utilities for Hyperf

## Getting Start
### Requirements
- php8.3+

### Install
```
$ /lib/path/composer require atellitech/utils-hyperf
```

### Setup database connection


---

## Model Generator
This generator is used to create model class by particular table name.


### Usage
```
$ php bin/hyperf.php at:gen:model [--disable-event-dispatcher] [--] <table> [<namespace> [<path> [<connection>]]]
```

### Options
- table
Table name
- connection
Database component id
- path
Store path of model class file
- namespace
Namespace of model class

---

## Repository, ValueObject and Validator Generator
Gernerate repository, value object, validator by table and outfile file into specific domain.


### Usage
```
$ php bin/hyperf.php at:gen:[repo|vo|validator] [--disable-event-dispatcher] [--] <table> [<domain> [<connection>]]
```

### Options
- table
Table name
- domain
DDD Domain name
