<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceFacts\Domain\Repository;

use CmsProvenance\ProvenanceFacts\Domain\Model\Fact;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * Reads published facts: live workspace, default language, and only where
 * fact, content element and page are visible to anonymous visitors.
 */
final readonly class FactRepository
{
    private const TABLE = 'tx_provenancefacts_fact';

    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    /**
     * @return list<Fact>
     */
    public function findByContentElement(int $contentUid): array
    {
        $queryBuilder = $this->publishedFacts();
        $rows = $queryBuilder
            ->andWhere($queryBuilder->expr()->eq('c.uid', $queryBuilder->createNamedParameter($contentUid, Connection::PARAM_INT)))
            ->orderBy('f.sorting')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map($this->map(...), $rows);
    }

    /**
     * @return list<Fact> in content and fact order
     */
    public function findByPage(int $pageUid): array
    {
        $queryBuilder = $this->publishedFacts();
        $rows = $queryBuilder
            ->andWhere($queryBuilder->expr()->eq('c.pid', $queryBuilder->createNamedParameter($pageUid, Connection::PARAM_INT)))
            ->orderBy('c.colPos')
            ->addOrderBy('c.sorting')
            ->addOrderBy('f.sorting')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map($this->map(...), $rows);
    }

    /**
     * @return array{fact: Fact, pageUid: int}|null
     */
    public function findPublishedById(string $factId): ?array
    {
        $queryBuilder = $this->publishedFacts();
        $row = $queryBuilder
            ->andWhere($queryBuilder->expr()->eq('f.fact_id', $queryBuilder->createNamedParameter($factId)))
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return $row !== false ? ['fact' => $this->map($row), 'pageUid' => (int)$row['page_uid']] : null;
    }

    private function publishedFacts(): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $expr = $queryBuilder->expr();
        $zero = $queryBuilder->createNamedParameter(0, Connection::PARAM_INT);
        $now = $queryBuilder->createNamedParameter($GLOBALS['EXEC_TIME'] ?? time(), Connection::PARAM_INT);

        return $queryBuilder
            ->select('f.*', 'c.pid AS page_uid')
            ->from(self::TABLE, 'f')
            ->join('f', 'tt_content', 'c', (string)$expr->eq('c.uid', $queryBuilder->quoteIdentifier('f.content_uid')))
            ->join('c', 'pages', 'p', (string)$expr->eq('p.uid', $queryBuilder->quoteIdentifier('c.pid')))
            ->where(
                $expr->eq('f.deleted', $zero),
                $expr->eq('f.hidden', $zero),
                $expr->eq('f.t3ver_wsid', $zero),
                $expr->eq('c.deleted', $zero),
                $expr->eq('c.hidden', $zero),
                $expr->eq('c.t3ver_wsid', $zero),
                $expr->eq('c.sys_language_uid', $zero),
                $expr->in('c.fe_group', [$queryBuilder->createNamedParameter(''), $queryBuilder->createNamedParameter('0')]),
                $expr->lte('c.starttime', $now),
                $expr->or($expr->eq('c.endtime', $zero), $expr->gt('c.endtime', $now)),
                $expr->eq('p.deleted', $zero),
                $expr->eq('p.hidden', $zero),
                $expr->in('p.fe_group', [$queryBuilder->createNamedParameter(''), $queryBuilder->createNamedParameter('0')]),
                $expr->lte('p.starttime', $now),
                $expr->or($expr->eq('p.endtime', $zero), $expr->gt('p.endtime', $now))
            );
    }

    private function map(array $row): Fact
    {
        return new Fact(
            factId: (string)$row['fact_id'],
            quote: (string)$row['quote'],
            subject: (string)$row['subject'],
            predicate: (string)$row['predicate'],
            objectLiteral: $this->stringOrNull($row['object_literal']),
            objectUri: $this->stringOrNull($row['object_uri']),
            sourceUrl: $this->stringOrNull($row['source_url']),
            confidence: (string)($row['confidence'] ?: 'asserted'),
            validFrom: (int)$row['valid_from'] > 0 ? (int)$row['valid_from'] : null,
            validUntil: (int)$row['valid_until'] > 0 ? (int)$row['valid_until'] : null,
            createdAt: (int)$row['crdate'],
        );
    }

    private function stringOrNull(mixed $value): ?string
    {
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }
}
