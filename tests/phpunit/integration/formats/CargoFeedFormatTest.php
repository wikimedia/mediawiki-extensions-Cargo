<?php

use MediaWiki\Request\FauxRequest;

/**
 * @file
 */

/**
 * Tests for the feed format.
 *
 * @group Database
 */
class CargoFeedFormatTest extends MediaWikiIntegrationTestCase {

	public function setUp(): void {
		// Set useless article path, for easier URL testing.
		$this->setMwGlobals( [
			'wgServer' => 'https://wiki.example.org',
			'wgArticlePath' => 'cargofeedtest/$1',
		] );
		MWTimestamp::setFakeTime( 1675991594 );
	}

	/**
	 * @covers CargoFeedFormat::queryAndDisplay
	 */
	public function testQueryAndDisplay(): void {
		$format = new CargoFeedFormat( $this->createMock( OutputPage::class ) );
		// Default.
		$this->assertStringContainsString( 'View Atom feed', $format->queryAndDisplay( [], [] ) );
		// User-supplied (case-insensitive).
		$this->assertStringContainsString( 'View RSS feed', $format->queryAndDisplay( [], [ 'feed type' => 'RSS' ] ) );
		$this->assertStringContainsString( 'View Atom feed', $format->queryAndDisplay( [], [ 'feed type' => 'Atom' ] ) );
		$this->assertStringContainsString( 'View Atom feed', $format->queryAndDisplay( [], [ 'feed type' => 'invalid-type' ] ) );
		$this->assertStringContainsString( 'View Atom feed', $format->queryAndDisplay( [], [ 'feed type' => null ] ) );
	}

	/**
	 * @covers CargoFeedFormat::outputFeed
	 * @dataProvider provideOutputFeed
	 */
	public function testOutputFeed( $expected, $queryResults, $requestParams ): void {
		$format = new CargoFeedFormat( $this->createMock( OutputPage::class ) );
		$sqlQuery = $this->createMock( CargoSQLQuery::class );
		$sqlQuery->expects( $this->once() )->method( 'run' )->willReturn( $queryResults );
		$sqlQuery->expects( $this->once() )->method( 'getMainStartAndEndDateFields' )->willReturn( [ 'date_published', null ] );
		// Workaround for T268295.
		$sqlQuery->mOrderBy = [];
		$this->expectOutputString( $expected );
		foreach ( $queryResults as $res ) {
			$this->insertPage( $res['_pageName'], 'Lorem ipsum' );
		}
		$request = new FauxRequest( $requestParams );
		$request->setRequestURL( '/test-request-url' );
		$format->outputFeed( $request, [ $sqlQuery ] );
	}

	public static function provideOutputFeed() {
		return [
			'no items' => [
				'expected' => '<?xml version="1.0"?>
<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<channel>
		<title>News feed</title>
		<link>https://wiki.example.org/test-request-url</link>
		<description>Desc.</description>
		<language>en</language>
		<generator>MediaWiki ' . MW_VERSION . '</generator>
		<lastBuildDate>Fri, 10 Feb 2023 01:13:14 GMT</lastBuildDate>
		<item>
			<title>Lorem ipsum</title>
			<link>cargofeedtest/Lorem_ipsum</link>
			<guid>cargofeedtest/Lorem_ipsum</guid>
			<description>&lt;div class=&quot;mw-content-ltr mw-parser-output&quot; lang=&quot;en&quot; dir=&quot;ltr&quot;&gt;&lt;p&gt;Lorem ipsum
&lt;/p&gt;&lt;/div&gt;</description>
			<pubDate>Thu, 02 Jan 2020 03:04:05 GMT</pubDate>
			<dc:creator></dc:creator>
			' . '
		</item>
		<item>
			<title>Sic amet</title>
			<link>cargofeedtest/Sic_amet</link>
			<guid>cargofeedtest/Sic_amet</guid>
			<description>&lt;div class=&quot;mw-content-ltr mw-parser-output&quot; lang=&quot;en&quot; dir=&quot;ltr&quot;&gt;&lt;p&gt;Lorem ipsum
&lt;/p&gt;&lt;/div&gt;</description>
			<pubDate>Sat, 02 Jan 2021 03:04:05 GMT</pubDate>
			<dc:creator>Bob</dc:creator>
			' . '
		</item>
</channel></rss>',
				'results' => [
					[
						'_pageName' => 'Lorem ipsum',
						'date_published' => '2020-01-02 03:04:05',
					],
					[
						'_pageName' => 'Sic amet',
						'date_published' => '2021-01-02 03:04:05',
						'author' => 'Bob',
					],
				],
				[
					'feed_description' => 'Desc.',
					'feed_type' => 'rss',
				]
			]
		];
	}
}
