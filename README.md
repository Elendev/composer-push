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
   [--username=USERNAME] \
   [--password=PASSWORD] \
   [--ignore=test.php]\
   [--ignore=foo/]\
   [--ignore-by-git-attributes]\
   [--src-type=<The type of repository used for source code: git, svn, ... which will be added to source tag of composer package>]\
   [--src-url=<URL of the source code repository which will be added to source tag of composer package>]\
   [--src-ref=<The reference to the current code version for this package which will be added to source tag of composer package>]\
   <version>
   
 # Example
 $ composer nexus-push --username=admin --password=admin123 --url=http://localhost:8081/repository/composer --ignore=test.php --ignore=foo/ --src-type=git --src-url="$(git remote get-url origin)" --src-ref="$(git rev-parse HEAD)" 0.0.1
 ```

## Configuration
It's possible to add some configurations inside the `composer.json` file:
```json
{
    "extra": {
        "nexus-push": {
            "url": "http://localhost:8081/repository/composer/",
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

## Source type, URL, reference
This is an optional part that can be added to the composer.json file provided for the package which can contain the source reference for this version.
This option is useful in case you have a source manager and you would like to have a direct link to the source of an specific version.
The example above given will read the last commit ID from git and the remote address from git as well which is quiet simple and useful.
