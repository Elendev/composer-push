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
   [--repository=<the repository you want to save, use this parameter if you want to control which repository to upload to by command-line parameter>] \
   [--username=USERNAME] \
   [--password=PASSWORD] \
   [--ignore=test.php]\
   [--ignore=foo/]\
   [--ignore-by-git-attributes]
   <version>
   
 # Example 
 $ composer nexus-push --username=admin --password=admin123 --url=http://localhost:8081/repository/composer --ignore=test.php --ignore=foo/ 0.0.1
 # use repo-type Example 
 # concreate repository name is configured in composer.json of the project,see value of key "repo-list" in the bellow Configuration part
 # if --repo-type is not offered, reposotory name is setted by param --url as above exapmple shown 
 $ composer nexus-push --username=admin --password=admin123 --url=http://localhost:8081/repository --repo-type=prod --ignore=test.php --ignore=foo/ 0.0.1
 ```

## Configuration
It's possible to add some configurations inside the `composer.json` file
```json
{
    "extra": {
        "nexus-push": {
            "url": "http://localhost:8081/repository/",
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
Above configuration may be called unique repository configuration format, as you can only configue one nexus repository in composer.json.  

In practice, for security reasons, different versions of component code, such as production and development, often apply different deployment policy, such as disable redeploy for the production version and allow redeploy for the development version, so they need to be stored in different nexus repositories.
For versions later than 0.1.5, the command-line parameter -- repository is introduced to meet this requirement. To enable the -- repository parameter, the composer.json file needs to be in the following format:
```json
{
	"extra": {
		"nexus-push": [{
			"name": "prod",
			"url": "http://localhost:8081/repository/composer-releases",
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
Above configuration may be called unique repository configuration format.  

The new version continues to support parsing the old unique repository configuration format, but remember that you cannot use the -- repository command line argument.  

The `username` and `password` can be specified in the `auth.json` file on a per-user basis with the [authentication mechanism provided by Composer](https://getcomposer.org/doc/articles/http-basic-authentication.md).
