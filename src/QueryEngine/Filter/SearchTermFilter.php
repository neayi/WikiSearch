<?php

namespace WikiSearch\QueryEngine\Filter;

use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\FullText\MatchPhraseQuery;
use ONGR\ElasticsearchDSL\Query\FullText\QueryStringQuery;
use WikiSearch\SMW\PropertyFieldMapper;

class SearchTermFilter extends AbstractFilter {
	/**
	 * @var PropertyFieldMapper[]
	 */
	private array $chainedFields = [];

	/**
	 * @var string[]|PropertyFieldMapper[]
	 */
	private array $fields = [
		"subject.title.search^8",
		"subject.title^8",
		"subject.title.folded^8",
		"text_copy.search^5",
		"text_copy^5",
		"text_copy.folded^5",
		"text_raw.search",
		"text_raw",
		"attachment.title^3",
		"attachment.content"
	];

	/**
	 * @param string $searchTerm The search term to filter on
	 * @param (string|PropertyFieldMapper)[] $properties
	 * @param string $defaultOperator The default operator to use
	 */
	public function __construct(
        private string $searchTerm,
        ?array         $properties = null,
        private string $defaultOperator = "or",
        bool           $includeDefaultSearchTermProperties = false,
    ) {
        if ( $properties === null ) {
            return;
        }

        if ( !$includeDefaultSearchTermProperties ) {
            $this->fields = [];
        }

        foreach ( $properties as $field ) {
            if ( is_string( $field ) ) {
                $field = new PropertyFieldMapper( $field );
            }

            if ( $field->isChained() ) {
                $this->chainedFields[] = $field;
            } else {
                $this->fields[] = $field->getWeightedPropertyField();
                if ( $field->hasSearchSubfield() ) {
                    $this->fields[] = $field->getWeightedSearchField();
                }
                if ( $field->hasFoldedSubfield() ) {
                    $this->fields[] = $field->getWeightedFoldedField();
                }
            }
        }
	}

	/**
	 * @inheritDoc
	 */
	public function filterToQuery(): BoolQuery {
		$boolQuery = new BoolQuery();

		foreach ($this->chainedFields as $property ) {
			// Construct a new chained sub query for each chained property and add it to the bool query
			$filter = new ChainedPropertyFilter( new PropertyTextFilter( $property, $this->searchTerm, $this->defaultOperator ) );
			$boolQuery->add( $filter->toQuery(), BoolQuery::SHOULD );
		}

		if ( $this->fields !== [] ) {
			$queryStringQuery = new QueryStringQuery( $this->searchTerm );
			$queryStringQuery->setParameters( [
				"fields" => $this->fields,
				"default_operator" => $this->defaultOperator,
				"analyze_wildcard" => true,
				"tie_breaker" => 1,
				"lenient" => true
			] );

			$boolQuery->add( $queryStringQuery, BoolQuery::SHOULD );
		}

		// Wildcard queries produce constant scores in ElasticSearch. Add a high-boost phrase match
		// on the title so that exact title matches rank first. The folded subfield ensures that
		// accent-insensitive searches (e.g. "paturage" vs "pâturage") also benefit from the boost.
		if ( preg_match( '/^(\*[^*\s]+\*\s*)+$/', trim( $this->searchTerm ) ) ) {
			$plainTerm = trim( str_replace( '*', '', $this->searchTerm ) );
			$boolQuery->add( new MatchPhraseQuery( 'subject.title', $plainTerm, [ 'boost' => 100 ] ), BoolQuery::SHOULD );
			$boolQuery->add( new MatchPhraseQuery( 'subject.title.folded', $plainTerm, [ 'boost' => 100 ] ), BoolQuery::SHOULD );
		}

		return $boolQuery;
	}
}
