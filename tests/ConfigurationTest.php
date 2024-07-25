<?php

namespace Clearlyip\Tests;

use Composer\Composer;
use Composer\IO\NullIO;
use Composer\Package\RootPackageInterface;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Clearlyip\ComposerPush\Configuration;

class ConfigurationTest extends TestCase
{
    /**
     * @var Configuration
     */
    private $configuration;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|InputInterface
     */
    private $inputMock;

    /**
     * @var Composer|\PHPUnit\Framework\MockObject\MockObject
     */
    private $composerMock;

    /**
     * @var Set of values used by the mocks
     */
    private $keepVendor;
    private $configIgnore;
    private $composerPackageArchiveExcludes;
    private $configIgnoreByComposer;
    private $configOptionUrl;

    private $singleConfig;
    private $configName;
    private $repository;

    private $configType;
    private $extraConfigType;

    private $configVerifySsl;
    private $extraVerifySsl;

    public function setUp(): void
    {
        $this->keepVendor = null;
        $this->configIgnore = [];
        $this->composerPackageArchiveExcludes = [];
        $this->configIgnoreByComposer = null;
        $this->configOptionUrl = 'https://option-url.com';

        $this->singleConfig = true;
        $this->configName = null;

        $this->configType = null;
        $this->extraConfigType = null;

        $this->configVerifySsl = null;
        $this->extraVerifySsl = null;

        $this->initGlobalConfiguration();
    }

    public function testGetVersion()
    {
        $this->inputMock
            ->method('getArgument')
            ->willReturnCallback(function ($argument) {
                if ($argument === 'version') {
                    return '1.0.1';
                }
            });
        $this->assertEquals('1.0.1', $this->configuration->getVersion());
    }

    public function testGetVersionComposeJson()
    {
        $this->inputMock
            ->method('getArgument')
            ->willReturnCallback(function ($argument) {
                if ($argument === 'version') {
                    return null;
                }
            });
        $this->assertEquals('1.2.3', $this->configuration->getVersion());
    }

    public function testGetSourceUrl()
    {
        $this->assertEquals('my-src-url', $this->configuration->getSourceUrl());
    }

    public function testGetSourceType()
    {
        $this->assertEquals(
            'my-src-type',
            $this->configuration->getSourceType(),
        );
    }

    public function testGetAccessToken()
    {
        $this->assertEquals('my-token', $this->configuration->getAccessToken());
    }

    public function testGetUrl()
    {
        $this->assertEquals(
            'https://option-url.com',
            $this->configuration->getUrl(),
        );

        $this->configOptionUrl = null;
        $this->initGlobalConfiguration();

        $this->assertEquals(
            'https://example.com',
            $this->configuration->getUrl(),
        );
    }

    public function testGetVerifySsl()
    {
        $this->assertTrue($this->configuration->getVerifySsl());

        $this->extraVerifySsl = false;
        $this->initGlobalConfiguration();

        $this->assertFalse($this->configuration->getVerifySsl());

        $this->extraVerifySsl = null;
        $this->configVerifySsl = false;
        $this->initGlobalConfiguration();

        $this->assertFalse($this->configuration->getVerifySsl());

        $this->extraVerifySsl = true;
        $this->configVerifySsl = false;
        $this->initGlobalConfiguration();

        $this->assertFalse($this->configuration->getVerifySsl());

        $this->extraVerifySsl = 'true';
        $this->configVerifySsl = 'false';
        $this->initGlobalConfiguration();

        $this->assertFalse($this->configuration->getVerifySsl());
    }

    public function testGet()
    {
        $this->assertEquals(
            'https://example.com',
            $this->configuration->get('url'),
        );
        $this->assertEquals(
            'push-username',
            $this->configuration->get('username'),
        );
        $this->assertEquals(
            'push-password',
            $this->configuration->get('password'),
        );

        $this->singleConfig = false;
        $this->repository = 'A';

        $this->initGlobalConfiguration();
        $this->assertEquals('https://a.com', $this->configuration->get('url'));
        $this->assertEquals(
            'push-username-a',
            $this->configuration->get('username'),
        );
        $this->assertEquals(
            'push-password-a',
            $this->configuration->get('password'),
        );

        $this->repository = 'B';
        $this->initGlobalConfiguration();
        $this->assertEquals('https://b.com', $this->configuration->get('url'));
        $this->assertEquals(
            'push-username-b',
            $this->configuration->get('username'),
        );
        $this->assertEquals(
            'push-password-b',
            $this->configuration->get('password'),
        );

        $this->repository = null;
        $this->initGlobalConfiguration();

        $this->expectException(\InvalidArgumentException::class);
        $this->configuration->get('url');
    }

    public function testGetIgnores()
    {
        $this->assertArrayEquals(
            ['option-dir1', 'option-dir2', 'vendor/'],
            $this->configuration->getIgnores(),
        );

        $this->keepVendor = true;
        $this->initGlobalConfiguration();

        $this->assertArrayEquals(
            ['option-dir1', 'option-dir2'],
            $this->configuration->getIgnores(),
        );

        $this->keepVendor = false;
        $this->configIgnore = ['config-dir1', 'config-dir2', 'config-dir3'];

        $this->initGlobalConfiguration();

        $this->assertArrayEquals(
            [
                'option-dir1',
                'option-dir2',
                'vendor/',
                'config-dir1',
                'config-dir2',
                'config-dir3',
            ],
            $this->configuration->getIgnores(),
        );

        $this->composerPackageArchiveExcludes = ['my-package1', 'my-package2'];
        $this->initGlobalConfiguration();

        $this->assertArrayEquals(
            [
                'option-dir1',
                'option-dir2',
                'vendor/',
                'config-dir1',
                'config-dir2',
                'config-dir3',
            ],
            $this->configuration->getIgnores(),
        );

        $this->configIgnoreByComposer = true;
        $this->initGlobalConfiguration();

        $this->assertArrayEquals(
            [
                'option-dir1',
                'option-dir2',
                'vendor/',
                'config-dir1',
                'config-dir2',
                'config-dir3',
                'my-package1',
                'my-package2',
            ],
            $this->configuration->getIgnores(),
        );
    }

    public function testGetSourceReference()
    {
        $this->assertEquals(
            'my-src-ref',
            $this->configuration->getSourceReference(),
        );
    }

    public function testGetOptionPassword()
    {
        $this->assertEquals(
            'my-password',
            $this->configuration->getOptionPassword(),
        );
    }

    public function testGetType()
    {
        $this->assertEquals('nexus', $this->configuration->getType());

        $this->extraConfigType = 'jfrog';
        $this->initGlobalConfiguration();
        $this->assertEquals('jfrog', $this->configuration->getType());

        $this->configType = 'other-type';
        $this->initGlobalConfiguration();
        $this->assertEquals('other-type', $this->configuration->getType());
    }

    public function testGetPackageName()
    {
        $this->assertEquals(
            'composer-push-name',
            $this->configuration->getPackageName(),
        );
    }

    public function testGetOptionUsername()
    {
        $this->assertEquals(
            'my-username',
            $this->configuration->getOptionUsername(),
        );
    }

    private function createInputMock()
    {
        $input = $this->createMock(InputInterface::class);
        $input->method('getOption')->willReturnCallback(function ($argument) {
            switch ($argument) {
                case 'name':
                    return 'composer-push-name';
                case 'url':
                    return $this->configOptionUrl;
                case 'src-type':
                    return 'my-src-type';
                case 'src-url':
                    return 'my-src-url';
                case 'src-ref':
                    return 'my-src-ref';
                case 'username':
                    return 'my-username';
                case 'password':
                    return 'my-password';
                case 'ignore':
                    return ['option-dir1', 'option-dir2'];
                case 'keep-vendor':
                    return $this->keepVendor;
                case 'ignore-by-composer':
                    return $this->configIgnoreByComposer;
                case 'repository':
                    return $this->repository;
                case 'type':
                    return $this->configType;
                case 'ssl-verify':
                    return $this->configVerifySsl;
                case 'access-token':
                    return 'my-token';
            }
        });

        return $input;
    }

    private function initGlobalConfiguration()
    {
        $this->inputMock = $this->createInputMock();
        $this->composerMock = $this->createComposerMock();
        $this->configuration = new Configuration(
            $this->inputMock,
            $this->composerMock,
            new NullIO(),
        );
    }

    private function createComposerMock()
    {
        $composer = $this->createMock(Composer::class);
        $packageInterface = $this->createMock(RootPackageInterface::class);

        $composer->method('getPackage')->willReturn($packageInterface);

        $packageInterface->method('getVersion')->willReturn('1.2.3');
        $packageInterface->method('getExtra')->willReturnCallback(function () {
            if ($this->singleConfig) {
                return [
                    'push' => [
                        'url' => 'https://example.com',
                        'username' => 'push-username',
                        'password' => 'push-password',
                        'ignore' => $this->configIgnore,
                        'type' => $this->extraConfigType,
                        'ssl-verify' => $this->extraVerifySsl,
                    ],
                ];
            } else {
                return [
                    'push' => [
                        [
                            'name' => 'A',
                            'url' => 'https://a.com',
                            'username' => 'push-username-a',
                            'password' => 'push-password-a',
                        ],
                        [
                            'name' => 'B',
                            'url' => 'https://b.com',
                            'username' => 'push-username-b',
                            'password' => 'push-password-b',
                        ],
                    ],
                ];
            }
        });

        $packageInterface
            ->method('getArchiveExcludes')
            ->willReturnCallback(function () {
                return $this->composerPackageArchiveExcludes;
            });

        return $composer;
    }

    private function assertArrayEquals($expected, $result)
    {
        try {
            $this->assertSameSize($expected, $result);
        } catch (ExpectationFailedException $e) {
            echo ' Expected: ';
            print_r($expected);
            echo ' Received: ';
            print_r($result);
            throw $e;
        }

        foreach ($expected as $e) {
            $this->assertContains($e, $result);
        }
    }
}
