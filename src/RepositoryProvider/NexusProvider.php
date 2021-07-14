<?php


namespace Elendev\NexusComposerPush\RepositoryProvider;

use Composer\IO\IOInterface;
use GuzzleHttp\Exception\ClientException;

class NexusProvider extends AbstractProvider
{
    /**
     * @param string $url
     * @param string $name
     * @param string $version
     *
     * @return string URL to the repository
     */
    public function getUrl()
    {
        $url = $this->getConfiguration()->getUrl();
        $name = $this->getConfiguration()->getPackageName();
        $version = $this->getConfiguration()->getVersion();

        if (empty($url)) {
            throw new \InvalidArgumentException('The option --url is required or has to be provided as an extra argument in composer.json');
        }

        if (empty($version)) {
            throw new \InvalidArgumentException('The version argument is required');
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
    public function sendFile(
        $filePath
    ) {
        $url = $this->getUrl();

        $sourceType = $this->getConfiguration()->getSourceType();
        $sourceUrl = $this->getConfiguration()->getSourceUrl();
        $sourceReference = $this->getConfiguration()->getSourceReference();
        $username = $this->getConfiguration()->getOptionUsername();
        $password = $this->getConfiguration()->getOptionPassword();

        if (!empty($username) && !empty($password)) {
            $this->postFile($url, $filePath, $sourceType, $sourceUrl, $sourceReference, $username, $password);
            return;
        } else {
            $credentials = [];

            if ($this->getConfiguration()->get('username') !== null && $this->getConfiguration()->get('password')) {
                $credentials['extra'] = [
                    'username' => $this->getConfiguration()->get('username'),
                    'password' => $this->getConfiguration()->get('password'),
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
}
