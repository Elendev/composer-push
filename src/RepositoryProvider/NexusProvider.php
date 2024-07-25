<?php

namespace Clearlyip\ComposerPush\RepositoryProvider;

use Composer\IO\ConsoleIO;

class NexusProvider extends AbstractProvider
{
    /**
     * {@inheritDoc}
     */
    public function getUrl(): string
    {
        $url = $this->getConfiguration()->getUrl();
        $name = $this->getConfiguration()->getPackageName();
        $version = $this->getConfiguration()->getVersion();

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

        return sprintf('%s/packages/upload/%s/%s', $url, $name, $version);
    }

    /**
     * {@inheritDoc}
     */
    protected function apiCall(string $file, array $options): void
    {
        $url = $this->getUrl();

        $sourceType = $this->getConfiguration()->getSourceType();
        $sourceUrl = $this->getConfiguration()->getSourceUrl();
        $sourceReference = $this->getConfiguration()->getSourceReference();

        $io = $this->getIO();
        $options['debug'] = $io->isVeryVerbose();

        if ($io instanceof ConsoleIO) {
            $options['progress'] = static::progressBarCallback(
                $io->getProgressBar(),
            );
        }

        if (
            !empty($sourceType) &&
            !empty($sourceUrl) &&
            !empty($sourceReference)
        ) {
            $options['multipart'] = [
                [
                    'Content-Type' => 'application/zip',
                    'name' => 'package',
                    'contents' => fopen($file, 'r'),
                ],
                [
                    'name' => 'src-type',
                    'contents' => $sourceType,
                ],
                [
                    'name' => 'src-url',
                    'contents' => $sourceUrl,
                ],
                [
                    'name' => 'src-ref',
                    'contents' => $sourceReference,
                ],
            ];
        } else {
            $options['body'] = fopen($file, 'r');
        }

        $this->getClient()->request('PUT', $url, $options);
    }
}
