<?php

namespace BiffBangPow\Search\Service;

use BiffBangPow\Search\OpenSearchHelper;
use OpenSearch\Client;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\SearchService\Exception\IndexConfigurationException;
use SilverStripe\SearchService\Interfaces\BatchDocumentRemovalInterface;
use SilverStripe\SearchService\Interfaces\DocumentInterface;
use SilverStripe\SearchService\Interfaces\IndexingInterface;
use SilverStripe\SearchService\Schema\Field;
use SilverStripe\SearchService\Service\DocumentBuilder;
use SilverStripe\SearchService\Service\IndexConfiguration;
use SilverStripe\SearchService\Service\Traits\ConfigurationAware;

class OpenSearchService implements IndexingInterface, BatchDocumentRemovalInterface
{
    use Configurable;
    use ConfigurationAware;
    use Injectable;

    private const DEFAULT_FIELD_TYPE = 'keyword';

    private Client $client;

    private DocumentBuilder $builder;

    /**
     * @config
     * @var array $valid_field_types
     */
    private static array $valid_field_types = [
        'binary',
        'boolean',
        'date',
        'ip',
        'keyword',
        'float',
        'integer',
        'text'
    ];

    private static array $system_index_fields = [
        'id' => 'keyword',
        'source_class' => 'keyword',
        'record_base_class' => 'keyword',
        'record_id' => 'integer'
    ];


    public function __construct(IndexConfiguration $configuration, DocumentBuilder $exporter)
    {
        $client = OpenSearchHelper::buildClient();
        $this->setClient($client);
        $this->setConfiguration($configuration);
        $this->setBuilder($exporter);
    }

    public function environmentizeIndex(string $indexName): string
    {
        return OpenSearchHelper::environmentizeIndexName($indexName);
    }

    public function addDocuments(array $documents): array
    {
        $ids = [];
        foreach ($documents as $document) {
            if ($document->shouldIndex()) {
                $fields = DocumentBuilder::singleton()->toArray($document);
                $indexes = IndexConfiguration::singleton()->getIndexesForDocument($document);

                //Injector::inst()->get(LoggerInterface::class)->info(print_r($fields, true));
                $client = $this->getClient();

                foreach (array_keys($indexes) as $indexName) {
                    $envIdx = $this->environmentizeIndex($indexName);

                        $res = $client->update([
                            'index' => $envIdx,
                            'body' => ['doc' => $fields, 'doc_as_upsert' => true],
                            'id' => $document->getIdentifier()
                        ]);

                    if (isset($res['_id'])) {
                        $ids[] = $res['_id'];
                    }

                    Injector::inst()->get(LoggerInterface::class)->info(print_r($res, true));
                }
            }
        }
        return $ids;
    }


    public function removeDocument(DocumentInterface $document): ?string
    {
        $docs = $this->removeDocuments([$document]);
        return array_shift($docs);
    }

    public function removeDocuments(array $documents): array
    {
        $docIds = [];
        foreach ($documents as $document) {
            $indexes = IndexConfiguration::singleton()->getIndexesForDocument($document);
            foreach (array_keys($indexes) as $indexName) {
                $res = $this->getClient()->deleteByQuery([
                    'index' => $this->environmentizeIndex($indexName),
                    'body' => ['query' => [
                        'match' => [
                            "id" => $document->getIdentifier()
                        ]
                    ]]
                ]);;
                Injector::inst()->get(LoggerInterface::class)->info(print_r($res, true));
                if (isset($res['deleted'])) {
                    $docIds[] = $document->getIdentifier();
                }
            }
        }
        return $docIds;
    }

    public function removeAllDocuments(string $indexName): int
    {
        $res = $this->getClient()->deleteByQuery([
            'index' => $this->environmentizeIndex($indexName),
            'body' => ['query' => [
                'match_all' => []
            ]]
        ]);

        if (isset($res['deleted'])) {
            return (int)$res['deleted'];
        }
        return 0;
    }

    public function addDocument(DocumentInterface $document): ?string
    {
        $processedIds = $this->addDocuments([$document]);
        return array_shift($processedIds);
    }


    public function getMaxDocumentSize(): int
    {
        return 500000;
    }

    public function getDocument(string $id): ?DocumentInterface
    {
        // TODO: Implement getDocument() method.
    }

    public function getDocuments(array $ids): array
    {
        // TODO: Implement getDocuments() method.
    }

    public function listDocuments(string $indexName, ?int $pageSize = null, int $currentPage = 0): array
    {
        $params = [
            'index' => $this->environmentizeIndex($indexName),
            'query' => ''
        ];

        if ((int)$pageSize > 0) {
            $start = $currentPage * $pageSize;
            $params['from'] = $start;
            $params['size'] = $pageSize;
        }

        $docs = $this->getClient()->search($params);

    }

    public function getDocumentTotal(string $indexName): int
    {
        $res = $this->getClient()->count([
            'index' => $this->environmentizeIndex($indexName)
        ]);
        return (isset($res['count'])) ? $res['count'] : 0;
    }

    public function configure(): array
    {
        Injector::inst()->get(LoggerInterface::class)->info('Configuring opensearch');

        $schemas = [];

        foreach (array_keys($this->getConfiguration()->getIndexes()) as $indexName) {

            Injector::inst()->get(LoggerInterface::class)->info('Index name: ' . $indexName);

            $this->validateIndex($indexName);
            $index = $this->findOrMakeIndex($indexName);
            $this->validateIndexMappings($indexName, $index);
            $schemas[$indexName] = $index;

        }

        return $schemas;
    }

    public function validateField(string $field): void
    {
        if (!preg_match('/[a-z0-9]+/', $field)) {
            throw new IndexConfigurationException('Fields can only be lowercase and numbers');
        }
    }

    public function getExternalURL(): ?string
    {
        return null;
    }

    public function getExternalURLDescription(): ?string
    {
        return null;
    }

    public function getDocumentationURL(): ?string
    {
        return null;
    }


    public function getClient(): Client
    {
        return $this->client;
    }

    public function getBuilder(): DocumentBuilder
    {
        return $this->builder;
    }

    private function setClient(Client $client): OpenSearchService
    {
        $this->client = $client;
        return $this;
    }

    private function setBuilder(DocumentBuilder $builder): OpenSearchService
    {
        $this->builder = $builder;
        return $this;
    }


    private function findOrMakeIndex(string $indexName)
    {
        $indexName = $this->environmentizeIndex($indexName);

        try {
            $check = $this->getClient()->indices()->exists([
                'index' => $indexName
            ]);
            if ($check) {
                Injector::inst()->get(LoggerInterface::class)->info('Index found - ' . $indexName);
                $index = $this->getClient()->indices()->get([
                    'index' => $indexName
                ]);
            } else {
                Injector::inst()->get(LoggerInterface::class)->info('Creating new index - ' . $indexName);

                $index = $this->getClient()->indices()->create([
                    'index' => $indexName,
                    'body' => [
                        'settings' => $this->getIndexSettings()
                    ]
                ]);
            }
            return $index;
        } catch (\Exception $e) {
            Injector::inst()->get(LoggerInterface::class)->error($e->getMessage());
        }

    }


    private function getIndexSettings()
    {
        return [
            'number_of_shards' => 2,
            'analysis' => [
                'filter' => [
                    'filter_ps_en' => [
                        'type' => 'porter_stem',
                        'language' => 'English'
                    ]
                ],
                'analyzer' => [
                    'stemmed_analyzer' => [
                        'filter' => ['lowercase', 'filter_ps_en'],
                        'type' => 'custom',
                        'tokenizer' => 'whitespace',
                        'char_filter' => ['html_strip']
                    ]
                ]
            ]
        ];
    }

    /**
     * @param string $index
     * @throws IndexConfigurationException
     */
    private function validateIndex(string $index): void
    {
        $validTypes = $this->config()->get('valid_field_types');
        $map = [];

        // Loop through each Class that has a definition for this index
        foreach ($this->getConfiguration()->getClassesForIndex($index) as $class) {

            // Loop through each field that has been defined for that Class
            foreach ($this->getConfiguration()->getFieldsForClass($class) as $field) {
                // Check to see if a Type has been defined, or just default to what we have defined
                $type = $field->getOption('type') ?? self::DEFAULT_FIELD_TYPE;

                // We can't progress if a type that we don't support has been defined
                if (!in_array($type, $validTypes, true)) {
                    throw new IndexConfigurationException(sprintf(
                        'Invalid field type: %s',
                        $type
                    ));
                }

                // Check to see if this field name has been defined by any other Class, and if it has, let's grab what
                // "type" it was described as
                $alreadyDefined = $map[$field->getSearchFieldName()] ?? null;

                // This field name has been defined by another Class, and it was described as a different type. We
                // don't support multiple types for a field, so we need to throw an Exception
                if ($alreadyDefined && $alreadyDefined !== $type) {
                    throw new IndexConfigurationException(sprintf(
                        'Field "%s" is defined twice in the same index with differing types.
                        (%s and %s). Consider changing the field name or explicitly defining
                        the type on each usage',
                        $field->getSearchFieldName(),
                        $alreadyDefined,
                        $type
                    ));
                }

                // Store this field and its type for later comparison
                $map[$field->getSearchFieldName()] = $type;
            }
        }

    }


    /**
     * Check to see if there are any important differences between the configured mappings
     * and the local config.  If there is, we need to trigger an update
     * @param $index
     * @return void
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    private function validateIndexMappings($indexName, $index)
    {
        $action = false;
        $plainIndexName = $indexName;
        $indexName = $this->environmentizeIndex($indexName);

        //If the mappings isn't set, just bail out - we need to update
        if (!isset($index[$indexName]['mappings'])) {
            Injector::inst()->get(LoggerInterface::class)->info('Mappings not set on index');
            $action = 'update';
        } else {
            Injector::inst()->get(LoggerInterface::class)->info('Mappings set - checking them');

            //Loop through the mappings and check that they match the configured fields and field types
            //If we're missing a mapping, we can just update
            //If a mapping has changed type, or we have mappings that are no longer required we need to create a
            // new index with the right mappings and use the reindex API to move all the documents


            $action = 'update';  // Force an update during dev!

            $definedFields = $this->getConfiguration()->getFieldsForIndex($plainIndexName);
            /**
             * @var Field $definedField
             */
            foreach ($definedFields as $definedField) {
                $fieldName = $definedField->getSearchFieldName();
                $fieldType = $definedField->getOption('type') ?? self::DEFAULT_FIELD_TYPE;


            }
        }

        switch ($action) {
            case 'update':
                $this->updateMapping($plainIndexName);
                break;
            case 'remap':

                break;
            default:
                return;
        }
    }

    private function updateMapping($indexName)
    {
        $mappings = [];
        $definedFields = $this->getConfiguration()->getFieldsForIndex($indexName);
        foreach ($definedFields as $definedField) {
            $fieldName = $definedField->getSearchFieldName();
            $fieldType = $definedField->getOption('type') ?? self::DEFAULT_FIELD_TYPE;

            $mappings[$fieldName] = array_merge(['type' => $fieldType], $this->getFieldSettings($fieldType));
        }

        //Add in the system fields
        foreach ($this->config()->get('system_index_fields') as $fieldName => $fieldType) {
            $mappings[$fieldName] = array_merge(['type' => $fieldType], $this->getFieldSettings($fieldType));
        }

        $req = [
            'index' => $this->environmentizeIndex($indexName),
            'body' => ["properties" => $mappings]
        ];

        Injector::inst()->get(LoggerInterface::class)->info(json_encode($req));

        $res = $this->getClient()->indices()->putMapping($req);

        Injector::inst()->get(LoggerInterface::class)->info(print_r($res, true));
    }


    private function getFieldSettings($fieldType)
    {
        switch ($fieldType) {
            case 'text':
                $res = ['analyzer' => 'stemmed_analyzer'];
                break;
            default:
                $res = [];
                break;
        }
        return $res;
    }


}
