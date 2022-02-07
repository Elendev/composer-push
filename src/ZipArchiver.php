<?php

namespace Elendev\ComposerPush;

use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class ZipArchiver
{
    /**
     * Archive the given directory in the $destination file
     *
     * @param string $source archive source
     * @param string $destination archive destination
     * @param string $subDirectory subdirectory in which the sources will be
     *               archived. If null, put at the root of the directory.
     * @param array $ignores
     * @param \Composer\IO\IOInterface|null $io
     *
     * @throws \Exception
     */
    public static function archiveDirectory(
        $source,
        $destination,
        $version,
        $subDirectory = null,
        $ignores = [],
        $keepDotFiles = false,
        $io = null
    ) {
        if (empty($io)) {
            $io = new NullIO();
        }

        if ($subDirectory) {
            $io->write('[ZIP Archive] Archive into the subdirectory ' . $subDirectory);
        } else {
            $io->write('[ZIP Archive] Archive into root directory');
        }

        $finder = new Finder();
        $fileSystem = new Filesystem();

        $finder->in($source)->ignoreVCS(true)->ignoreDotFiles(!$keepDotFiles);

        foreach ($ignores as $ignore) {
            $finder->notPath($ignore);
        }

        $archive = new \ZipArchive();

        $io->write(
            'Create ZIP file ' . $destination,
            true,
            IOInterface::VERY_VERBOSE
        );

        if ($archive->open($destination, \ZipArchive::CREATE) !== true) {
            $io->writeError(
                'Impossible to create ZIP file ' . $destination,
                true
            );
            throw new \Exception('Impossible to create the file ' . $destination);
        }

        foreach ($finder as $fileInfo) {
            if ($subDirectory) {
                $zipPath = $subDirectory . '/';
            } else {
                $zipPath = '';
            }

            $zipPath .= rtrim($fileSystem->makePathRelative(
                $fileInfo->getRealPath(),
                $source
            ), '/');

            if (!$fileInfo->isFile()) {
                continue;
            }

            $io->write(
                'Zip file ' . $fileInfo->getPath() . ' to ' . $zipPath,
                true,
                IOInterface::VERY_VERBOSE
            );

            $archive->addFile($fileInfo->getRealPath(), $zipPath);
        }

        $archive->close();

        $io->write('Update version in ZIP archive to ' . $version, true, IOInterface::VERBOSE);

        self::updateVersion($destination, $subDirectory, $version, $io);

        $io->write('Zip archive ' . $destination . ' done');
    }

    /**
     * Update the version of the composer.json file of the zip archive
     * @param $zipFile
     * @param $subDirectory
     * @param $version
     * @param \Composer\IO\IOInterface|null $io
     * @throws \Exception
     */
    private static function updateVersion($zipFile, $subDirectory, $version, $io)
    {
        $archive = new \ZipArchive();

        if ($archive->open($zipFile) !== true) {
            throw new \Exception('Impossible to update Composer version in composer.json');
        }

        $filePath = ($subDirectory ? $subDirectory . '/' : '') . 'composer.json';

        $content = json_decode($archive->getFromName($filePath));

        if ($content === false || $content === null) {
            $io->write('No composer.json file in the archive (path: ' . $filePath . ')', true, IOInterface::VERBOSE);
            return;
        }

        $content->version = $version;

        $archive->deleteName($filePath);

        $archive->addFromString($filePath, json_encode($content, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

        $archive->close();
    }
}
