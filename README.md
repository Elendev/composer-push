# Push command for composer
This composer plugin provide a `composer push` command that allow to push the current package into a distant composer repository.

Currently supported repositories are:
 * [JFrog Artifactory](https://jfrog.com/artifactory/)
 * Nexus, with [nexus-repository-composer](https://github.com/sonatype-nexus-community/nexus-repository-composer).

## Installation
```bash
 $ composer require elendev/composer-push
 ```

**Important note**

This plugin is the continuation of `elendev/nexus-composer-push`, you have to migrate to this one if you haven't done it yet.

## Usage
Many of the options are optional since they can be added directly to the `composer.json` file.
```bash
 # At the root of your directory
 $ composer push [--name=<package name>] \
   [--url=<URL to the composer repository>] \
   [--type=<Type of repository, nexus by default, artifactory available too>]
   [--repository=<the repository you want to save, use this parameter if you want to control which repository to upload to by command-line parameter>] \
   [--username=USERNAME] \
   [--password=PASSWORD] \
   [--ignore=test.php]\
   [--ignore=foo/]\
   [--ignore-by-git-attributes]\
   [--src-type=<The type of repository used for source code: git, svn, ... which will be added to source tag of composer package>]\
   [--src-url=<URL of the source code repository which will be added to source tag of composer package>]\
   [--src-ref=<The reference to the current code version for this package which will be added to source tag of composer package>]\
   [--keep-vendor=<Keep vendor directory when creating zip>]
   [--ssl-verify=true/]\
   [--access-token=<ACCESS_TOKEN added in Bearer>]
   <version>

If <version> is not set, `composer.json` version will be used.
   
 # Example 
 $ composer push --username=admin --password=admin123 --url=http://localhost:8081/repository/composer --ignore=test.php --ignore=foo/ --src-type=git --src-url="$(git remote get-url origin)" --src-ref="$(git rev-parse HEAD)" 0.0.1
 
 # Example of use --repository
 # you need firstly configure multi repositories in composer.json of the project.
 # Please refer to Configuration below (multi repository configuration format) for configuration method
 # The component will be uploaded to the first repository whose's name value matching -- repository value
 # If there is no matching between the value of repository name and the value of -- repository, the upload will fail with a prompt
 $ composer push --username=admin --password=admin123 --repository=prod --ignore=test.php --ignore=foo/ 0.0.1
 ```

## Configuration
It's possible to add some configurations inside the `composer.json` file
```json
{
    "extra": {
        "push": {
            "url": "http://localhost:8081/repository/composer",
            "type": "nexus",
            "ssl-verify": true,
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
Above configuration may be called unique repository configuration format, as you can only configue one repository in composer.json.  

In practice, for security reasons, different versions of component code, such as production and development, often apply different deployment policy, such as disable redeploy for the production version and allow redeploy for the development version, so they need to be stored in different repositories.
For versions later than 0.1.5, the command-line parameter -- repository is introduced to meet this requirement. To enable the -- repository parameter, the composer.json file needs to be in the following format:
```json
{
    "extra": {
        "push": [{
            "name": "prod",
            "type": "artifactory",
            "url": "https://jfrog-art.com/artifactory/composer-local/",
            "username": "admin",
            "password": "admin123",
            "ignore-by-git-attributes": true,
            "ignore": [
                "test.php",
                "foo/"
            ]
        }, {
            "name": "dev",
            "url": "http://localhost:8081/repository/composer-devs",
            "username": "admin",
            "password": "admin123",
            "ignore-by-git-attributes": true,
            "ignore": [
                "test.php",
                "foo/"
            ]
        }]
    }
}
```
Above configuration may be called multi repository configuration format.  

The new version continues to support parsing the unique repository configuration format, but remember that you cannot use the -- repository command line argument in this scenario.  

The `username` and `password` can be specified in the `auth.json` file on a per-user basis with the [authentication mechanism provided by Composer](https://getcomposer.org/doc/articles/http-basic-authentication.md).

## Providers
Specificity for some of the providers.

### Nexus

***Source type, URL, reference***

This is an optional part that can be added to the composer.json file provided for the package which can contain the source reference for this version.
This option is useful in case you have a source manager and you would like to have a direct link to the source of an specific version.
The example above given will read the last commit ID from git and the remote address from git as well which is quiet simple and useful.

### Artifactory

**Tokens**

Tokens are currently supported when used as password. The username is still required for it to work.

Standalone tokens are currently not supported.

Example of token usage:
```json
{
    "extra": {
        "push": {
            "url": "https://jfrog-art.com/artifactory/composer-local",
            "type": "artifactory",
            "username": "<username>",
            "password": "<Authentication token for the user username>"
        }
    }
}
```
