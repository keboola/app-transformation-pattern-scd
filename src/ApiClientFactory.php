<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd;

use Keboola\StorageApi\Client;
use Keboola\StorageApiBranch\ClientWrapper;

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
            new Client([
               'url' => $this->config->getStorageApiUrl(),
               'token' => $this->config->getStorageApiToken(),
            ]),
            null,
            null
        );

        $branchId = $this->config->getStorageBranchId();
        if ($branchId) {
            $clientWrapper->setBranchId($branchId);
            return $clientWrapper->getBranchClient();
        }

        return $clientWrapper->getBasicClient();
    }
}
