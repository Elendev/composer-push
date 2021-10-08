<?php


namespace Elendev\ComposerPush\RepositoryProvider;

class ArtifactoryProvider extends AbstractProvider
{
    /**
     * @return string URL to the repository
     */
    public function getUrl()
    {
        $url = $this->getConfiguration()->getUrl();
        $name = $this->getConfiguration()->getPackageName();
        $version = $this->getConfiguration()->getVersion();

        $nameArray = explode('/', $name);
        $moduleName = end($nameArray);

        if (empty($url)) {
            throw new \InvalidArgumentException('The option --url is required or has to be provided as an extra argument in composer.json');
        }

        if (empty($version)) {
            throw new \InvalidArgumentException('The version argument is required');
        }

        // Remove trailing slash from URL
        $url = preg_replace('{/$}', '', $url);

        return sprintf('%s/%s/%s-%s', $url, $name, $moduleName, $version);
    }

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
     * Post with access token auth
     */
    protected function postFileWithToken($file, $token)
    {
        $options = [];
        $options['headers']['Authorization'] = 'Bearer ' . $token;
        $this->apiCall($file, $options);
    }

    private function apiCall($file, $options)
    {
        $options['debug'] = $this->getIO()->isVeryVerbose();
        $options['body'] = fopen($file, 'r');
        $url = $this->getUrl() . '.' . pathinfo($file, PATHINFO_EXTENSION) . '?properties=composer.version=' . $this->getConfiguration()->getVersion();
        $this->getClient()->request('PUT', $url, $options);
    }
}
