<?php

namespace App\Services;

use Google\Cloud\SecretManager\V1\SecretManagerServiceClient;

class SecretManagerService
{
    protected $secretManagerClient;
    protected $projectId;

    public function __construct()
    {
        $this->secretManagerClient = new SecretManagerServiceClient();
        $this->projectId = 'shortsai-b68d2';
    }

    public function getSecret(string $secretName, string $version = 'latest'): string
    {
        $name = sprintf('projects/%s/secrets/%s/versions/%s', $this->projectId, $secretName, $version);
        $response = $this->secretManagerClient->accessSecretVersion($name);
        return $response->getPayload()->getData();
    }
}
