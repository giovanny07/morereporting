<?php

namespace Modules\MoreReporting\Tests\Includes\Reports;

use Modules\MoreReporting\Includes\Reports\AnomalyReport;
use PHPUnit\Framework\TestCase;

class AnomalyReportTest extends TestCase {

	private AnomalyReport $report;

	protected function setUp(): void {
		$this->report = new AnomalyReport();
	}

	public function testComputeFlagsValuesBeyondThreshold(): void {
		// Baseline: mean 10, stddev 0 except for a couple of points giving it some spread.
		$data = [
			'items' => [
				['itemid' => '1', 'name' => 'CPU load', 'hosts' => [['name' => 'Host A']]]
			],
			'baseline_history' => [
				'1' => [
					['value' => 8.0, 'clock' => 1000], ['value' => 9.0, 'clock' => 1001],
					['value' => 10.0, 'clock' => 1002], ['value' => 11.0, 'clock' => 1003],
					['value' => 12.0, 'clock' => 1004]
				]
			],
			'history' => [
				'1' => [
					['value' => 10.0, 'clock' => 2000],
					['value' => 100.0, 'clock' => 2001]
				]
			],
			'zscore_threshold' => 3.0
		];

		$rows = $this->report->compute($data);

		self::assertCount(1, $rows);

		$row = $rows[0];

		self::assertSame('1', $row['itemid']);
		self::assertSame('Host A', $row['host']);
		self::assertSame(10.0, $row['baseline_mean']);
		self::assertSame(2, $row['analysis_count']);
		self::assertSame(1, $row['anomalies']);
		self::assertSame(100.0, $row['worst_value']);
		self::assertSame(2001, $row['worst_clock']);
	}

	public function testComputeSkipsItemsWithInsufficientBaseline(): void {
		$data = [
			'items' => [
				['itemid' => '1', 'name' => 'Too little baseline', 'hosts' => [['name' => 'Host A']]]
			],
			'baseline_history' => [
				'1' => [['value' => 5.0, 'clock' => 1000]]
			],
			'history' => [
				'1' => [['value' => 5.0, 'clock' => 2000]]
			],
			'zscore_threshold' => 3.0
		];

		self::assertSame([], $this->report->compute($data));
	}

	public function testComputeSkipsItemsWithFlatBaseline(): void {
		$data = [
			'items' => [
				['itemid' => '1', 'name' => 'Constant', 'hosts' => [['name' => 'Host A']]]
			],
			'baseline_history' => [
				'1' => [['value' => 5.0, 'clock' => 1000], ['value' => 5.0, 'clock' => 1001]]
			],
			'history' => [
				'1' => [['value' => 5.0, 'clock' => 2000]]
			],
			'zscore_threshold' => 3.0
		];

		self::assertSame([], $this->report->compute($data));
	}

	public function testComputeSortsRowsByAnomalyCountDescending(): void {
		$flat_baseline = [
			['value' => 8.0, 'clock' => 1000], ['value' => 9.0, 'clock' => 1001],
			['value' => 10.0, 'clock' => 1002], ['value' => 11.0, 'clock' => 1003],
			['value' => 12.0, 'clock' => 1004]
		];

		$data = [
			'items' => [
				['itemid' => '1', 'name' => 'Quiet', 'hosts' => [['name' => 'Host A']]],
				['itemid' => '2', 'name' => 'Noisy', 'hosts' => [['name' => 'Host A']]]
			],
			'baseline_history' => [
				'1' => $flat_baseline,
				'2' => $flat_baseline
			],
			'history' => [
				'1' => [['value' => 10.0, 'clock' => 2000]],
				'2' => [['value' => 100.0, 'clock' => 2000], ['value' => -100.0, 'clock' => 2001]]
			],
			'zscore_threshold' => 3.0
		];

		$rows = $this->report->compute($data);

		self::assertSame('2', $rows[0]['itemid']);
		self::assertSame('1', $rows[1]['itemid']);
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
