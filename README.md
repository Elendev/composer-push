# Nexus Push command for composer
This composer plugin provide a `composer nexus-push` command that allow to push the current package into a Nexus 
Composer repository hosted with [nexus-repository-composer](https://github.com/sonatype-nexus-community/nexus-repository-composer).

## Installation
```bash
 $ composer require elendev/nexus-composer-push
 ```

## Usage
Many of the options are optionnal since they can be added directly to the `composer.json` file.
```bash
 # At the root of your directory
 $ composer nexus-push [--name=<package name>] \
   [--url=<URL to the composer nexus repository>] \
   [--username=USERNAME] \
   [--password=PASSWORD] \
   <version>
   
 # Example
 $ composer nexus-push --username=admin --password=admin123 --url=http://localhost:8081/repository/composer 0.0.1
 ```

## Configuration
It's possible to add some configurations inside the `composer.json` file:
```json
{
    "extra": {
        "nexus-push": {
            "url": "http://localhost:8081/repository/composer/",
            "username": "admin",
            "password": "admin123"
        }
    }
}
```

The `username` and `password` can be specified in the `auth.json` file on a per-user basis with the [authentication mechanism provided by Composer](https://getcomposer.org/doc/articles/http-basic-authentication.md).
