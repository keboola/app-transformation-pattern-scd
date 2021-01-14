<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Tests;

use Keboola\StorageApi\BranchAwareClient;
use Keboola\StorageApi\Client;
use Keboola\TransformationPatternScd\ApiClientFactory;
use Keboola\TransformationPatternScd\Config;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

class ApiClientFactoryTest extends TestCase
{
    public function testBranchIdSet(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getStorageApiUrl')->willReturn('https://connection.keboola.com/');
        $config->method('getStorageApiToken')->willReturn('1234abc');
        $config->method('getStorageBranchId')->willReturn('my-dev-branch');
        $apiClientFactory = new ApiClientFactory($config);
        $apiClient = $apiClientFactory->create();
        Assert::assertSame(BranchAwareClient::class, get_class($apiClient));
    }

    public function testBranchIdNotSet(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getStorageApiUrl')->willReturn('https://connection.keboola.com/');
        $config->method('getStorageApiToken')->willReturn('1234abc');
        $config->method('getStorageBranchId')->willReturn(null);
        $apiClientFactory = new ApiClientFactory($config);
        $apiClient = $apiClientFactory->create();
        Assert::assertSame(Client::class, get_class($apiClient));
    }
}
