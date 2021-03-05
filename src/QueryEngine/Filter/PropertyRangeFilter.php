<?php


namespace WSSearch\QueryEngine\Filter;

use ONGR\ElasticsearchDSL\BuilderInterface;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\RangeQuery;
use WSSearch\SMW\Property;

/**
 * Class DateRangeFilter
 *
 * Represents a date range filter to filter in between date properties values.
 *
 * @package WSSearch\QueryEngine\Filter
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/query-dsl-range-query.html
 */
class PropertyRangeFilter extends Filter {
    /**
     * @var \WSSearch\SMW\Property The property to apply the filter to
     */
    private $property;

    /**
     * @var array The options for this filter
     */
    private $options;

    /**
     * DateRangeFilter constructor.
     *
     * @param \WSSearch\SMW\Property|string $property The property to apply the filter to
     * @param array $options The options for this filter, for instance:
     *  [
     *      RangeQuery::GTE => 10,
     *      RangeQuery::LT => 20
     *  ]
     *
     *  to filter out everything that is not greater or equal to ten and less than twenty.
     * @param float $boost
     */
    public function __construct( $property, array $options, float $boost = 1.0 ) {
        if ( is_string( $property ) ) {
            $property = new Property( $property );
        }

        if ( !($property instanceof Property)) {
            throw new \InvalidArgumentException();
        }

        $this->property = $property;
        $this->options = $options;
        $this->options["boost"] = $boost;
    }

    /**
     * @inheritDoc
     */
    public function toQuery(): BoolQuery {
        $range_query = new RangeQuery(
            $this->property->getPropertyField(),
            $this->options
        );

        $bool_query = new BoolQuery();
        $bool_query->add( $range_query, BoolQuery::MUST );

        /*
         * Example of such a query:
         *
         * "bool": {
         *      "must": [
         *          {
         *              "range": {
         *                  "P:0.wpgField": {
         *                      "gte": "6 ft"
         *                  }
         *              }
         *          }
         *      ]
         *  }
         */

        return $bool_query;
    }
}