DoctrinePaginatorBundle
=======================

DoctrinePaginatorBundle bundle allows you to page your DQL statements. 
It is ideal for returning paged datasets, including searching and filtering data. 

Installation
------------
You can install it using composer

```
composer require lcavero/doctrine-paginator-bundle dev-master
```


Then add the bundle to your kernel:
```php
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = [
            // ...

            new LCavero\DoctrinePaginatorBundle\DoctrinePaginatorBundle(),
        ];

        // ...
    }
}
```

What does this bundle?

It converts an initial DQL sentence to a paginated sentence based on the paginator and search options.

Basic usage
-----------
```php
use LCavero\DoctrinePaginatorBundle\Paginator\PaginatorOptions;

class MyController
{
    public function getUsersAction()
    {
        // Define the standard DQL sentence
        $dql = 'SELECT a FROM AppBundle:User WHERE a.age > 18'
        
        // Create the Query
        $query = $entity_manager->createQuery($dql);
        
        // Define paginator options (page, per_page, order, order_by, search, filters)
        $opts = new PaginatorOptions(4, 10, 'asc', 'id');
        
        // Paginate the data, this returns an array with the data and other interesting info
        $pagination = $container->get('lcav_doctrine_paginator')->paginate($query, $opts);
        
        // Now you have the users paginated and filtered, you can return them or do something amazing
        $users = $pagination['data'];
        
        // ..
    }
}
```

Pagination results
------------------

Calling **paginate** returns some interesting info:
    - data: An array with the paginated result
    - count: Total number of items (filtered but not paged)
    - current_page: The current page
    - total_pages: Total number of pages of the filtered result
    - per_page: Items by page
    
That info it's ussually interesting to display pagination options in a frontend

Search and Filters
------------------

Filters and search are similar, the difference is that a search is a set of conditions that at least one of them must match, however, each filter must match.
You can think about this how a conditional structure:
````
    (STANDARD SENTENCE) AND (FILTER 1 AND FILTER 2) AND (SEARCH 1 OR SEARCH 2)
```