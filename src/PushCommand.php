<?php
namespace Elendev\NexusComposerPush;

if (file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
    $loader = require_once dirname(__DIR__) . '/vendor/autoload.php';
} elseif (file_exists(dirname(__DIR__) . '/../../autoload.php')) {
    $loader = require_once dirname(__DIR__) . '/../../autoload.php';
} else {
    trigger_error("autoload.php was not found", E_USER_WARNING);
}

if (isset($loader) && $loader !== true) {
    spl_autoload_unregister([$loader, 'loadClass']);
    $loader->register(false);
}

use Composer\Command\BaseCommand;
use Composer\Config;
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

    /**
     * @var Configuration
     */
    private $configuration;

    const REPOSITORY = 'repository';
    const PUSH_CFG_NAME = 'name';

    protected function configure()
    {
        $this
            ->setName('nexus-push')
            ->setDescription('Initiate a push to a distant Nexus repository')
            ->setDefinition([
                new InputArgument('version', InputArgument::REQUIRED, 'The package version'),
                new InputOption('name', null, InputArgument::OPTIONAL, 'Name of the package (if different from the composer.json file)'),
                new InputOption('url', null, InputArgument::OPTIONAL, 'URL to the distant Nexus repository'),
                new InputOption(self::REPOSITORY, null, InputArgument::OPTIONAL, 'which repository to save, use this parameter if you want to place development version and production version in different repository'),
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
                new InputOption('src-ref', null, InputArgument::OPTIONAL, 'The source reference pushed on composer on distant Nexus repository'),
                new InputOption('keep-vendor', null, InputOption::VALUE_NONE, 'Keep vendor directory when creating zip'),
                new InputOption('keep-dot-files', null, InputOption::VALUE_NONE, 'Keep dots files/dirs when creating zip')
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
        $sourceType = $input->getOption('src-type');
        $sourceUrl = $input->getOption('src-url');
        $sourceReference = $input->getOption('src-ref');
        // we will check to see if any of these are available, and if so, and not all of them we will inform the user
        if (!empty($sourceType) || !empty($sourceUrl) || !empty($sourceReference)) {
            if (empty($sourceType) || empty($sourceUrl) || empty($sourceReference)) {
                throw new InvalidArgumentException('Source reference parameters are not complete, you should set all three parameters (type, url, ref) or none of them, please check');
            }
        }

        $fileName = tempnam(sys_get_temp_dir(), 'nexus-push') . '.zip';

        $this->configuration = new Configuration($input, $this->getComposer(true), $this->getIO());

        $packageName = $this->configuration->getPackageName();

        $subdirectory = strtolower(preg_replace(
            '/[^a-zA-Z0-9_]|\./',
            '-',
            $packageName . '-' . $input->getArgument('version')
        ));



        $ignoredDirectories = $this->configuration->getIgnores();
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
            $url = $this->configuration->get('url');

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

            if ($this->configuration->get('username') !== null && $this->configuration->get('password')) {
                $credentials['extra'] = [
                    'username' => $this->configuration->get('username'),
                    'password' => $this->configuration->get('password'),
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
            $this->client = new Client();
        }
        return $this->client;
    }
}
