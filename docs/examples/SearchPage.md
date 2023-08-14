One of the simplest ways to implement a search results page is to just create a new Page subclass, allowing the standard CMS routing to deal with the request:

```php
<?php

namespace BiffBangPow\Example\Page;

use BiffBangPow\Example\Control\SearchPageController;

class SearchPage extends Page
{
    private static $table_name = 'SearchPage';
    private static $controller_name = SearchPageController::class;
}
```

_Please note:_  These code samples are provided "as-is" and are for information purposes only.  They are not complete, and should not be used in a production environment without a full review and adaptation as required.