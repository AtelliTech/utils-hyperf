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

---

## API Spec Page Generator
Generate API documentation page by namespace and path

### Usage
```
$ php bin/hyperf.php at:gen:apidoc [options] [--] [<namespace> [<path>]]
```

### Options
- namespace
Name of class namespace
- path
Path of generated file

---

## Generate dependency files
Generate dependency file for Repository, Service class by scaning specific path which the default is "{root path}/app".
The generated file named "autoload_denpendencies.php" which locating at "config/autoload/"

### Usage
#### Step1 Add require file script into "config/autoload/dependencies.php"
```php
$src = __DIR__ . '/autoload_dependencies.php';
$autoDependencies = [];
if (file_exists($src)) {
    $autoDependencies = require $src;
}

return array_merge((array) $autoDependencies, [
    // other manual dependencies
]);
```

#### Step2 Execute the command belows
```
$ php bin/hyperf.php at:gen:di [options] [--] [<path>]
```

##### Options
- path
Path of generated file

#### Step3 Setup script into "composer.json"
```json=
{
    // other configuration

    "scripts": {
        // ... other script command
        "di": "php bin/hyperf.php at:gen:di"
    }
}
```
