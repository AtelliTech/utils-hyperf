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

## Model Generator
This generator is used to create model class by particular table name.

### Getting Start
#### Setup database connection
#### Usage
```
$ php bin/hyperf.php at:gen:model {table} --namespace={custom namespace} --path={generated file path} --connection={db profile name}
```

#### Options
- table
Table name
- connection
Database component id
- path
Store path of model class file
- namespace
Namespace of model class
