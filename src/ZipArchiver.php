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
     * @param string $subDirectory subdirectory in which the sources will be
     *               archived. If null, put at the root of the directory.
     * @param array $ignorePatterns
     *
     * @param \Composer\IO\IOInterface|null $io
     *
     * @throws \Exception
     */
    public static function archiveDirectory(
        $source,
        $destination,
        $subDirectory = null,
        $ignorePatterns = [],
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

        $finder->in($source)->ignoreVCS(true);

        foreach ($ignorePatterns as $ignorePattern) {
            $finder->notPath($ignorePattern);
        }

        $archive = new \ZipArchive();

      $io->write(
        'Create ZIP file ' . $destination,
        TRUE,
        IOInterface::VERY_VERBOSE
      );

        if (!$archive->open($destination, \ZipArchive::CREATE)) {
          $io->writeError(
            'Impossible to create ZIP file ' . $destination,
            TRUE
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
            TRUE,
            IOInterface::VERY_VERBOSE
          );
            $archive->addFile($fileInfo->getRealPath(), $zipPath);
        }

        $io->write('Zip archive ' . $destination . ' done');
        $archive->close();
    }
}
