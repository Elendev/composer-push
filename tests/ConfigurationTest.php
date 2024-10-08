<?php

namespace Elendev\ComposerPush;

use Composer\Composer;
use Composer\IO\NullIO;
use Composer\Package\RootPackageInterface;
use Composer\Plugin\PluginManager;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;

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

    private $localConfig;
    private $globalConfig;
    private $splitConfig;
    private $repository;

    private $configType;
    private $extraConfigType;

    private $configVerifySsl;
    private $extraVerifySsl;

    private const ComposerConfigEmpty = 0;
    private const ComposerConfigSingle = 1;
    private const ComposerConfigMulti = 2;

    public function setUp(): void
    {
        $this->keepVendor = null;
        $this->configIgnore = [];
        $this->composerPackageArchiveExcludes = [];
        $this->configIgnoreByComposer = null;
        $this->configOptionUrl = "https://option-url.com";

        $this->localConfig = self::ComposerConfigSingle;
        $this->globalConfig = self::ComposerConfigEmpty;
        $this->configName = null;

        $this->configType = null;
        $this->extraConfigType = null;

        $this->configVerifySsl = null;
        $this->extraVerifySsl = null;

        $this->initGlobalConfiguration();
    }


    public function testGetVersion()
    {
        $this->inputMock->method('getArgument')->willReturnCallback(function ($argument) {
            if ($argument === 'version') {
                return '1.0.1';
            }
        });
        $this->assertEquals('1.0.1', $this->configuration->getVersion());
    }

    public function testGetVersionComposeJson()
    {
        $this->inputMock->method('getArgument')->willReturnCallback(function ($argument) {
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
        $this->assertEquals('my-src-type', $this->configuration->getSourceType());
    }

    public function testGetAccessToken()
    {
        $this->assertEquals('my-token', $this->configuration->getAccessToken());
    }

    public function testGetUrl()
    {
        $this->assertEquals('https://option-url.com', $this->configuration->getUrl());

        $this->configOptionUrl = null;
        $this->initGlobalConfiguration();

        $this->assertEquals('https://example.com', $this->configuration->getUrl());
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
        $this->assertEquals('https://example.com', $this->configuration->get('url'));
        $this->assertEquals('push-username', $this->configuration->get('username'));
        $this->assertEquals('push-password', $this->configuration->get('password'));

        $this->localConfig = self::ComposerConfigMulti;
        $this->repository = 'A';

        $this->initGlobalConfiguration();
        $this->assertEquals('https://a.com', $this->configuration->get('url'));
        $this->assertEquals('push-username-a', $this->configuration->get('username'));
        $this->assertEquals('push-password-a', $this->configuration->get('password'));

        $this->repository = 'B';
        $this->initGlobalConfiguration();
        $this->assertEquals('https://b.com', $this->configuration->get('url'));
        $this->assertEquals('push-username-b', $this->configuration->get('username'));
        $this->assertEquals('push-password-b', $this->configuration->get('password'));

        $this->repository = null;
        $this->initGlobalConfiguration();

        $this->expectException(\InvalidArgumentException::class);
        $this->configuration->get('url');
    }

    public function testGetIgnores()
    {
        $this->assertArrayEquals([
            "option-dir1",
            "option-dir2",
            "vendor/"
        ], $this->configuration->getIgnores());

        $this->keepVendor = true;
        $this->initGlobalConfiguration();

        $this->assertArrayEquals([
            "option-dir1",
            "option-dir2"
        ], $this->configuration->getIgnores());

        $this->keepVendor = false;
        $this->configIgnore = ["config-dir1", "config-dir2", "config-dir3"];

        $this->initGlobalConfiguration();

        $this->assertArrayEquals([
            "option-dir1",
            "option-dir2",
            "vendor/",
            "config-dir1",
            "config-dir2",
            "config-dir3",
        ], $this->configuration->getIgnores());

        $this->composerPackageArchiveExcludes = ["my-package1", "my-package2"];
        $this->initGlobalConfiguration();

        $this->assertArrayEquals([
            "option-dir1",
            "option-dir2",
            "vendor/",
            "config-dir1",
            "config-dir2",
            "config-dir3",
        ], $this->configuration->getIgnores());

        $this->configIgnoreByComposer = true;
        $this->initGlobalConfiguration();

        $this->assertArrayEquals([
            "option-dir1",
            "option-dir2",
            "vendor/",
            "config-dir1",
            "config-dir2",
            "config-dir3",
            "my-package1",
            "my-package2"
        ], $this->configuration->getIgnores());
    }

    public function testGetSourceReference()
    {
        $this->assertEquals('my-src-ref', $this->configuration->getSourceReference());
    }

    public function testGetOptionPassword()
    {
        $this->assertEquals("my-password", $this->configuration->getOptionPassword());
    }

    public function testGetType()
    {
        $this->assertEquals("nexus", $this->configuration->getType());

        $this->extraConfigType = 'jfrog';
        $this->initGlobalConfiguration();
        $this->assertEquals("jfrog", $this->configuration->getType());

        $this->configType = 'other-type';
        $this->initGlobalConfiguration();
        $this->assertEquals("other-type", $this->configuration->getType());
    }

    public function testGetPackageName()
    {
        $this->assertEquals('composer-push-name', $this->configuration->getPackageName());
    }

    public function testGetOptionUsername()
    {
        $this->assertEquals("my-username", $this->configuration->getOptionUsername());
    }

    public function testGetGlobalConfig()
    {
        $this->configIgnore = ['dir1', 'dir2'];

        $this->splitConfig = true;
        $this->localConfig = self::ComposerConfigSingle;
        $this->globalConfig = self::ComposerConfigSingle;
        $this->repository = null;

        $this->initGlobalConfiguration();
        $this->assertEquals('https://global.example.com', $this->configuration->get('url'));
        $this->assertArrayEquals($this->configIgnore, $this->configuration->get('ignore'));

        $this->splitConfig = false;
        $this->localConfig = self::ComposerConfigSingle;
        $this->globalConfig = self::ComposerConfigMulti;
        $this->repository = null;

        $this->initGlobalConfiguration();
        $this->assertEquals('https://example.com', $this->configuration->get('url'));

        $this->repository = 'A';

        $this->initGlobalConfiguration();
        $this->assertEquals('https://global.a.com', $this->configuration->get('url'));

        $this->repository = 'B';

        $this->initGlobalConfiguration();
        $this->assertEquals('https://global.b.com', $this->configuration->get('url'));

        $this->localConfig = self::ComposerConfigMulti;
        $this->globalConfig = self::ComposerConfigSingle;
        $this->repository = null;

        $this->initGlobalConfiguration();
        $this->expectException(\InvalidArgumentException::class);
        $this->configuration->get('url');

        $this->localConfig = self::ComposerConfigMulti;
        $this->globalConfig = self::ComposerConfigMulti;
        $this->repository = 'A';

        $this->initGlobalConfiguration();
        $this->assertEquals('https://a.com', $this->configuration->get('url'));
        $this->assertEquals('global-push-username-a', $this->configuration->get('username'));

        $this->repository = 'B';

        $this->initGlobalConfiguration();
        $this->assertEquals('https://b.com', $this->configuration->get('url'));
        $this->assertEquals('global-push-username-b', $this->configuration->get('username'));


        $this->splitConfig = false;

        $this->localConfig = self::ComposerConfigEmpty;
        $this->globalConfig = self::ComposerConfigSingle;
        $this->repository = null;

        $this->initGlobalConfiguration();
        $this->assertEquals('https://global.example.com', $this->configuration->get('url'));
        $this->assertEquals(null, $this->configuration->get('ignore'));

        $this->localConfig = self::ComposerConfigEmpty;
        $this->globalConfig = self::ComposerConfigMulti;
        $this->repository = 'A';

        $this->initGlobalConfiguration();
        $this->assertEquals('https://global.a.com', $this->configuration->get('url'));
        $this->assertEquals('global-push-username-a', $this->configuration->get('username'));

        $this->repository = 'B';

        $this->initGlobalConfiguration();
        $this->assertEquals('https://global.b.com', $this->configuration->get('url'));
        $this->assertEquals('global-push-username-b', $this->configuration->get('username'));
    }

    private function createInputMock()
    {
        $input = $this->createMock(InputInterface::class);
        $input->method('getOption')->willReturnCallback(function ($argument) {
            switch ($argument) {
                case 'name':
                    return "composer-push-name";
                case 'url':
                    return $this->configOptionUrl;
                case 'src-type':
                    return "my-src-type";
                case 'src-url':
                    return "my-src-url";
                case 'src-ref':
                    return "my-src-ref";
                case 'username':
                    return "my-username";
                case 'password':
                    return "my-password";
                case 'ignore':
                    return ["option-dir1", "option-dir2"];
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
        $this->configuration = new Configuration($this->inputMock, $this->composerMock, new NullIO());
    }

    private function createComposerMock()
    {
        $composer = $this->createMock(Composer::class);
        $packageInterface = $this->createMock(RootPackageInterface::class);

        $composer->method('getPackage')->willReturn($packageInterface);

        $packageInterface->method('getVersion')->willReturn('1.2.3');
        $packageInterface->method('getExtra')->willReturnCallback(function() {
            switch ($this->localConfig) {
                case self::ComposerConfigSingle:
                    return [
                        'push' => array_replace([
                            "ignore" => $this->configIgnore,
                        ], (!$this->splitConfig) ? [
                            'url' => 'https://example.com',
                            "username" => "push-username",
                            "password" => "push-password",
                            "type" => $this->extraConfigType,
                            "ssl-verify" => $this->extraVerifySsl,
                        ] : [])
                    ];
                case self::ComposerConfigMulti:
                    return [
                        'push' => array_replace_recursive([
                            [
                                'name' => 'A',
                                'url' => 'https://a.com',
                            ],
                            [
                                'name' => 'B',
                                'url' => 'https://b.com',
                            ]
                        ], (!$this->splitConfig) ? [
                            [
                                "username" => "push-username-a",
                                "password" => "push-password-a",
                            ],
                            [
                                "username" => "push-username-b",
                                "password" => "push-password-b",
                            ]
                        ] : [])
                    ];
                default:
                    return [];
            }
        });

        $pluginManager = $this->createMock(PluginManager::class);
        // PartialComposer is returned for 2.3.0+ composer
        $globalComposer = class_exists('Composer\PartialComposer')
            ? $this->createMock('Composer\PartialComposer')
            : $this->createMock('Composer\Composer');
        $globalPackageInterface = $this->createMock(RootPackageInterface::class);

        $composer->method('getPluginManager')->willReturn($pluginManager);
        $pluginManager->method('getGlobalComposer')->willReturn($globalComposer);
        $globalComposer->method('getPackage')->willReturn($globalPackageInterface);

        $globalPackageInterface->method('getExtra')->willReturnCallback(function () {
            switch ($this->globalConfig) {
                case self::ComposerConfigSingle:
                    return [
                        'push' => array_replace([
                            'url' => 'https://global.example.com',
                            "username" => "global-push-username",
                            "password" => "global-push-password",
                            "type" => $this->extraConfigType,
                            "ssl-verify" => $this->extraVerifySsl,
                        ], (!$this->splitConfig) ? [
                            "ignore" => $this->configIgnore,
                        ] : [])
                    ];
                case self::ComposerConfigMulti:
                    return [
                        'push' => array_replace_recursive([
                            [
                                'name' => 'B',
                                "username" => "global-push-username-b",
                                "password" => "global-push-password-b",
                            ],
                            [
                                'name' => 'A',
                                "username" => "global-push-username-a",
                                "password" => "global-push-password-a",
                            ]
                        ], (!$this->splitConfig) ? [
                            [
                                'url' => 'https://global.b.com',
                            ],
                            [
                                'url' => 'https://global.a.com',
                            ]
                        ] : [])
                    ];
                default:
                    return [];
            }
        });

        $packageInterface->method('getArchiveExcludes')->willReturnCallback(function() {
            return $this->composerPackageArchiveExcludes;
        });

        return $composer;
    }

    private function assertArrayEquals($expected, $result)
    {
        try {

            $this->assertSameSize($expected, $result);
        } catch (ExpectationFailedException $e) {
            echo " Expected: ";
            print_r($expected);
            echo " Received: ";
            print_r($result);
            throw $e;
        }

        foreach ($expected as $e) {
            $this->assertContains($e, $result);
        }
    }
}
