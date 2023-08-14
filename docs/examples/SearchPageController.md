The search page controller carries out the heavy-lifting when running a search query. 

_Please note:_  These code samples are provided "as-is" and are for information purposes only.  They are not complete, and should not be used in a production environment without a full review and adaptation as required.

```php
<?php

namespace BiffBangPow\Example\Control;

use BiffBangPow\Search\OpenSearchHelper;
use OpenSearch\Client;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Requirements;
use SilverStripe\View\ThemeResourceLoader;

class SearchPageController extends \PageController
{
    use Configurable;

    private static $results_per_page = 6;
    private static $max_resources = 100;


    private function index(HTTPRequest $request)
    {
        $search = trim($request->getVar('search'));

        if ($search != "") {
            $client = OpenSearchHelper::buildClient();

            $query = [
                'size' => 1000,
                'query' => [
                    'function_score' => [
                        'query' => [
                            'bool' => [
                                'must' => [
                                    [
                                        'multi_match' => [
                                            "query" => $search,
                                            "fields" => [
                                                "urlsegment^2", "title^2", "elementcontent", "content"
                                            ],
                                            "fuzziness" => "AUTO",
                                            "fuzzy_transpositions" => true,
                                            "auto_generate_synonyms_phrase_query" => true,
                                            "zero_terms_query" => "none",
                                            "lenient" => true,
                                            "max_expansions" => 50,
                                            "prefix_length" => 0
                                        ],
                                    ],
                                ],
                                'must_not' => [
                                    [
                                        'match' => [
                                            'link' => '',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'score_mode' => 'sum',
                        'boost_mode' => 'sum',
                    ],
                ],
                'collapse' => [
                    'field' => 'urlsegment'
                ],
            ];


            $searchparams = [
                'index' => OpenSearchHelper::environmentizeIndexName('pageindex'),
                'body' => $query
            ];

            $results = $client->search($searchparams);


            // Execute the search
            if (isset($results['hits'])) {
                $numResults = $results['hits']['total']['value'];
                $resultSet = $this->transformSearchResults($results['hits']['hits']);
                $paginatedResults = PaginatedList::create($resultSet, $request)
                    ->setPageLength($this->config()->get('results_per_page'));

                $output = (string)$this->renderWith('Layout/SearchPage_pageResults', ['PaginatedMatches' => $paginatedResults]);


            }
        }
    }

    /**
    * Convert the data from search into something we can use in the templates
    * @param $results
    * @return mixed
    */
    private function transformSearchResults($results)
    {
        $resultSet = ArrayList::create();
        foreach ($results as $result) {
            $record = $result['_source'];
            $newRecord = ArrayData::create($record);
            $newRecord->Score = $result['_score'];
            $resultSet->push($newRecord);
        }
        return $resultSet;
    }

}

```