<?php

namespace Elendev\ComposerPush\RepositoryProvider;

use Composer\IO\IOInterface;
use Elendev\ComposerPush\Configuration;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
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
    private $configuration;

    /**
     * @var IOInterface
     */
    private $io;

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
     * Try to send a file with the given username/password. If the credentials
     * are not set, try to send a simple request without credentials. If the
     * send fail with a 401, try to use the credentials that may be available
     * in an `auth.json` file or in the
     * `extra` section
     *
     * @param string $filePath path to the file to send
     * @throws \Exception
     */
    public function sendFile(
        $filePath
    ) {
        $username = $this->getConfiguration()->getOptionUsername();
        $password = $this->getConfiguration()->getOptionPassword();

        if (!empty($username) && !empty($password)) {
            $this->postFile($filePath, $username, $password);
            return;
        } else {
            $credentials = [];

            if ($this->getConfiguration()->get('username') !== null && $this->getConfiguration()->get('password')) {
                $credentials['extra'] = [
                    'username' => $this->getConfiguration()->get('username'),
                    'password' => $this->getConfiguration()->get('password'),
                ];
            }

            if ($this->getConfiguration()->getAccessToken()) {
                $credentials['access_token']['token'] = $this->getConfiguration()->getAccessToken();
            }

            if (preg_match(
                '{^(?:https?)://([^/]+)(?:/.*)?}',
                $this->getUrl(),
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
                    if (!empty($credential['token'])) {
                        $this->getIO()
                            ->write(
                                '[postFile] Use ' . $type,
                                true,
                                IOInterface::VERY_VERBOSE
                            );
                        $this->postFileWithToken($filePath, $credential['token']);
                    } elseif (!empty($credential['username']) && !empty($credential['password'])) {
                        $this->getIO()
                            ->write(
                                '[postFile] Use user ' . $credential['username'],
                                true,
                                IOInterface::VERY_VERBOSE
                            );
                        $this->postFile(
                            $filePath,
                            $credential['username'],
                            $credential['password']
                        );
                    } else {
                        $this->getIO()
                            ->write(
                                '[postFile] Use no credentials',
                                true,
                                IOInterface::VERY_VERBOSE
                            );
                        $this->postFile($filePath);
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
                                'A network error occured while trying to upload to the server: ' . $e->getMessage(),
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
     * Process the API call
     * @param $file file to upload
     * @param $options http call options
     */
    abstract protected function apiCall($file, $options);

    /**
     * The file has to be uploaded by hand because of composer limitations
     * (impossible to use Guzzle functions.php file in a composer plugin).
     *
     * @param $file
     * @param $username
     * @param $password
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function postFile($file, $username = null, $password = null)
    {
        $options = [];

        if (!empty($username) && !empty($password)) {
            $options['auth'] = [$username, $password];
        }

        $this->apiCall($file, $options);
    }

    /**
     * Post the given file with access token
     * @param $file
     * @param string $token
     * @return mixed
     */
    protected function postFileWithToken($file, $token)
    {
        $options = [];
        $options['headers']['Authorization'] = 'Bearer ' . $token;
        $this->apiCall($file, $options);
    }

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
            $this->client = new Client([
                'verify' => $this->configuration->getVerifySsl()
            ]);
        }
        return $this->client;
    }
}
