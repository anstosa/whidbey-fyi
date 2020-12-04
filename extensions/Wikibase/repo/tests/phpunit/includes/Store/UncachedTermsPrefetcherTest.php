<?php

namespace Wikibase\Repo\Tests\Store;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Wikibase\DataAccess\PrefetchingTermLookup;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Term\TermTypes;
use Wikibase\Lib\Store\RedirectResolvingLatestRevisionLookup;
use Wikibase\Lib\Store\TermCacheKeyBuilder;
use Wikibase\Lib\Store\UncachedTermsPrefetcher;
use Wikibase\Lib\Tests\FakeCache;

/**
 * @covers \Wikibase\Lib\Store\UncachedTermsPrefetcher
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class UncachedTermsPrefetcherTest extends TestCase {

	use TermCacheKeyBuilder;

	const TEST_REVISION = 666;
	const TEST_LANGUAGE = 'en';

	/** @var PrefetchingTermLookup|MockObject */
	private $prefetchingLookup;

	/** @var RedirectResolvingLatestRevisionLookup|MockObject */
	private $redirectResolvingRevisionLookup;

	/** @var int|null */
	private $ttl;

	protected function setUp(): void {
		parent::setUp();

		$this->prefetchingLookup = $this->createMock( PrefetchingTermLookup::class );
		$this->redirectResolvingRevisionLookup = $this->newStubRedirectResolvingRevisionLookup();
		$this->ttl = null;
	}

	public function testGivenAllTermsAreCached_doesNotPrefetch() {
		$cache = new FakeCache();
		$cache->set( $this->buildTestCacheKey( 'Q123', TermTypes::TYPE_LABEL ), 'some label' );
		$this->prefetchingLookup->expects( $this->never() )->method( $this->anything() );

		$this->newTermsPrefetcher()
			->prefetchUncached( $cache, [ new ItemId( 'Q123' ) ], [ TermTypes::TYPE_LABEL ], [ 'en' ] );
	}

	public function testGivenNothingCached_prefetchesAndCaches() {
		$cache = new FakeCache();
		$termTypes = [ TermTypes::TYPE_LABEL, TermTypes::TYPE_DESCRIPTION ];
		$languages = [ 'en' ];
		$q123 = new ItemId( 'Q123' );
		$q123Label = 'meow';
		$q123Description = 'cat sound';
		$q321 = new ItemId( 'Q321' );
		$q321Label = 'quack';
		$q321Description = 'duck sound';

		$this->prefetchingLookup->expects( $this->once() )
			->method( 'prefetchTerms' )
			->with( [ $q123, $q321 ], $termTypes, $languages );
		$this->prefetchingLookup->expects( $this->any() )
			->method( 'getPrefetchedTerm' )
			->withConsecutive(
				[ $q123, TermTypes::TYPE_LABEL, $languages[0] ],
				[ $q123, TermTypes::TYPE_DESCRIPTION, $languages[0] ],
				[ $q321, TermTypes::TYPE_LABEL, $languages[0] ],
				[ $q321, TermTypes::TYPE_DESCRIPTION, $languages[0] ]
			)
			->willReturnOnConsecutiveCalls(
				$q123Label,
				$q123Description,
				$q321Label,
				$q321Description
			);

		$this->newTermsPrefetcher()
			->prefetchUncached( $cache, [ $q123, $q321 ], $termTypes, $languages );

		$this->assertSame(
			$q123Label,
			$cache->get( $this->buildTestCacheKey( 'Q123', TermTypes::TYPE_LABEL ) )
		);
		$this->assertSame(
			$q123Description,
			$cache->get( $this->buildTestCacheKey( 'Q123', TermTypes::TYPE_DESCRIPTION ) )
		);
		$this->assertSame(
			$q321Label,
			$cache->get( $this->buildTestCacheKey( 'Q321', TermTypes::TYPE_LABEL ) )
		);
		$this->assertSame(
			$q321Description,
			$cache->get( $this->buildTestCacheKey( 'Q321', TermTypes::TYPE_DESCRIPTION ) )
		);
	}

	public function testGivenTermsCachedForSomeEntities_looksUpOnlyUncachedOnes() {
		$cache = new FakeCache();
		$cachedItemId = new ItemId( 'Q123' );
		$uncachedItemId = new ItemId( 'Q321' );
		$languages = [ 'en' ];
		$termTypes = [ TermTypes::TYPE_LABEL ];

		$cache->set( $this->buildTestCacheKey( 'Q123', TermTypes::TYPE_LABEL ), 'whatever' );

		$this->prefetchingLookup->expects( $this->once() )
			->method( 'prefetchTerms' )
			->with( [ $uncachedItemId ], $termTypes, $languages );

		$this->newTermsPrefetcher()
			->prefetchUncached( $cache, [ $cachedItemId, $uncachedItemId ], $termTypes, $languages );
	}

	public function testGivenTTL_setsEntriesWithTTL() {
		$this->ttl = 123;

		$cache = $this->createMock( CacheInterface::class );
		$cache->expects( $this->once() )
			->method( 'setMultiple' )
			->with( $this->anything(), 123 );

		$this->newTermsPrefetcher()
			->prefetchUncached( $cache, [ new ItemId( 'Q123' ) ], [ TermTypes::TYPE_LABEL ], [ 'en' ] );
	}

	public function testGivenNoTTL_usesOneMinuteTTL() {
		$cache = $this->createMock( CacheInterface::class );
		$cache->expects( $this->once() )
			->method( 'setMultiple' )
			->with( $this->anything(), 60 );

		$this->newTermsPrefetcher()
			->prefetchUncached( $cache, [ new ItemId( 'Q123' ) ], [ TermTypes::TYPE_LABEL ], [ 'en' ] );
	}

	private function newTermsPrefetcher() {
		return new UncachedTermsPrefetcher(
			$this->prefetchingLookup,
			$this->redirectResolvingRevisionLookup,
			$this->ttl
		);
	}

	/**
	 * @return MockObject|RedirectResolvingLatestRevisionLookup
	 */
	protected function newStubRedirectResolvingRevisionLookup() {
		$revisionAndRedirectResolver = $this->createMock( RedirectResolvingLatestRevisionLookup::class );
		$revisionAndRedirectResolver->expects( $this->any() )
			->method( 'lookupLatestRevisionResolvingRedirect' )
			->willReturnCallback( function ( EntityId $id ) {
				return [ self::TEST_REVISION, $id ];
			} );

		return $revisionAndRedirectResolver;
	}

	private function buildTestCacheKey(
		string $itemId,
		string $termType,
		string $language = self::TEST_LANGUAGE,
		int $revision = self::TEST_REVISION
	) {
		return $this->buildCacheKey( new ItemId( $itemId ), $revision, $language, $termType );
	}

}
