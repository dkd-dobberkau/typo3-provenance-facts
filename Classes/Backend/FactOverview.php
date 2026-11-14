<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceFacts\Backend;

use CmsProvenance\ProvenanceFacts\Event\ModifyFactOverviewRowEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * The facts of a set of pages for the "Facts" overview: live facts of live
 * default-language content elements, hidden ones included and marked.
 * Sorted like the page tree (in the order of the given pages), then by the
 * element's and the fact's position.
 */
final readonly class FactOverview
{
    private const TABLE = 'tx_provenancefacts_fact';

    public function __construct(
        private ConnectionPool $connectionPool,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * @param list<int> $pageIds in the order the overview lists them
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, pages: int}
     */
    public function find(array $pageIds, FactFilter $filter, int $page, int $perPage = 50): array
    {
        if ($pageIds === []) {
            return ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
        }

        $queryBuilder = $this->baseQuery($pageIds);
        $queryBuilder->select(
            'f.uid', 'f.fact_id', 'f.quote', 'f.subject', 'f.predicate', 'f.object_literal', 'f.object_uri',
            'f.source_url', 'f.confidence', 'f.valid_from', 'f.valid_until', 'f.hidden AS fact_hidden', 'f.sorting AS fact_sorting',
            'c.uid AS content_uid', 'c.hidden AS content_hidden', 'c.sorting AS content_sorting',
            'p.uid AS page_uid', 'p.title AS page_title', 'p.hidden AS page_hidden'
        );
        $this->applyFilter($queryBuilder, $filter);
        $records = $queryBuilder->executeQuery()->fetchAllAssociative();

        // Page order comes from the page tree, which SQL does not know.
        $position = array_flip($pageIds);
        usort($records, static fn(array $a, array $b): int => [$position[$a['page_uid']], $a['content_sorting'], $a['fact_sorting']]
            <=> [$position[$b['page_uid']], $b['content_sorting'], $b['fact_sorting']]);

        $total = count($records);
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $rows = array_map($this->row(...), array_slice($records, ($page - 1) * $perPage, $perPage));

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /**
     * @param list<int> $pageIds
     * @return list<string>
     */
    public function predicates(array $pageIds): array
    {
        if ($pageIds === []) {
            return [];
        }
        $queryBuilder = $this->baseQuery($pageIds);
        $predicates = $queryBuilder->select('f.predicate')->distinct()->executeQuery()->fetchFirstColumn();
        $predicates = array_values(array_filter(array_map(strval(...), $predicates), static fn(string $p): bool => $p !== ''));
        sort($predicates, SORT_STRING | SORT_FLAG_CASE);

        return $predicates;
    }

    /**
     * @param list<int> $pageIds
     */
    private function baseQuery(array $pageIds): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        // Joins: deleted, workspace and language conditions are set explicitly below.
        $queryBuilder->getRestrictions()->removeAll();
        $expr = $queryBuilder->expr();
        $zero = $queryBuilder->createNamedParameter(0, Connection::PARAM_INT);

        return $queryBuilder
            ->from(self::TABLE, 'f')
            ->join('f', 'tt_content', 'c', $expr->eq('c.uid', $queryBuilder->quoteIdentifier('f.content_uid')))
            ->join('c', 'pages', 'p', $expr->eq('p.uid', $queryBuilder->quoteIdentifier('c.pid')))
            ->where(
                $expr->in('p.uid', $queryBuilder->createNamedParameter($pageIds, Connection::PARAM_INT_ARRAY)),
                $expr->eq('f.deleted', $zero),
                $expr->eq('c.deleted', $zero),
                $expr->eq('p.deleted', $zero),
                $expr->eq('f.t3ver_wsid', $zero),
                $expr->eq('c.t3ver_wsid', $zero),
                $expr->eq('c.sys_language_uid', $zero)
            );
    }

    private function applyFilter(QueryBuilder $queryBuilder, FactFilter $filter): void
    {
        $expr = $queryBuilder->expr();
        $search = trim($filter->search);
        if ($search !== '') {
            // like() escapes with a backslash and ignores case on every supported database.
            $pattern = $queryBuilder->createNamedParameter('%' . $queryBuilder->escapeLikeWildcards($search) . '%');
            $queryBuilder->andWhere($expr->or(...array_map(
                static fn(string $field): string => $expr->like($field, $pattern),
                ['f.fact_id', 'f.quote', 'f.subject', 'f.object_literal', 'f.object_uri', 'f.source_url']
            )));
        }
        if ($filter->predicate !== '') {
            $queryBuilder->andWhere($expr->eq('f.predicate', $queryBuilder->createNamedParameter($filter->predicate)));
        }
        if ($filter->confidence !== '') {
            $queryBuilder->andWhere($expr->eq('f.confidence', $queryBuilder->createNamedParameter($filter->confidence)));
        }
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function row(array $record): array
    {
        $row = [
            'uid' => (int)$record['uid'],
            'factId' => (string)$record['fact_id'],
            'quote' => (string)$record['quote'],
            'subject' => (string)$record['subject'],
            'predicate' => (string)$record['predicate'],
            'objectLiteral' => (string)$record['object_literal'],
            'objectUri' => (string)$record['object_uri'],
            'sourceUrl' => (string)$record['source_url'],
            'confidence' => (string)$record['confidence'],
            'validFrom' => (int)$record['valid_from'] ?: null,
            'validUntil' => (int)$record['valid_until'] ?: null,
            'factHidden' => (bool)$record['fact_hidden'],
            'contentUid' => (int)$record['content_uid'],
            'contentHidden' => (bool)$record['content_hidden'],
            'pageUid' => (int)$record['page_uid'],
            'pageTitle' => (string)$record['page_title'],
            'pageHidden' => (bool)$record['page_hidden'],
            'extra' => [],
        ];

        return $this->eventDispatcher->dispatch(new ModifyFactOverviewRowEvent($row))->getRow();
    }
}
