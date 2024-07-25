<?php

namespace RepositoryProvider;

use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Elendev\ComposerPush\Configuration;
use Elendev\ComposerPush\RepositoryProvider\NexusProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class NexusProviderTest extends TestCase
{
    public function testGetUrl()
    {
        $configurationMock = $this->createBaseConfigurationMock();

        $nexusProvider = new NexusProvider($configurationMock, new NullIO());

        $this->assertEquals(
            'https://example.com/my-repository/packages/upload/my-package/2.1.0',
            $nexusProvider->getUrl(),
        );
    }

    /**
     * @covers \Elendev\ComposerPush\RepositoryProvider\NexusProvider::sendFile
     */
    public function testSendFile()
    {
        $configurationMock = $this->createBaseConfigurationMock();

        $mock = new MockHandler([new Response(200)]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $nexusProvider = new NexusProvider(
            $configurationMock,
            new NullIO(),
            $client,
        );

        $nexusProvider->sendFile($this->getFilePath());

        $request = $mock->getLastRequest();

        $this->assertEquals('https', $request->getUri()->getScheme());
        $this->assertEquals('example.com', $request->getUri()->getHost());
        $this->assertEquals(
            '/my-repository/packages/upload/my-package/2.1.0',
            $request->getUri()->getPath(),
        );
        $this->assertEquals('PUT', $request->getMethod());
        $this->assertEmpty($request->getHeader('Authorization'));
        $this->assertEquals(
            'Simple test file to push.',
            $request->getBody()->getContents(),
        );
    }

    /**
     * @covers \Elendev\ComposerPush\RepositoryProvider\NexusProvider::sendFile
     */
    public function testSendFileWithAuthentication()
    {
        $configurationMock = $this->createBaseConfigurationMock();

        $configurationMock->method('getOptionUsername')->willReturn('admin');
        $configurationMock->method('getOptionPassword')->willReturn('password');

        $mock = new MockHandler([new Response(200)]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $nexusProvider = new NexusProvider(
            $configurationMock,
            new NullIO(),
            $client,
        );

        $nexusProvider->sendFile($this->getFilePath());

        $request = $mock->getLastRequest();

        $this->assertEquals('https', $request->getUri()->getScheme());
        $this->assertEquals('example.com', $request->getUri()->getHost());
        $this->assertEquals(
            '/my-repository/packages/upload/my-package/2.1.0',
            $request->getUri()->getPath(),
        );
        $this->assertEquals('PUT', $request->getMethod());

        $this->assertEquals(
            'Basic ' . base64_encode('admin:password'),
            $request->getHeader('Authorization')[0],
        );

        $this->assertEquals(
            'Simple test file to push.',
            $request->getBody()->getContents(),
        );
    }

    /**
     * @covers \Elendev\ComposerPush\RepositoryProvider\NexusProvider::sendFile
     */
    public function testSendFileWithConfigCredentials()
    {
        $configurationMock = $this->createBaseConfigurationMock();

        $configurationMock
            ->method('get')
            ->willReturnCallback(function ($parameter) {
                switch ($parameter) {
                    case 'username':
                        return 'admin';
                    case 'password':
                        return 'my-password';
                }
            });

        $mock = new MockHandler([new Response(200)]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $nexusProvider = new NexusProvider(
            $configurationMock,
            new NullIO(),
            $client,
        );

        $nexusProvider->sendFile($this->getFilePath());

        $request = $mock->getLastRequest();

        $this->assertEquals('https', $request->getUri()->getScheme());
        $this->assertEquals('example.com', $request->getUri()->getHost());
        $this->assertEquals(
            '/my-repository/packages/upload/my-package/2.1.0',
            $request->getUri()->getPath(),
        );
        $this->assertEquals('PUT', $request->getMethod());

        $this->assertEquals(
            'Basic ' . base64_encode('admin:my-password'),
            $request->getHeader('Authorization')[0],
        );

        $this->assertEquals(
            'Simple test file to push.',
            $request->getBody()->getContents(),
        );
    }

    /**
     * @covers \Elendev\ComposerPush\RepositoryProvider\NexusProvider::sendFile
     */
    public function testSendFileWithAuthenticationCredentials()
    {
        $configurationMock = $this->createBaseConfigurationMock();
        $ioMock = $this->createMock(IOInterface::class);
        $ioMock->method('hasAuthentication')->willReturn(true);
        $ioMock->method('getAuthentication')->willReturn([
            'username' => 'admin',
            'password' => 'my-password',
        ]);

        $mock = new MockHandler([new Response(200)]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $nexusProvider = new NexusProvider(
            $configurationMock,
            $ioMock,
            $client,
        );

        $nexusProvider->sendFile($this->getFilePath());

        $request = $mock->getLastRequest();

        $this->assertEquals('https', $request->getUri()->getScheme());
        $this->assertEquals('example.com', $request->getUri()->getHost());
        $this->assertEquals(
            '/my-repository/packages/upload/my-package/2.1.0',
            $request->getUri()->getPath(),
        );
        $this->assertEquals('PUT', $request->getMethod());

        $this->assertEquals(
            'Basic ' . base64_encode('admin:my-password'),
            $request->getHeader('Authorization')[0],
        );

        $this->assertEquals(
            'Simple test file to push.',
            $request->getBody()->getContents(),
        );
    }

    /**
     * @covers \Elendev\ComposerPush\RepositoryProvider\NexusProvider::sendFile
     */
    public function testSendFileWithMultipleCredentials()
    {
        $configurationMock = $this->createBaseConfigurationMock();

        $configurationMock
            ->method('get')
            ->willReturnCallback(function ($parameter) {
                switch ($parameter) {
                    case 'username':
                        return '';
                    case 'password':
                        return '';
                    case 'access-token':
                        return '';
                }
            });

        $mock = new MockHandler([new Response(200)]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $nexusProvider = new NexusProvider(
            $configurationMock,
            new NullIO(),
            $client,
        );

        $nexusProvider->sendFile($this->getFilePath());

        $request = $mock->getLastRequest();

        $this->assertEquals('https', $request->getUri()->getScheme());
        $this->assertEquals('example.com', $request->getUri()->getHost());
        $this->assertEquals(
            '/my-repository/packages/upload/my-package/2.1.0',
            $request->getUri()->getPath(),
        );
        $this->assertEquals('PUT', $request->getMethod());

        $this->assertEmpty($request->getHeader('Authorization')); // Fallback to "none" authentication

        $this->assertEquals(
            'Simple test file to push.',
            $request->getBody()->getContents(),
        );
    }

    /**
     * @covers \Elendev\ComposerPush\RepositoryProvider\NexusProvider::sendFile
     */
    public function testSendFileWithBadCredentials()
    {
        $configurationMock = $this->createBaseConfigurationMock();

        $configurationMock
            ->method('get')
            ->willReturnCallback(function ($parameter) {
                switch ($parameter) {
                    case 'username':
                        return 'admin';
                    case 'password':
                        return 'my-password';
                }
            });

        $mock = new MockHandler([new Response(401)]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $nexusProvider = new NexusProvider(
            $configurationMock,
            new NullIO(),
            $client,
        );

        $this->expectException(\Exception::class);
        $nexusProvider->sendFile($this->getFilePath());
    }

    /**
     * @covers \Elendev\ComposerPush\RepositoryProvider\NexusProvider::sendFile
     */
    public function testSendFileWithAccessToken()
    {
        $configurationMock = $this->createBaseConfigurationMock();
        $ioMock = $this->createMock(IOInterface::class);
        $configurationMock->method('getAccessToken')->willReturn('my-token');

        $mock = new MockHandler([new Response(200)]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $nexusProvider = new NexusProvider(
            $configurationMock,
            $ioMock,
            $client,
        );

        $nexusProvider->sendFile($this->getFilePath());

        $request = $mock->getLastRequest();

        $this->assertEquals('https', $request->getUri()->getScheme());
        $this->assertEquals('example.com', $request->getUri()->getHost());
        $this->assertEquals(
            '/my-repository/packages/upload/my-package/2.1.0',
            $request->getUri()->getPath(),
        );
        $this->assertEquals('PUT', $request->getMethod());

        $this->assertEquals(
            'Bearer my-token',
            $request->getHeader('Authorization')[0],
        );

        $this->assertEquals(
            'Simple test file to push.',
            $request->getBody()->getContents(),
        );
    }

    /**
     * Create a base configuration mock
     * @return Configuration|\PHPUnit\Framework\MockObject\MockObject
     */
    private function createBaseConfigurationMock()
    {
        $configurationMock = $this->createMock(Configuration::class);
        $configurationMock
            ->method('getUrl')
            ->willReturn('https://example.com/my-repository/');
        $configurationMock->method('getPackageName')->willReturn('my-package');
        $configurationMock->method('getVersion')->willReturn('2.1.0');

        return $configurationMock;
    }

    /**
     * Return the test file path
     * @return string
     */
    private function getFilePath()
    {
        return __DIR__ . '/testFile.txt';
    }
}
