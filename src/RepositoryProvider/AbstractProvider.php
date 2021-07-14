<?php


namespace Elendev\NexusComposerPush\RepositoryProvider;


use Composer\IO\IOInterface;
use Elendev\NexusComposerPush\Configuration;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

abstract class AbstractProvider
{
    /**
     * @var \GuzzleHttp\ClientInterface
     */
    private $client;

    /**
     * @var Configuration
     */
    private Configuration $configuration;

    /**
     * @var IOInterface
     */
    private IOInterface $io;

    public function __construct(Configuration $configuration, IOInterface $io) {
        $this->configuration = $configuration;
        $this->io = $io;
    }

    /**
     * Generate the URL used for the provider
     * @return mixed
     */
    public abstract function generateUrl();

    /**
     * Send the given file
     * @param $filePath
     * @return mixed
     */
    public abstract function sendFile($filePath);

    /**
     * @return Configuration
     */
    protected function getConfiguration() {
        return $this->configuration;
    }

    /**
     * @return IOInterface
     */
    protected function getIO() {
        return $this->io;
    }

    /**
     * @throws FileNotFoundException
     * @return \GuzzleHttp\Client|\GuzzleHttp\ClientInterface
     */
    protected function getClient()
    {
        if (empty($this->client)) {
            $this->client = new Client();
        }
        return $this->client;
    }
}