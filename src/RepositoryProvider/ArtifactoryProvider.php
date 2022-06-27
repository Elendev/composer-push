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
     * Process the API call
     * @param $file file to upload
     * @param $options http call options
     */
    protected function apiCall($file, $options)
    {
        $options['debug'] = $this->getIO()->isVeryVerbose();
        $options['body'] = fopen($file, 'r');
        $options['progress'] = $this->getProgressCallback();
        $url = $this->getUrl() . '.' . pathinfo($file, PATHINFO_EXTENSION) . '?properties=composer.version=' . $this->getConfiguration()->getVersion();
        $this->getClient()->request('PUT', $url, $options);
    }
}
