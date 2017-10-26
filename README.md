DoctrinePaginatorBundle
=======================

DoctrinePaginatorBundle bundle allows you to page your DQL statements.   
It is ideal for returning paged datasets, including searching and filtering data. 

## Table of Contents

1. [Installation](#installation)
2. [Basic Usage](#basic-usage)
3. [Pagination Results](#pagination-results)
4. [Search and Filters](#search-and-filters)
4.1 [Behavior](#behavior)
4.2 [Structure](#structure)
4.3 [Associations](#associations)
4.4 [Assocations and ORDER BY](#associations-and-order-by)
5 [Configuration](#configuration)
5.1 [Accepted Boolean values](#accepted-boolean-values)
5.2 [Strict mode](#strict-mode)

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

Call **paginate** returns some interesting info:
- **data:** An array with the paginated result
- **count:** Total number of items (filtered but not paged)
- **current_page:** The current page
- **total_pages:** Total number of pages of the filtered result
- **per_page:** Items by page
    
That info it's ussually interesting to display pagination options in a frontend

Search and Filters
------------------

###### BEHAVIOR

Filters and search are similar, the difference is that a search is a set of conditions that at least one of them must match, however, each filter must match.  
You can think about this how a conditional structure:

```
    (STANDARD SENTENCE) AND (FILTER 1 AND FILTER 2) AND (SEARCH 1 OR SEARCH 2)
```

###### STRUCTURE

Search and filters have the same structure, an array with key **=>** value.  
The key is the name of the entity field, the value is ... obviously the value.

So if you have the following entity:

```php
class User
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="email", type="string", length=255, unique=true)
     */
    protected $email;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255)
     */
    protected $name;
    
    /**
     * @ORM\ManyToMany(targetEntity="Group", mappedBy="users")
     */
    private $groups;
    
    // ..
}
```
You can make, for example:

```php
    // Returns users which email contains lcavero or which name contains luis (or Luis, all search and filters are case insensitive)
    $search  = ['email' => 'lcavero', 'name' => 'Luis'];
    
    // Returns users which email contains roma and which name contains susana
    $filters = ['email' => 'roma', 'name' => 'Susana'];
    
    // Returns users which email contains maria and whitch name contains paula and can optionally contains jonh
    $search  = ['email' => 'maria', 'name' => 'Jonh'];
    $filters = ['name' => 'Paula'];
    
```

###### ASSOCIATIONS
Ok, **thats great!** But what about entity associations?
This **User** class have a **many to many** association with groups, and I want to filter by them!  
  
Ok, no problem, suppose the following entity:

```php
class Group
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255)
     */
    protected $name;
    
    /**
     * @ORM\ManyToMany(targetEntity="User", inversedBy="groups")
     */
    private $users;
    // ..
}
```

As you can see, a group can be represented by the field **name**, so I want to filter by the groups name, and a User has a field **groups** to makes the association possible.  
So ... if you want filter the users whose groups are named *GroupA*, you should do:

```php
    // You can do it also with search and order_by
    $filter = ['groups.name' => 'GroupA']
```

**That's All!** You can do it with any type of association (1-1, 1-N, N-M ....).


###### ASSOCIATIONS AND ORDER BY
You can use the above sintax to order by an association field, but don't forget that you can only order by **One-to-One** or **Many-to-One** associations.

Configuration
-------------

###### ACCEPTED BOOLEAN VALUES

You can define the accepted boolean values, that means, by default if you search on a boolean field with a not-boolean value, the search is ignored (the DQL sentence searchs for -1 value instead 0/1). Maybe you want define your own accepted boolean values.

###### STRICT MODE

You can also enable/disable the *strict mode*. By defaults, if you search *"Hello World"*, the DQL sentence searchs for the words *"Hello"*, *"World"* and *"Hello World"*, but you can dissable it enabling the *strict mode*. That means, only *"Hello World"* can matchs.

```yaml
# app/config/config.yml

lcavero_doctrine_paginator:
    mapping:
        boolean_true_values: [1, 'true']
        boolean_false_values: [0, 'false']
        
    search:
        strict_mode: false
```
