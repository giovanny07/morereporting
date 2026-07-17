<?php

namespace Modules\MoreReporting\Tests\Includes;

use Modules\MoreReporting\Includes\ReportComparison;
use PHPUnit\Framework\TestCase;

class ReportComparisonTest extends TestCase {

	public function testPairMatchesRowsByKey(): void {
		$current = [
			['itemid' => '1', 'value' => 10],
			['itemid' => '2', 'value' => 20]
		];
		$previous = [
			['itemid' => '2', 'value' => 15],
			['itemid' => '1', 'value' => 5]
		];

		$pairs = ReportComparison::pair($current, $previous, 'itemid');

		self::assertSame(['current' => $current[0], 'previous' => $previous[1]], $pairs[0]);
		self::assertSame(['current' => $current[1], 'previous' => $previous[0]], $pairs[1]);
	}

	public function testPairReturnsNullPreviousWhenEntityIsNew(): void {
		$current = [['itemid' => '1', 'value' => 10]];
		$previous = [];

		$pairs = ReportComparison::pair($current, $previous, 'itemid');

		self::assertNull($pairs[0]['previous']);
	}

	public function testPairPreservesCurrentRowOrder(): void {
		$current = [
			['itemid' => '3', 'value' => 1],
			['itemid' => '1', 'value' => 2],
			['itemid' => '2', 'value' => 3]
		];

		$pairs = ReportComparison::pair($current, [], 'itemid');

		self::assertSame(['3', '1', '2'], array_column(array_column($pairs, 'current'), 'itemid'));
	}

	public function testPreviousPeriodIsImmediatelyPrecedingWithEqualLength(): void {
		$period = ReportComparison::previousPeriod(1000, 1700);

		self::assertSame(300, $period['from']);
		self::assertSame(1000, $period['to']);
		self::assertSame(700, $period['to'] - $period['from']);
	}
}
