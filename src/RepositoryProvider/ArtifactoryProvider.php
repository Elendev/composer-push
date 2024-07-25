<?php

namespace Elendev\ComposerPush\RepositoryProvider;

use Composer\IO\ConsoleIO;

class ArtifactoryProvider extends AbstractProvider
{
    /**
     * {@inheritDoc}
     */
    public function getUrl(): string
    {
        $url = $this->getConfiguration()->getUrl();
        $name = $this->getConfiguration()->getPackageName();
        $version = $this->getConfiguration()->getVersion();

        $nameArray = explode('/', $name);
        $moduleName = end($nameArray);

        if (empty($url)) {
            throw new \InvalidArgumentException(
                'The option --url is required or has to be provided as an extra argument in composer.json',
            );
        }

        if (empty($version)) {
            throw new \InvalidArgumentException(
                'The version argument is required',
            );
        }

        // Remove trailing slash from URL
        $url = preg_replace('{/$}', '', $url);

        return sprintf('%s/%s/%s-%s', $url, $name, $moduleName, $version);
    }

    /**
     * {@inheritDoc}
     */
    protected function apiCall(string $file, array $options): void
    {
        $io = $this->getIO();
        $options['debug'] = $io->isVeryVerbose();
        $options['body'] = fopen($file, 'r');
        if ($io instanceof ConsoleIO) {
            $options['progress'] = static::progressBarCallback(
                $io->getProgressBar(),
            );
        }

        $url =
            $this->getUrl() .
            '.' .
            pathinfo($file, PATHINFO_EXTENSION) .
            '?properties=composer.version=' .
            $this->getConfiguration()->getVersion();
        $this->getClient()->request('PUT', $url, $options);
    }
}
