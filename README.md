# silverstripe-opensearch

Site search using the Opensearch engine

This module is an Opensearch provider for the [Silverstripe Search Service](https://github.com/silverstripe/silverstripe-search-service).

The module depends upon the search service for index maintenance, and utilises the official OpenSearch PHP client library for communication with the backend service.

## Installation

Install the module and its dependencies with:

```
composer require biffbangpow/silverstripe-opensearch 
```

## Configuration

### Environment 
A number of environment variables are required for the search service to work:

```dotenv
OPENSEARCH_ENDPOINTS=searchnode.example.com
OPENSEARCH_USER=searchuser
OPENSEARCH_PASSWORD=password
OPENSEARCH_INDEX_PREFIX=myindex
OPENSEARCH_PORT=9200
OPENSEARCH_SCHEME=https
```

Hopefully these parameters are self-explanatory!


### YML config

Indexes are configured using YML using the format shown below (further information can be found on the Silverstripe search service repo).

Valid field types for OpenSearch are:

- binary
- boolean
- date
- ip
- keyword
- float
- integer
- text

**Warning:**  Changing field types after you have configured the OpenSearch engine can cause problems.   You may need to delete, rebuild and reindex in order to make changes at this level

An example config is shown below, but please refer to the [main search service documentation](https://github.com/silverstripe/silverstripe-search-service/blob/2/docs/en/configuration.md) to work out what you need!

```yaml
SilverStripe\SearchService\Service\IndexConfiguration:
  indexes:
    pageindex:
      includeClasses:
        SilverStripe\CMS\Model\SiteTree:
          fields:
            title:
              property: Title
              options:
                type: text
            urlsegment:
              property: URLSegment
              options:
                type: keyword
            link:
              property: AbsoluteLink
              options:
                type: keyword
            content:
              property: Content
              options:
                type: text
            elementcontent:
              property: ElementsForSearch
              options:
                type: text
```

## Indexing

The search service depends on the [Silverstripe Queued Jobs](https://github.com/symbiote/silverstripe-queuedjobs/tree/4) module to deal with indexing.
In order for this to work properly, it is strongly recommended that you set up a cron job / scheduled task to poll the jobs list and execute any jobs which have been queued (see the [queued jobs documentation](https://github.com/symbiote/silverstripe-queuedjobs/blob/4/docs/en/index.md) for more details.)

A typical cron entry may look something like this:

```
*/1 * * * * /path/to/silverstripe/vendor/bin/sake dev/tasks/ProcessJobQueueTask
```


## Searching

Since the search query code will depend on the structure of your indexes, nothing is provided out-of-the-box.   
Some samples of code are included, however, which provide an example of how this may work.  Please see the docs directory for these.