<?php

namespace BiffBangPow\Search;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use OpenSearch\Client;
use OpenSearch\ClientBuilder;
use SilverStripe\Core\Environment;
use SilverStripe\SearchService\Service\IndexConfiguration;

class OpenSearchHelper
{
    public static function buildClient(): Client
    {
        $endpoints = Environment::getEnv('OPENSEARCH_ENDPOINTS') ?? null;
        $user = Environment::getEnv('OPENSEARCH_USER') ?? null;
        $password = Environment::getEnv('OPENSEARCH_PASSWORD') ?? null;
        $scheme = Environment::getEnv('OPENSEARCH_SCHEME') ?? null;
        $port = Environment::getEnv('OPENSEARCH_PORT') ?? null;

        if (!$endpoints || !$user || !$password || !$port || !$scheme) {
            throw new \Exception('Please ensure you have added your OpenSearch credentials to the environment');
        }

        $configuredEndpoints = explode(',', $endpoints);
        $hosts = [];
        foreach ($configuredEndpoints as $configuredEndpoint) {
            $hosts[] = [
                'host' => $configuredEndpoint,
                'port' => $port,
                'scheme' => $scheme,
                'user' => $user,
                'pass' => $password
            ];
        }

        $osLogger = new Logger('opensearch');
        $osLogger->pushHandler(new StreamHandler('/app/logs/opensearch.log', Logger::DEBUG));

        return (new ClientBuilder())
            ->setHosts($hosts)
            ->setSSLVerification(false)
            ->setLogger($osLogger)
            ->build();
    }


    public static function environmentizeIndexName(string $indexName): string
    {
        $sitePrefix = Environment::getEnv('OPENSEARCH_INDEX_PREFIX') ?? null;
        if ($sitePrefix) {
            $indexName = $sitePrefix . '-' . $indexName;
        }

        $variant = IndexConfiguration::singleton()->getIndexVariant();
        if ($variant) {
            return sprintf('%s-%s', $variant, $indexName);
        }

        return $indexName;
    }
}
