<?php
/*
 * (c) lcavero <luiscaverodeveloper@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace LCavero\DoctrinePaginatorBundle\Paginator;

/**
 * Class PaginatorOptions
 * @author Luis Cavero Martínez <luiscaverodeveloper@gmail.com>
 */
class PaginatorOptions
{
    private $order;
    private $orderBy;

    private $page;
    private $perPage;

    private $search;

    private $filters;

    /**
     * PaginatorOptions constructor.
     * @param int $page
     * @param int|null $perPage
     * @param string $order
     * @param string|null $orderBy
     * @param array $search
     * @param array $filters
     */
    public function __construct(int $page = 1, int $perPage = null, $order = 'ASC', string $orderBy = null,
                                array $search = [], array $filters = [])
    {
        $this->page = $page;
        $this->perPage = $perPage;
        $this->order = $order;
        $this->orderBy = $orderBy;
        $this->search = $search;
        $this->filters = $filters;
    }

    /**
     * @return string
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @param string $order
     */
    public function setOrder(string $order)
    {
        $this->order = $order;
    }

    /**
     * @return null|string
     */
    public function getOrderBy()
    {
        return $this->orderBy;
    }

    /**
     * @param null|string $orderBy
     */
    public function setOrderBy($orderBy)
    {
        $this->orderBy = $orderBy;
    }

    /**
     * @return int
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * @param int $page
     */
    public function setPage(int $page)
    {
        $this->page = $page;
    }

    /**
     * @return int|null
     */
    public function getPerPage()
    {
        return $this->perPage;
    }

    /**
     * @param int|null $perPage
     */
    public function setPerPage($perPage)
    {
        $this->perPage = $perPage;
    }

    /**
     * @return array
     */
    public function getSearch()
    {
        return $this->search;
    }

    /**
     * @param array $search
     */
    public function setSearch(array $search)
    {
        $this->search = $search;
    }

    /**
     * @return array
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * @param array $filters
     */
    public function setFilter(array $filters)
    {
        $this->filters = $filters;
    }
}