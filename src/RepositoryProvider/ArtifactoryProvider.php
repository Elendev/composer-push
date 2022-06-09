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

        $io = $this->getIO();

        if(method_exists($io, 'getProgressBar')) {
            /** @var \Symfony\Component\Console\Helper\ProgressBar */
            $progress = $io->getProgressBar();
            $options['progress'] = function (
                $downloadTotal,
                $downloadedBytes,
                $uploadTotal,
                $uploadedBytes
            ) use ($progress) {
                if ($uploadTotal === 0) {
                    return;
                }
                if ($uploadedBytes === 0) {
                    $progress->start(100);
                    return;
                }

                if ($uploadedBytes === $uploadTotal) {
                    if ($progress->getProgress() != 100) {
                        $progress->finish();
                        $this->getIO()->write('');
                    }
                    return;
                }

                $progress->setProgress(($uploadedBytes / $uploadTotal) * 100);
            };
        }

        $url = $this->getUrl() . '.' . pathinfo($file, PATHINFO_EXTENSION) . '?properties=composer.version=' . $this->getConfiguration()->getVersion();
        $this->getClient()->request('PUT', $url, $options);
    }
}
