<?php

namespace BiffBangPow\Search\Service;

use OpenSearch\ClientBuilder;
use SilverStripe\Core\Injector\Factory;

class ClientFactory implements Factory
{

    public function create($service, array $params = [])
    {
        $endpoints = $params['endpoints'] ?? null;
        $user = $params['user'] ?? null;
        $password = $params['password'] ?? null;

        if (!$endpoints || !$user || !$password) {
            throw new \Exception('Please ensure you have added your OpenSearch credentials to the environment');
        }

        return (new ClientBuilder())
        ->setHosts(explode(",", $endpoints))
            ->setBasicAuthentication($user, $password)
            ->build();
    }
}
