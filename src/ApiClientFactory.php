<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd;

use Keboola\StorageApi\Client;
use Keboola\StorageApiBranch\ClientWrapper;
use Keboola\StorageApiBranch\Factory\ClientOptions;

class ApiClientFactory
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function create(): Client
    {
        $clientWrapper = new ClientWrapper(
            new ClientOptions(
                $this->config->getStorageApiUrl(),
                $this->config->getStorageApiToken(),
                $this->config->getStorageBranchId()
            ),
        );

        return $clientWrapper->getBranchClientIfAvailable();
    }
}
