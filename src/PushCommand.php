<?php


namespace Elendev\NexusComposerPush;


use Composer\Command\ArchiveCommand;
use Composer\Command\BaseCommand;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

class PushCommand extends BaseCommand
{
    protected function configure()
    {
        $this
          ->setName('nexus-push')
          ->setDescription('Initiate a push to a distant Nexus repository')
          ->setDefinition([
            new InputArgument('version', InputArgument::REQUIRED, 'The package version'),
            new InputOption('name', null, InputArgument::OPTIONAL, 'Name of the package (if different from the composer.json file)'),
            new InputOption('url', null, InputArgument::OPTIONAL, 'URL to the distant Nexus repository'),
            new InputOption('user', null, InputArgument::OPTIONAL, 'Username to log in the distant Nexus repository'),
            new InputOption('password', null, InputArgument::OPTIONAL, 'Password to log in the distant Nexus repository'),
          ])
          ->setHelp(<<<EOT
The <info>nexus-push</info> command uses the archive command to reate a ZIP
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
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $fileName = tempnam(sys_get_temp_dir(), 'nexus-push');

        try {
            $this->getApplication()->find('archive')->run(
              new StringInput(sprintf('--format=zip --dir=%s --file=%s', dirname($fileName), basename($fileName))),
              new NullOutput()
            );

            $url = $this->generateUrl(
              $input->getOption('url'),
              $input->getOption('name'),
              $input->getArgument('version')
            );

            $output->writeln("Executed the Nexus Push for the URL " . $url);

            // TODO use Guzzle to send the file into the nexus webserver, using the credentials

        } finally {
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
    private function generateUrl($url, $name, $version) {

        $options = [];

        if(!empty($this->getComposer(true)->getPackage()->getExtra()['nexus-push'])) {
            $options = $this->getComposer(true)->getPackage()->getExtra()['nexus-push'];
        }

        if (empty($url)) {
            if (empty($options['url'])) {
                throw new InvalidArgumentException('The option --url is required or has to be provided as an extra argument in composer.json');
            }

            $url = $options['url'];
        }

        if (empty($name)) {
            $name = $this->getComposer(true)->getPackage()->getName();
        }

        if (empty($version)) {
            throw new InvalidArgumentException('The version argument is required');
        }

        return sprintf('%s/packages/upload/%s/%s', $url, $name, $version);
    }
}
