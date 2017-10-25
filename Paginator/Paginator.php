<?php
/*
 * (c) lcavero <luiscaverodeveloper@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace LCavero\DoctrinePaginatorBundle\Paginator;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Query;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Class Paginator
 * @author Luis Cavero Martínez <luiscaverodeveloper@gmail.com>
 */
class Paginator
{
    private $mandatoryOrderByDql;
    private $mandatoryGroupByDql;
    private $mandatoryWhereDql;

    private $rootAlias;
    private $rootEntity;
    private $rootIdentifiers;

    /**
     * @var \Doctrine\ORM\Mapping\ClassMetadata
     */
    private $rootMetadata;

    /**
     * @var Query
     */
    private $query;

    private $paramRef;
    private $classRef;
    private $associationJoinsDql;

    private $associationClasses;

    private $booleanTrueValues;
    private $booleanFalseValues;

    /**
     * @var EntityManager
     */
    private $em;

    /**
     * Paginator constructor.
     * @param array $boolean_true_values
     * @param array $boolean_false_values
     */
    public function __construct(array $boolean_true_values, array $boolean_false_values)
    {
        $this->paramRef = 1;
        $this->classRef = 1;
        $this->associationJoinsDql = '';
        $this->mandatoryGroupByDql = '';
        $this->mandatoryOrderByDql = '';
        $this->associationClasses = [];
        $this->booleanTrueValues = array_map(function ($v){return strval($v);}, $boolean_true_values);
        $this->booleanFalseValues = array_map(function ($v){return strval($v);}, $boolean_false_values);
    }

    /**
     * paginate
     * @param Query $query
     * @param PaginatorOptions|null $options
     * @return array
     *
     * Transform a Query object into a DQL paginated sentence
     */
    public function paginate(Query $query, PaginatorOptions $options = null)
    {
        /** ============ Initial sets ============ */

        if(!$options){
            $options = new PaginatorOptions();
        }

        // Query
        $this->query = $query;

        // Entity Manager
        $this->em = $query->getEntityManager();

        // Initial Dql
        $dql = $this->query->getDQL();

        // Request Filters
        $filters = $options->getFilters();

        // Request Search
        $search = $options->getSearch();


        /** ============ DQL Extractions ============ */

        // Look for rootEntity, rootAlias and left joins analyzing the query
        $this->configureRootSettings($dql);

        // Extracts the mandatory order by to add it later
        $this->extractMandatoryOrderBy($dql);

        // Extracts the mandatory group by to add it later
        $this->extractMandatoryGroupBy($dql);

        // Extracts the mandatory where by to add it later
        $this->extractMandatoryWhere($dql);


        /** ============ DQL Generation ============ */

        // Generate filter dql sentence
        $filter_sentence = $this->generateFilterOrSearchSentence($filters);

        // Generate search dql sentence
        $search_sentence = $this->generateFilterOrSearchSentence($search, true);

        // Generate order by dql sentence
        if(!$this->mandatoryOrderByDql){
            $order_by = $options->getOrderBy();
            if($order_by == null){
                $order_by = implode(", ", $this->rootMetadata->getIdentifierFieldNames());
            }
            $order_by_sentence = $this->generateOrderBySentence($order_by, $options->getOrder());
        }


        /** ============ DQL Addition ============ */

        // Adds association left joins after the FROM sentence
        $dql .= $this->associationJoinsDql;

        // Adds mandatory Where
        if($this->mandatoryWhereDql){
            $dql .= ' WHERE (' . $this->mandatoryWhereDql . ')';
        }

        // Adds filters
        if($filters){
            if($this->mandatoryWhereDql){
                // WHERE sentence it's currently added
                $dql .= ' AND ';
            }else{
                // No previously WHERE sentence
                $dql .= ' WHERE ';
            }
            // Adds the filter sentence
            $dql .= $filter_sentence;
        }

        // Adds search
        if($search){
            if($this->mandatoryWhereDql){
                // WHERE sentence it's currently added
                $dql .= ' AND ';
            }else{
                if($filters){
                    // WHERE sentence it's currently added
                    $dql .= ' AND ';
                }else{
                    // No previously WHERE sentence
                    $dql .= ' WHERE ';
                }
            }
            // Adds the search sentence
            $dql .= $search_sentence;
        }

        // Adds the group by sentence
        $dql .= $this->generateGroupBySentence();

        // Adds mandatory or requested order by
        if(!$this->mandatoryOrderByDql && isset($order_by_sentence)){
            $dql .= $order_by_sentence;
        }else{
            $dql .= $this->mandatoryOrderByDql;
        }

        // Make dql more pretty (removing multiple spaces)
        $this->makePrettyDql($dql);

        /** ============ Pagination Configs ============ */

        // Update query dql
        $query->setDQL($dql);

        // Get total entities
        $count = count($query->getResult());

        // Create final paginated query
        $paginated_query = $this->em->createQuery($dql);
        $paginated_query->setParameters($query->getParameters());

        // Pagination config
        $per_page = $options->getPerPage();
        if($per_page){
            $pages = ceil($count / $per_page);
            $paginated_query->setMaxResults($per_page);
        }else{
            $pages = 1;
        }

        // Offset config
        $page = $options->getPage();
        $offset = $page * $per_page - $per_page;
        $paginated_query->setFirstResult($offset);

        $data = $paginated_query->getResult();

        return ["total_pages" => $pages, "current_page" => $page,
            "per_page" => $per_page, "count" => $count, "data" => $data];
    }

    /**
     * generateGroupBySentence
     * Generates the DQl sentence for the group by.
     */
    private function generateGroupBySentence()
    {
        // Adds possible mandatory group by
        if(!$this->mandatoryGroupByDql){
            $group_by_sentence = ' GROUP BY ';
        }else{
            $group_by_sentence = $this->mandatoryGroupByDql;
        }

        // Search mandatory group by sentences
        preg_match_all('/' . $this->rootAlias . '\.[^,\s]+/', $this->mandatoryGroupByDql, $group_by_matches);

        // We need ensure group by entity indexes to evade left join redundance problems
        for($i = 0; $i < count($this->rootIdentifiers); $i++){
            // Include group by indexes if there are not included yet
            if(!$group_by_matches || (!in_array($this->rootAlias . '.' . $this->rootIdentifiers[$i], $group_by_matches[0]))) {
                if($this->mandatoryGroupByDql || ($i > 0)) {
                    $group_by_sentence = ', ';
                }
                $group_by_sentence .= ' ' . $this->rootAlias . '.' . $this->rootIdentifiers[$i];
            }
        }

        return $group_by_sentence;
    }

    /**
     * generateOrderBySentence
     * @param $orderBy
     * @param $order
     * @return string
     *
     * Generates the DQl filter sentence for the order by filters.
     */
    private function generateOrderBySentence($orderBy, $order)
    {
        $order_by_sentence = ' ORDER BY ';
        $order_by_sentence .= $this->transformOrderByToDql($this->rootEntity, $this->rootAlias, $orderBy);

        // Available descendent values
        $descendent_values = ['D', 'DESC', 'DESCENT', 'DESCEND', 'DESCENDENT', 'DESCENDING', 'DOWN', 'L', 'LOWER', '-1'];

        if(in_array(strtoupper($order), $descendent_values)){
            $order_by_sentence .= ' DESC';
        }else{
            $order_by_sentence .= ' ASC';
        }

        return $order_by_sentence;
    }

    /**
     * generateFilterOrSearchSentence
     * @param $filters
     * @param bool $isSearch
     * @return string
     *
     * Generates the DQl filter sentence for each filter or search.
     */
    private function generateFilterOrSearchSentence($filters, $isSearch = false)
    {
        if(!$filters){
            return '';
        }

        $filter_sentence = '(';

        $filter_count = 0;

        foreach ($filters as $key => $value){
            $filter_sentence .= $this->transformFilterOrSearchToDql($this->rootEntity, $this->rootAlias, $key, $value);
            if($filter_count < count($filters) -1){
                if($isSearch){
                    // Searchs are inclusive
                    $filter_sentence .= ' OR ';
                }else{
                    // Filters are exclusive
                    $filter_sentence .= ' AND ';
                }
            }
            $filter_count++;
        }

        return $filter_sentence . ')';
    }

    /**
     * makePrettyDql
     * @param $dql
     * Makes DQL more pretty removing multiple spaces
     */
    private function makePrettyDql(&$dql)
    {
        $dql = preg_replace('/\(\s/', '(', $dql);
        $dql = preg_replace('/\s\)/', ')', $dql);
        $dql = preg_replace('!\s+!', ' ', $dql);
    }

    /**
     * transformOrderByToDql
     * @param $entity
     * @param $alias
     * @param $key
     * @return mixed|string
     *
     * Converts an order by filter to its dql expresion
     */
    private function transformOrderByToDql($entity, $alias, $key)
    {
        $entity_metadata = $this->em->getClassMetadata($entity);

        $is_association_filter = preg_match('/\./', $key);

        if(!$is_association_filter){
            // Filter is looking for a field

            if(!$entity_metadata->hasField($key)){
                throw new HttpException(400, 'Wrong order by: ' . $key . ' is not a valid field');
            }else{
                return $alias . '.' . $key;
            }
        }else{
            // Filter is looking for an association

            $split = preg_split('/\./', $key);
            $association = $split[0];

            if(!$entity_metadata->hasAssociation($association)){
                throw new HttpException(400, 'Wrong search: ' . $association . ' is not a valid association');
            }else{
                // Reconstruct the rest of the key
                unset($split[0]);
                $rest = implode(".", $split);

                $association_class = $entity_metadata->getAssociationMapping($association)['targetEntity'];
                $association_type = $entity_metadata->getAssociationMapping($association)["type"];

                // Class reference for alias
                $reference = 'cls_ref' . $this->classRef;
                $this->classRef++;

                if($association_type == ClassMetadataInfo::ONE_TO_ONE || $association_type == ClassMetadataInfo::MANY_TO_ONE){
                    // Order By needs to join with the association tables
                    if(!in_array($association_class, $this->associationClasses)){
                        // LEFT JOIN is not currently added
                        $this->associationJoinsDql .= ' LEFT JOIN ' . $alias . '.' . $association . ' ' . $reference;
                        $this->associationClasses[] = $association_class;
                    }

                    return $this->transformOrderByToDql($association_class, $reference, $rest);
                }else{
                    // Only One-to-one and Many-to-one associations are allowed to do an Order by
                    throw new HttpException(400, 'Wrong order by: ' . $association
                        . ' is not a valid One-to-one or Many-to-One field');
                }
            }
        }
    }



    /**
     * transformFilterToDql
     * @param $entity
     * @param $alias
     * @param $key
     * @param $value
     * @return string
     *
     * Converts a filter or search to its dql expresion
     */
    private function transformFilterOrSearchToDql($entity, $alias, $key, $value)
    {
        $entity_metadata = $this->em->getClassMetadata($entity);

        $is_association_filter = preg_match('/\./', $key);

        if(!$is_association_filter){
            // Filter is looking for a field

            if(!$entity_metadata->hasField($key)){
                throw new HttpException(400, 'Wrong search: ' . $key . ' is not a valid field');
            }else{

                // Param reference for alias
                $reference = 'prm_ref' . $this->paramRef;
                $this->paramRef++;

                if($entity_metadata->getTypeOfField($key) == 'boolean'){
                    // Mapping boolean values
                    if(in_array($value, $this->booleanFalseValues)){
                        // True mapped value
                        $value = '0';
                    }else if(in_array($value, $this->booleanTrueValues)){
                        // False mapped value
                        $value = '1';
                    }else{
                        // Invalid mapped value, the value is ignored
                        $value = '-1';
                    }

                    $this->query->setParameter($reference, $value);
                    return $alias . '.' . $key . ' = :' . $reference;
                }else{
                    // Any other type of field
                    $this->query->setParameter($reference, '%' . $value . '%');
                    return $alias . '.' . $key . ' LIKE :' . $reference;
                }
            }
        }else{
            // Filter is looking for an association

            $split = preg_split('/\./', $key);
            $association = $split[0];

            if(!$entity_metadata->hasAssociation($association)){
                throw new HttpException(400, 'Wrong search: ' . $association . ' is not a valid association');
            }else{
                // Reconstruct the rest of the key
                unset($split[0]);
                $rest = implode(".", $split);

                $association_class = $entity_metadata->getAssociationMapping($association)['targetEntity'];
                $association_type = $entity_metadata->getAssociationMapping($association)["type"];

                // Class reference for alias
                $reference = 'cls_ref' . $this->classRef;
                $this->classRef++;

                if($association_type == ClassMetadataInfo::ONE_TO_ONE || $association_type == ClassMetadataInfo::MANY_TO_ONE){
                    // First group of associations needs to subquery the association entities
                    return $alias . '.' . $association . ' IN (SELECT ' . $reference . ' FROM ' . $association_class
                        . ' ' . $reference . ' WHERE '
                        . $this->transformFilterOrSearchToDql($association_class, $reference, $rest, $value) . ')';
                }else{
                    // Second group of associations needs to join with the association tables
                    if(!in_array($association_class, $this->associationClasses)){
                        // LEFT JOIN is not currently added
                        $this->associationJoinsDql .= ' LEFT JOIN ' . $alias . '.' . $association . ' ' . $reference;
                        $this->associationClasses[] = $association_class;
                    }

                    return $this->transformFilterOrSearchToDql($association_class, $reference, $rest, $value);
                }
            }
        }
    }

    /**
     * configureRootSettings
     * @param $dql
     * @throws \Exception
     *
     * Ensure a correctly DQL and obtain the root entity and the root alias
     */
    private function configureRootSettings($dql)
    {
        // Matchs the first FROM sentence
        preg_match('/FROM\s+\S+\s+\S+\s?/i', $dql, $from_matches);
        if(count($from_matches) < 1){
            throw new \Exception('You should define a FROM sentence with the following structure: FROM [RootEntity] [RootAlias]');
        }else {
            // Split the FROM sentence in words
            $substr = $from_matches[0];

            $substr = preg_replace('!\s+!', ' ', $substr);

            // First word is "FROM", second word is the entity, third word is the alias
            $words = preg_split('/\s/', $substr);

            $this->rootEntity = $words[1];
            $alias = $words[2];

            // We look for invalid aliases, which usually means that an alias is not included
            $invalid_aliases = ['WHERE', 'JOIN', 'INNER', 'RIGHT', 'LEFT', 'GROUP', 'INDEX'];
            if(in_array(strtoupper($alias), $invalid_aliases)){
                throw new \Exception('Invalid alias ' . $alias . ' you can not use the following alias: '
                    . implode(", ", $invalid_aliases));
            }
            $this->rootAlias = $alias;
        }

        // Initialice mandatory left joins
        $this->rootMetadata = $this->em->getClassMetadata($this->rootEntity);

        preg_match_all('/LEFT JOIN\s+' . $this->rootAlias . '\.\S+/i', $dql, $left_join_matches);
        if(isset($left_join_matches[0])){
            foreach ($left_join_matches[0] as $left_join_match){
                $association = str_ireplace(['LEFT JOIN', $this->rootAlias . '.', ' '], '', $left_join_match);
                if($this->rootMetadata->hasAssociation($association)){
                    $this->associationClasses[] = $this->rootMetadata->getAssociationMapping($association)['targetEntity'];
                }
            }
        }

        $this->rootIdentifiers = $this->rootMetadata->getIdentifierFieldNames();
    }

    /**
     * extractMandatoryOrderBy
     * @param $dql
     *
     * Extracts the mandatory order by of the DQL
     */
    private function extractMandatoryOrderBy(&$dql)
    {
        $order_by_pos = strripos($dql, ' ORDER BY ');

        if($order_by_pos !== false){
            $order_by_str = substr($dql, $order_by_pos);
            if(substr_count($order_by_str, ')')/2 == 0){
                // Saves the sentence
                $this->mandatoryOrderByDql = $order_by_str;
                // Extracts the sentence of the DQL
                $dql = substr($dql, 0, $order_by_pos);
            }
        }
    }


    /**
     * extractMandatoryGroupBy
     * @param $dql
     *
     * Extracts the mandatory group by of the DQL
     */
    private function extractMandatoryGroupBy(&$dql)
    {
        $group_by_pos = strripos($dql, ' GROUP BY ');

        if($group_by_pos !== false){
            $group_by_str = substr($dql, $group_by_pos);
            if(substr_count($group_by_str, ')')/2 == 0){
                // Saves the sentence
                $this->mandatoryGroupByDql = $group_by_str;
                // Extracts the sentence of the DQL
                $dql = substr($dql, 0, $group_by_pos);
            }
        }
    }

    /**
     * extractMandatoryWhere
     * @param $dql
     *
     * Extracts the mandatory where of the DQL
     */
    private function extractMandatoryWhere(&$dql)
    {
        $str = 'WHERE';
        $where_pos = stripos($dql, $str);

        if($where_pos !== false){
            // Saves the sentence (ignoring WHERE keyword)
            $this->mandatoryWhereDql = substr($dql, $where_pos + strlen($str));
            // Extracts the full where sentence of the DQL
            $dql = substr($dql, 0, $where_pos);
        }
    }
}