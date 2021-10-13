<?php


namespace Elendev\ComposerPush\RepositoryProvider;

class NexusProvider extends AbstractProvider
{
    /**
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
     * Process the API call
     * @param $file file to upload
     * @param $options http call options
     */
    protected function apiCall($file, $options)
    {
        $url = $this->getUrl();

        $sourceType = $this->getConfiguration()->getSourceType();
        $sourceUrl = $this->getConfiguration()->getSourceUrl();
        $sourceReference = $this->getConfiguration()->getSourceReference();

        $options['debug'] = $this->getIO()->isVeryVerbose();

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

        $this->getClient()->request('PUT', $url, $options);
    }
}
