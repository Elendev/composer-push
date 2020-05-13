# Nexus Push command for composer
This composer plugin provide a `composer nexus-push` command that allow to push the current package into a Nexus 
Composer repository hosted with [nexus-repository-composer](https://github.com/sonatype-nexus-community/nexus-repository-composer).

## Installation
```bash
 $ composer require elendev/nexus-composer-push
 ```

## Usage
Many of the options are optional since they can be added directly to the `composer.json` file.
```bash
 # At the root of your directory
 $ composer nexus-push [--name=<package name>] \
   [--url=<URL to the composer nexus repository>] \
   [--repo-type=<the repository name you want to save, if you want to place development version and production version in different Nexus repositoryo>] \
   [--username=USERNAME] \
   [--password=PASSWORD] \
   [--ignore=test.php]\
   [--ignore=foo/]\
   [--ignore-by-git-attributes]
   <version>
   
 # Example 
 $ composer nexus-push --username=admin --password=admin123 --url=http://localhost:8081/repository/composer --ignore=test.php --ignore=foo/ 0.0.1
 # use repo-type Example 
 # concreate repository name is configured in composer.json of the project,see value of key "repo-list" key in the next Configuration part
 # if --repo-type is not offered, reposotory name is setted by param --url as above exapmple shown 
 $ composer nexus-push --username=admin --password=admin123 --url=http://localhost:8081/repository --repo-type=prod --ignore=test.php --ignore=foo/ 0.0.1
 ```

## Configuration
It's possible to add some configurations inside the `composer.json` file:
```json
{
    "extra": {
        "nexus-push": {
            "url": "http://localhost:8081/repository/composer/",
            "repo-list": {
                "dev": "composer-devs",
                "prod": "composer"
            },
            "username": "admin",
            "password": "admin123",
            "ignore-by-git-attributes": true,
            "ignore": [
                "test.php",
                "foo/"
            ]
        }
    }
}
```

The `username` and `password` can be specified in the `auth.json` file on a per-user basis with the [authentication mechanism provided by Composer](https://getcomposer.org/doc/articles/http-basic-authentication.md).
