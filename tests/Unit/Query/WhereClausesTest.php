<?php

namespace Coretik\Tests\Unit\Query;

use Coretik\Core\Query\Adapters\WPCommentAdapter;
use Coretik\Core\Query\Adapters\WPPostAdapter;
use Coretik\Core\Query\Adapters\WPTermAdapter;
use Coretik\Core\Query\Adapters\WPUserAdapter;
use Coretik\Core\Query\Clauses\DateClause;
use Coretik\Core\Query\Clauses\MetaClause;
use Coretik\Core\Query\Clauses\WhereClause;
use Coretik\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class WhereClausesTest extends TestCase
{
    public static function adapters(): array
    {
        // A query parameter supported by each adapter
        return [
            'post' => [WPPostAdapter::class, 'post_parent'],
            'term' => [WPTermAdapter::class, 'parent'],
            'user' => [WPUserAdapter::class, 'role'],
            'comment' => [WPCommentAdapter::class, 'post_id'],
        ];
    }

    #[DataProvider('adapters')]
    public function testWhereSetsQueryParameter(string $adapter, string $parameter): void
    {
        $builder = (new $adapter())->where(new WhereClause($parameter, 5));

        $this->assertSame(5, $builder->get($parameter));
    }

    #[DataProvider('adapters')]
    public function testWhereWithUnknownParameterThrows(string $adapter): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('use whereMeta()');
        (new $adapter())->where(new WhereClause('color', 'blue'));
    }

    #[DataProvider('adapters')]
    public function testWhereWithOperatorThrows(string $adapter, string $parameter): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new $adapter())->where(new WhereClause($parameter, 5, '>'));
    }

    #[DataProvider('adapters')]
    public function testOrWhereWithParameterThrows(string $adapter, string $parameter): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new $adapter())->orWhere(new WhereClause($parameter, 5));
    }

    public function testDateClauseThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new WPPostAdapter())->where(new DateClause('post_date', ['year' => 2024]));
    }

    #[DataProvider('adapters')]
    public function testMetaClauseIsApplied(string $adapter): void
    {
        $builder = (new $adapter())->where(new MetaClause('color', 'blue'));

        $this->assertSame('blue', $builder->get('meta_query')[0]['value']);
    }
}
