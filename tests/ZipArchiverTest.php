<?php

namespace Elendev\ComposerPush;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class ZipArchiverTest extends TestCase
{
    private $generationPath;

    public function setUp(): void
    {
        $this->generationPath = tempnam(sys_get_temp_dir(),'');

        if (file_exists($this->generationPath)) {
            unlink($this->generationPath);
        }
    }

    public function tearDown(): void
    {
        $fs = new Filesystem();
        $fs->remove($this->generationPath);
    }

    /**
     * @dataProvider zipArchiverProvider
     */
    public function testArchiveDirectory(string $directory, array $expectedResult, string $subdirectory = null, array $ignore = []) {
        ZipArchiver::archiveDirectory(
            $directory,
            $this->generationPath,
            '0.0.1',
            $subdirectory,
            $ignore
        );

        $this->assertArchiveContainsFiles($this->generationPath, $expectedResult);
    }

    public function zipArchiverProvider() {
        return [
            [
                __DIR__ . '/ZipArchiverTest/TypicalArchive',
                [
                    'README.md',
                    'src/myFile.php',
                    'src/myOtherFile.php',
                    'src/tests/myFileTest.php',
                    'src/tests/myOtherFileTest.php',
                ],
            ], [
                __DIR__ . '/ZipArchiverTest/TypicalArchive',
                [
                    'typicalArchive/README.md',
                    'typicalArchive/src/myFile.php',
                    'typicalArchive/src/myOtherFile.php',
                    'typicalArchive/src/tests/myFileTest.php',
                    'typicalArchive/src/tests/myOtherFileTest.php',
                ],
                'typicalArchive'
            ], [
                __DIR__ . '/ZipArchiverTest/TypicalArchive',
                [
                    'README.md',
                    'src/myFile.php',
                    'src/myOtherFile.php',
                ],
                null,
                [
                    'src/tests'
                ]
            ], [
                __DIR__ . '/ZipArchiverTest/TypicalArchive',
                [
                    'README.md',
                    'src/myFile.php',
                    'src/tests/myFileTest.php',
                    'src/tests/myOtherFileTest.php',
                ],
                null,
                [
                    'myOtherFile.php'
                ]
            ],
        ];
    }

    /**
     * @covers \Elendev\ComposerPush\ZipArchiver::archiveDirectory
     * @dataProvider composerArchiverProvider
     */
    public function testComposerArchiveDirectory(string $directory, array $expectedResult, $subdirectory, string $version) {
        ZipArchiver::archiveDirectory(
            $directory,
            $this->generationPath,
            $version,
            $subdirectory
        );

        $this->assertArchiveContainsFiles($this->generationPath, $expectedResult);
        $this->assertComposerJsonVersion($this->generationPath, $subdirectory, $version);
    }

    public function composerArchiverProvider() {
        return [
            [
                __DIR__ . '/ZipArchiverTest/ComposerJsonArchive',
                [
                    'composer.json',
                    'src/myFile.php',
                    'src/myOtherFile.php',
                ],
                null,
                '0.0.1',
            ],
            [
                __DIR__ . '/ZipArchiverTest/ComposerJsonArchive',
                [
                    'composer.json',
                    'src/myFile.php',
                    'src/myOtherFile.php',
                ],
                null,
                'v1.0.0',
            ],
            [
                __DIR__ . '/ZipArchiverTest/ComposerJsonArchive',
                [
                    'composer-json-archive/composer.json',
                    'composer-json-archive/src/myFile.php',
                    'composer-json-archive/src/myOtherFile.php',
                ],
                'composer-json-archive',
                'v2.0.0',
            ],
        ];
    }


    /**
     * Assert that the given archive contains the files
     * @param string $archivePath
     * @param array $files
     */
    private function assertArchiveContainsFiles(string $archivePath, array $files) {
        $archive = new \ZipArchive();
        $archive->open($archivePath);

        $this->assertEquals(count($files), $archive->numFiles, 'Not the correct amount of files in the archive ' . $archivePath);

        for ($i = 0; $i < $archive->numFiles; $i ++) {
            $entry = $archive->statIndex($i);
            $this->assertContains($entry['name'], $files);
        }
    }

    /**
     * @param string $archivePath
     * @param string $version
     */
    private function assertComposerJsonVersion(string $archivePath, $subDirectory, string $version)
    {
        $archive = new \ZipArchive();
        $archive->open($archivePath);

        $filePath = ($subDirectory ? $subDirectory . '/' : '') . 'composer.json';

        $content = json_decode($archive->getFromName($filePath));

        $this->assertEquals($version, $content->version);

        $archive->close();
    }
}
