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
use Elendev\NexusComposerPush\RepositoryProvider\NexusProvider;
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
            ->setName('push')
            ->setAliases(['nexus-push']) // Deprecated, use push instead
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
            $packageName . '-' . $this->configuration->getVersion()
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

            $provider = new NexusProvider($this->configuration, $this->getIO());

            $this->getIO()
                ->write(
                    'Execute the Nexus Push for the URL ' . $provider->generateUrl() . '...',
                    true
                );

            $provider->sendFile($fileName);

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
}
