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

    public function __construct(Configuration $configuration, IOInterface $io, Client $client = null)
    {
        $this->configuration = $configuration;
        $this->io = $io;
        $this->client = $client;
    }

    /**
     * Get the URL used for the provider
     * @return mixed
     */
    abstract public function getUrl();

    /**
     * Send the given file
     * @param $filePath
     * @return mixed
     */
    abstract public function sendFile($filePath);

    /**
     * @return Configuration
     */
    protected function getConfiguration()
    {
        return $this->configuration;
    }

    /**
     * @return IOInterface
     */
    protected function getIO()
    {
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
