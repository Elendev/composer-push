<?php


namespace Elendev\NexusComposerPush;

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
     * @param array $ignorePatterns
     *
     * @param \Composer\IO\IOInterface|null $io
     *
     * @throws \Exception
     */
    public static function archiveDirectory(
        $source,
        $destination,
        $ignorePatterns = [],
        $io = null
    ) {

        if (empty($io)) {
            $io = new NullIO();
        }

        $finder = new Finder();
        $fileSystem = new Filesystem();

        $finder->in($source)->ignoreVCS(true);

        foreach ($ignorePatterns as $ignorePattern) {
            $finder->notPath($ignorePattern);
        }

        $archive = new \ZipArchive();

        $io->write('Create ZIP file ' . $destination, true,
            IOInterface::VERY_VERBOSE);

        if (!$archive->open($destination, \ZipArchive::CREATE)) {
            $io->writeError('Impossible to create ZIP file ' . $destination,
                true);
            throw new \Exception('Impossible to create the file ' . $destination);
        }

        foreach ($finder as $fileInfo) {
            $zipPath = '/' . rtrim($fileSystem->makePathRelative($fileInfo->getRealPath(),
                    $source), '/');

            if (!$fileInfo->isFile()) {
                continue;
            }

            $io->write('Zip file ' . $fileInfo->getPath() . ' to ' . $zipPath,
                true, IOInterface::VERY_VERBOSE);
            $archive->addFile($fileInfo->getRealPath(), $zipPath);
        }

        $io->write('Zip archive ' . $destination . ' done');
        $archive->close();
    }

}
