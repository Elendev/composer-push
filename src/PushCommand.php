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
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

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
            new InputOption('username', null, InputArgument::OPTIONAL,
              'Username to log in the distant Nexus repository'),
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
     * @throws \GuzzleHttp\Exception\GuzzleException
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

            $this->getIO()
              ->write('Execute the Nexus Push for the URL ' . $url . '...',
                true);

            $this->sendFile($url, $fileName, $input->getOption('username'),
              $input->getOption('password'));

            $this->getIO()
              ->write('Archive correctly pushed to the Nexus server');

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
     * @param string|null $username
     * @param string|null $password
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function sendFile(
      $url,
      $filePath,
      $username = null,
      $password = null
    ) {

        if (!empty($username) && !empty($password)) {
            $this->getClient()->request(
              'POST',
              $url,
              [
                'body' => fopen($filePath, 'r'),
                'auth' => [$username, $password],
              ]
            );
        } else {

            $credentials = ['none' => []];

            if ($this->getNexusExtra('username') !== null && $this->getNexusExtra('password')) {
                $credentials['extra'] = [
                  $this->getNexusExtra('username'),
                  $this->getNexusExtra('password'),
                ];
            }


            if (preg_match('{^(?:https?)://([^/]+)(?:/.*)?}', $url,
                $match) && $this->getIO()->hasAuthentication($match[1])) {
                $auth = $this->getIO()->getAuthentication($match[1]);
                $credentials['auth.json'] = [
                  $auth['username'],
                  $auth['password'],
                ];
            }

            foreach ($credentials as $type => $credential) {
                $options = [
                  'body' => fopen($filePath, 'r'),
                ];

                if (!empty($credential)) {
                    $options['auth'] = $credential;
                }

                try {
                    $this->getClient()->request(
                      'POST',
                      $url,
                      $options
                    );

                    if ($type !== 'none') {
                        $this->getIO()
                          ->write('Nexus authentication done with credentials ' . $type,
                            true, IOInterface::VERY_VERBOSE);
                    }

                    return;

                } catch (ClientException $e) {
                    if ($e->getResponse()->getStatusCode() === '401') {
                        if ($type === 'none') {
                            $this->getIO()
                              ->write('Unable to push on server (authentication required)',
                                true, IOInterface::VERY_VERBOSE);
                        } else {
                            $this->getIO()
                              ->write('Unable to authenticate on server with credentials ' . $type,
                                true, IOInterface::VERY_VERBOSE);
                        }
                    } else {
                        $this->getIO()
                          ->writeError('A network error occured while trying to upload to nexus: ' . $e->getMessage(),
                            true, IOInterface::QUIET);
                    }
                }
            }
        }
    }

    /**
     * @return \GuzzleHttp\Client|\GuzzleHttp\ClientInterface
     */
    private function getClient()
    {
        if (empty($this->client)) {
            $this->client = new Client();
        }

        return $this->client;
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
}
