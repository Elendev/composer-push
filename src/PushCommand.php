<?php


namespace Elendev\NexusComposerPush;

use Composer\Command\BaseCommand;
use Composer\IO\IOInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

class PushCommand extends BaseCommand
{

    /**
     * @var \GuzzleHttp\ClientInterface
     */
    private $client;

    protected function configure()
    {
        $this
          ->setName('nexus-push')
          ->setDescription('Initiate a push to a distant Nexus repository')
          ->setDefinition([
            new InputArgument('version', InputArgument::REQUIRED, 'The package version'),
            new InputOption('name', null, InputArgument::OPTIONAL, 'Name of the package (if different from the composer.json file)'),
            new InputOption('url', null, InputArgument::OPTIONAL, 'URL to the distant Nexus repository'),
            new InputOption(
                'username',
                null,
                InputArgument::OPTIONAL,
                'Username to log in the distant Nexus repository'
            ),
            new InputOption('password', null, InputArgument::OPTIONAL, 'Password to log in the distant Nexus repository'),
            new InputOption('ignore', 'i', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Directories and files to ignore when creating the zip'),
            new InputOption('ignore-dirs', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, '<error>DEPRECATED</error> Directories to ignore when creating the zip'),
            new InputOption('ignore-by-git-attributes', null, InputOption::VALUE_NONE, 'Ignore .gitattrbutes export-ignore directories when creating the zip'),
            new InputOption('ignore-by-composer', null, InputOption::VALUE_NONE, 'Ignore composer.json archive-exclude files and directories when creating the zip'),
            new InputOption('src-type', null, InputArgument::OPTIONAL, 'The source type (git/svn,...) pushed on composer on distant Nexus repository'),
            new InputOption('src-url', null, InputArgument::OPTIONAL, 'The source url pushed on composer on distant Nexus repository'),
            new InputOption('src-ref', null, InputArgument::OPTIONAL, 'The source reference pushed on composer on distant Nexus repository')
          ])
          ->setHelp(
              <<<EOT
The <info>nexus-push</info> command uses the archive command to create a ZIP
archive and send it to the configured (or given) nexus repository.
EOT
            )
        ;
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int|null|void
     * @throws \Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $fileName = tempnam(sys_get_temp_dir(), 'nexus-push') . '.zip';

        $packageName = $this->getPackageName($input);

        $subdirectory = strtolower(preg_replace(
            '/[^a-zA-Z0-9_]|\./',
            '-',
            $packageName . '-' . $input->getArgument('version')
        ));
        $sourceType = $input->getOption('src-type');
        $sourceUrl = $input->getOption('src-url');
        $sourceReference = $input->getOption('src-ref');
        $ignoredDirectories = $this->getIgnores($input);
        $this->getIO()
            ->write(
                'Ignore directories: ' . join(' ', $ignoredDirectories),
                true,
                IOInterface::VERY_VERBOSE
            );

        try {
            ZipArchiver::archiveDirectory(
                getcwd(),
                $fileName,
                $subdirectory,
                $ignoredDirectories,
                $this->getIO()
          );

            $url = $this->generateUrl(
                $input->getOption('url'),
                $packageName,
                $input->getArgument('version')
            );

            $this->getIO()
              ->write(
                  'Execute the Nexus Push for the URL ' . $url . '...',
                  true
              );

            $this->sendFile(
                $url,
                $fileName,
                $sourceType,
                $sourceUrl,
                $sourceReference,
                $input->getOption('username'),
                $input->getOption('password')
          );

            $this->getIO()
              ->write('Archive correctly pushed to the Nexus server');
        } finally {
            $this->getIO()
              ->write(
                  'Remove file ' . $fileName,
                  true,
                  IOInterface::VERY_VERBOSE
              );
            unlink($fileName);
        }
    }

    /**
     * @param string $url
     * @param string $name
     * @param string $version
     *
     * @return string URL to the repository
     */
    private function generateUrl($url, $name, $version)
    {
        if (empty($url)) {
            $url = $this->getNexusExtra('url');

            if (empty($url)) {
                throw new InvalidArgumentException('The option --url is required or has to be provided as an extra argument in composer.json');
            }
        }

        if (empty($name)) {
            $name = $this->getComposer(true)->getPackage()->getName();
        }

        if (empty($version)) {
            throw new InvalidArgumentException('The version argument is required');
        }

        // Remove trailing slash from URL
        $url = preg_replace('{/$}', '', $url);

        return sprintf('%s/packages/upload/%s/%s', $url, $name, $version);
    }

    /**
     * Try to send a file with the given username/password. If the credentials
     * are not set, try to send a simple request without credentials. If the
     * send fail with a 401, try to use the credentials that may be available
     * in an `auth.json` file or in the
     * `extra` section
     *
     * @param string $url URL to send the file to
     * @param string $filePath path to the file to send
     * @param string|null $sourceType the type which will be added as source in composer
     * @param string|null $sourceUrl the Url which will be added as source in composer
     * @param string|null $sourceReference the reference which will be added as source in composer
     * @param string|null $username
     * @param string|null $password
     *
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function sendFile(
        $url,
        $filePath,
        $sourceType = null,
        $sourceUrl = null,
        $sourceReference = null,
        $username = null,
        $password = null
    ) {
        if (!empty($username) && !empty($password)) {
            $this->postFile($url, $filePath, $sourceType, $sourceUrl, $sourceReference, $username, $password);
            return;
        } else {
            $credentials = [];

            if ($this->getNexusExtra('username') !== null && $this->getNexusExtra('password')) {
                $credentials['extra'] = [
                    'username' => $this->getNexusExtra('username'),
                    'password' => $this->getNexusExtra('password'),
                ];
            }


            if (preg_match(
                '{^(?:https?)://([^/]+)(?:/.*)?}',
                $url,
                $match
            ) && $this->getIO()->hasAuthentication($match[1])) {
                $auth = $this->getIO()->getAuthentication($match[1]);
                $credentials['auth.json'] = [
                    'username' => $auth['username'],
                    'password' => $auth['password'],
                ];
            }

            // In the case anything else works, try to connect without any credentials.
            $credentials['none'] = [];

            foreach ($credentials as $type => $credential) {
                $this->getIO()
                  ->write(
                      '[postFile] Trying credentials ' . $type,
                      true,
                      IOInterface::VERY_VERBOSE
                  );

                try {
                    if (empty($credential) || empty($credential['username']) || empty($credential['password'])) {
                        $this->getIO()
                          ->write(
                              '[postFile] Use no credentials',
                              true,
                              IOInterface::VERY_VERBOSE
                          );
                        $this->postFile($url, $filePath, $sourceType, $sourceUrl, $sourceReference);
                    } else {
                        $this->getIO()
                          ->write(
                              '[postFile] Use user ' . $credential['username'],
                              true,
                              IOInterface::VERY_VERBOSE
                          );
                        $this->postFile(
                            $url,
                            $filePath,
                            $sourceType,
                            $sourceUrl,
                            $sourceReference,
                            $credential['username'],
                            $credential['password']
                      );
                    }

                    return;
                } catch (ClientException $e) {
                    if ($e->getResponse()->getStatusCode() === '401') {
                        if ($type === 'none') {
                            $this->getIO()
                              ->write(
                                  'Unable to push on server (authentication required)',
                                  true,
                                  IOInterface::VERY_VERBOSE
                              );
                        } else {
                            $this->getIO()
                              ->write(
                                  'Unable to authenticate on server with credentials ' . $type,
                                  true,
                                  IOInterface::VERY_VERBOSE
                              );
                        }
                    } else {
                        $this->getIO()
                          ->writeError(
                              'A network error occured while trying to upload to nexus: ' . $e->getMessage(),
                              true,
                              IOInterface::QUIET
                          );
                    }
                }
            }
        }

        throw new \Exception('Impossible to push to remote repository, use -vvv to have more details');
    }

    /**
     * The file has to be uploaded by hand because of composer limitations
     * (impossible to use Guzzle functions.php file in a composer plugin).
     *
     * @param $url
     * @param $file
     * @param $sourceType
     * @param $sourceUrl
     * @param $sourceReference
     * @param $username
     * @param $password
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function postFile($url, $file, $sourceType = null, $sourceUrl = null, $sourceReference = null, $username = null, $password = null)
    {
        $options = [
            'debug' => $this->getIO()->isVeryVerbose(),
        ];
        if (!empty($sourceType) && !empty($sourceUrl) && !empty($sourceReference)) {
            $options['multipart'] = [
                [
                    'Content-Type' => 'application/zip',
                    'name' => 'package',
                    'contents' => fopen($file, 'r')
                ],
                [
                    'name' => 'src-type',
                    'contents' => $sourceType
                ],
                [
                    'name' => 'src-url',
                    'contents' => $sourceUrl
                ],
                [
                    'name' => 'src-ref',
                    'contents' => $sourceReference
                ]
            ];
        } else {
            $options['body'] = fopen($file, 'r');
        }

        if (!empty($username) && !empty($password)) {
            $options['auth'] = [$username, $password];
        }

        $this->getClient()->request('PUT', $url, $options);
    }

    /**
     * @throws FileNotFoundException
     * @return \GuzzleHttp\Client|\GuzzleHttp\ClientInterface
     */
    private function getClient()
    {
        if (empty($this->client)) {
            // https://github.com/composer/composer/issues/5998
            $composer = $this->getComposer(true);
            $homeDir = $composer->getConfig()->get('home');
            $vendorDir = $composer->getConfig()->get('vendor-dir');
            $autoload = "$vendorDir/autoload.php";

            // Show an error if the file wasn't found in the current project.
            if (!file_exists($autoload)) {
                throw new FileNotFoundException("vendor/autoload.php not found, did you run composer install?");
            }

            // Require the guzzle functions manually.
            $guzzlefunctions = "$vendorDir/guzzlehttp/guzzle/src/functions_include.php";
            if (!file_exists($guzzlefunctions)) {
                $guzzlefunctions = "$homeDir/vendor/guzzlehttp/guzzle/src/functions_include.php";
                if (!file_exists($guzzlefunctions)) {
                    throw new FileNotFoundException("$guzzlefunctions not found, is guzzle installed?");
                }
            }
            $guzzlepsr7functions = "$vendorDir/guzzlehttp/psr7/src/functions_include.php";
            if (!file_exists($guzzlepsr7functions)) {
                $guzzlepsr7functions = "$homeDir/vendor/guzzlehttp/psr7/src/functions_include.php";
                if (!file_exists($guzzlepsr7functions)) {
                    throw new FileNotFoundException("$guzzlepsr7functions not found, is guzzle installed?");
                }
            }
            $guzzlepromisesfunctions = "$vendorDir/guzzlehttp/promises/src/functions_include.php";
            if (!file_exists($guzzlepromisesfunctions)) {
                $guzzlepromisesfunctions = "$homeDir/vendor/guzzlehttp/promises/src/functions_include.php";
                if (!file_exists($guzzlepromisesfunctions)) {
                    throw new FileNotFoundException("$guzzlepromisesfunctions not found, is guzzle installed?");
                }
            }
            require $guzzlefunctions;
            require $guzzlepsr7functions;
            require $guzzlepromisesfunctions;
            require $autoload;

            $this->client = new Client();
        }
        return $this->client;
    }

    /**
     * Return the package name based on the given name or the real package name.
     *
     * @param \Symfony\Component\Console\Input\InputInterface|null $input
     *
     * @return string
     */
    private function getPackageName(InputInterface $input = null)
    {
        if ($input && $input->getOption('name')) {
            return $input->getOption('name');
        } else {
            return $this->getComposer(true)->getPackage()->getName();
        }
    }

    /**
     * Get the Nexus extra values if available
     *
     * @param $parameter
     * @param null $default
     *
     * @return array|string|null
     */
    private function getNexusExtra($parameter, $default = null)
    {
        $extras = $this->getComposer(true)->getPackage()->getExtra();

        if (!empty($extras['nexus-push'][$parameter])) {
            return $extras['nexus-push'][$parameter];
        } else {
            return $default;
        }
    }

    /**
     * Fetch any directories or files to be excluded from zip creation
     *
     * @param InputInterface $input
     * @return array
     */
    private function getIgnores(InputInterface $input)
    {
        // Remove after removal of --ignore-dirs option
        $deprecatedIgnores = $this->getDirectoriesToIgnore($input);

        $optionalIgnore = $input->getOption('ignore');
        $composerIgnores = $this->getNexusExtra('ignore', []);
        $gitAttrIgnores = $this->getGitAttributesExportIgnores($input);
        $composerJsonIgnores = $this->getComposerJsonArchiveExcludeIgnores($input);
        $defaultIgnores = ['vendor/'];

        $ignore = array_merge($deprecatedIgnores, $composerIgnores, $optionalIgnore, $gitAttrIgnores, $composerJsonIgnores, $defaultIgnores);
        return array_unique($ignore);
    }

    /**
     * @param InputInterface $input
     * @deprecated argument has been changed to ignore
     * @return array
     */
    private function getDirectoriesToIgnore(InputInterface $input)
    {
        $optionalIgnore = $input->getOption('ignore-dirs');
        $composerIgnores = $this->getNexusExtra('ignore-dirs', []);

        if (!empty($optionalIgnore)) {
            $this->getIO()->write('<error>The --ignore-dirs option has been deprecated. Please use --ignore instead</error>');
        }

        if (!empty($composerIgnores)) {
            $this->getIO()->write('<error>The ignore-dirs config option has been deprecated. Please use ignore instead</error>');
        }

        $ignore = array_merge($composerIgnores, $optionalIgnore);
        return array_unique($ignore);
    }

    private function getGitAttributesExportIgnores(InputInterface $input)
    {
        $option = $input->getOption('ignore-by-git-attributes');
        $extra = $this->getNexusExtra('ignore-by-git-attributes', false);
        if (!$option && !$extra) {
            return [];
        }

        $path = getcwd() . '/.gitattributes';
        if (!is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);
        $lines = explode(PHP_EOL, $contents);
        $ignores = [];
        foreach ($lines as $line) {
            if ($line = trim($line)) {
                // ignore if end with `export-ignore`
                $diff = strlen($line) - 13;
                if ($diff > 0 && strpos($line, 'export-ignore', $diff) !== false) {
                    $ignores[] = trim(trim(explode(' ', $line)[0]), DIRECTORY_SEPARATOR);
                }
            }
        }

        return $ignores;
    }

    private function getComposerJsonArchiveExcludeIgnores(InputInterface $input)
    {
        $option = $input->getOption('ignore-by-composer');
        $extra = $this->getNexusExtra('ignore-by-composer', false);
        if (!$option && !$extra) {
            return [];
        }

        $path = getcwd() . '/composer.json';
        
        $contents = file_get_contents($path);
        $jsonContents = json_decode($contents, true);
        $ignores = [];
        if (array_key_exists('archive', $jsonContents) && array_key_exists('exclude', $jsonContents['archive'])) {
            foreach ($jsonContents['archive']['exclude'] as $exclude) {
                $ignores[] = trim($exclude, DIRECTORY_SEPARATOR);
            }   
        }

        return $ignores;
    }
}
