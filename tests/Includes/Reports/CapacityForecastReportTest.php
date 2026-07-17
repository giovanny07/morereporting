<?php

namespace Modules\MoreReporting\Tests\Includes\Reports;

use Modules\MoreReporting\Includes\Reports\CapacityForecastReport;
use PHPUnit\Framework\TestCase;

class CapacityForecastReportTest extends TestCase {

	private const SEC_PER_DAY_TEST = 86400;

	private CapacityForecastReport $report;

	protected function setUp(): void {
		$this->report = new CapacityForecastReport();

		if (!defined('SEC_PER_DAY')) {
			define('SEC_PER_DAY', self::SEC_PER_DAY_TEST);
		}
	}

	public function testComputeForecastsDaysToThresholdForADecliningTrend(): void {
		// Perfect linear fit: 100 at day 0, dropping 2/day.
		$data = [
			'items' => [
				['itemid' => '1', 'name' => 'Free disk space', 'hosts' => [['name' => 'Host A']]]
			],
			'history' => [
				'1' => [
					['value' => 100.0, 'clock' => 0],
					['value' => 98.0, 'clock' => self::SEC_PER_DAY_TEST],
					['value' => 96.0, 'clock' => 2 * self::SEC_PER_DAY_TEST]
				]
			],
			'time_from' => 0,
			'threshold' => 90.0
		];

		$rows = $this->report->compute($data);

		self::assertCount(1, $rows);

		$row = $rows[0];

		self::assertSame('1', $row['itemid']);
		self::assertSame('Host A', $row['host']);
		self::assertEqualsWithDelta(96.0, $row['current_value'], 0.0001);
		self::assertEqualsWithDelta(-2.0, $row['slope_per_day'], 0.0001);
		self::assertEqualsWithDelta(3.0, $row['days_to_threshold'], 0.0001);
	}

	public function testComputeReturnsNullForecastForAFlatTrend(): void {
		$data = [
			'items' => [
				['itemid' => '1', 'name' => 'Stable', 'hosts' => [['name' => 'Host A']]]
			],
			'history' => [
				'1' => [
					['value' => 50.0, 'clock' => 0],
					['value' => 50.0, 'clock' => self::SEC_PER_DAY_TEST]
				]
			],
			'time_from' => 0,
			'threshold' => 0.0
		];

		$rows = $this->report->compute($data);

		self::assertCount(1, $rows);
		self::assertSame(0.0, $rows[0]['slope_per_day']);
		self::assertNull($rows[0]['days_to_threshold']);
	}

	public function testComputeReturnsNullForecastWhenTrendMovesAwayFromThreshold(): void {
		// Increasing trend, threshold is below and behind where the trend started.
		$data = [
			'items' => [
				['itemid' => '1', 'name' => 'Growing', 'hosts' => [['name' => 'Host A']]]
			],
			'history' => [
				'1' => [
					['value' => 10.0, 'clock' => 0],
					['value' => 11.0, 'clock' => self::SEC_PER_DAY_TEST],
					['value' => 12.0, 'clock' => 2 * self::SEC_PER_DAY_TEST]
				]
			],
			'time_from' => 0,
			'threshold' => 0.0
		];

		$rows = $this->report->compute($data);

		self::assertNull($rows[0]['days_to_threshold']);
	}

	public function testComputeSkipsItemsWithFewerThanTwoPoints(): void {
		$data = [
			'items' => [
				['itemid' => '1', 'name' => 'Single point', 'hosts' => [['name' => 'Host A']]]
			],
			'history' => [
				'1' => [['value' => 5.0, 'clock' => 0]]
			],
			'time_from' => 0,
			'threshold' => 0.0
		];

		self::assertSame([], $this->report->compute($data));
	}

	public function testComputeSortsForecastableRowsBeforeNonForecastableOnesByDaysAscending(): void {
		$declining = [
			['value' => 100.0, 'clock' => 0],
			['value' => 90.0, 'clock' => self::SEC_PER_DAY_TEST]
		];
		$decliningFaster = [
			['value' => 100.0, 'clock' => 0],
			['value' => 50.0, 'clock' => self::SEC_PER_DAY_TEST]
		];
		$flat = [
			['value' => 50.0, 'clock' => 0],
			['value' => 50.0, 'clock' => self::SEC_PER_DAY_TEST]
		];

		$data = [
			'items' => [
				['itemid' => '1', 'name' => 'Slow decline', 'hosts' => [['name' => 'Host A']]],
				['itemid' => '2', 'name' => 'Flat', 'hosts' => [['name' => 'Host A']]],
				['itemid' => '3', 'name' => 'Fast decline', 'hosts' => [['name' => 'Host A']]]
			],
			'history' => [
				'1' => $declining,
				'2' => $flat,
				'3' => $decliningFaster
			],
			'time_from' => 0,
			'threshold' => 0.0
		];

		$rows = $this->report->compute($data);

		self::assertSame('3', $rows[0]['itemid']);
		self::assertSame('1', $rows[1]['itemid']);
		self::assertSame('2', $rows[2]['itemid']);
	}

	public function testRenderReturnsRowsForInteractiveChannel(): void {
		$rows = [['itemid' => '1']];

		self::assertSame($rows, $this->report->render($rows, 'interactive'));
	}

	public function testRenderReturnsNullForUnimplementedChannels(): void {
		self::assertNull($this->report->render([['itemid' => '1']], 'pdf'));
		self::assertNull($this->report->render([['itemid' => '1']], 'export'));
	}
}
