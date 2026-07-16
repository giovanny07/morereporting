<?php

namespace Modules\MoreReporting\Tests\Includes\Reports;

use Modules\MoreReporting\Includes\Reports\ItemPercentilesReport;
use PHPUnit\Framework\TestCase;

class ItemPercentilesReportTest extends TestCase {

	private ItemPercentilesReport $report;

	protected function setUp(): void {
		$this->report = new ItemPercentilesReport();
	}

	public function testComputeReturnsExpectedPercentilesForKnownDataset(): void {
		$data = [
			'items' => [
				['itemid' => '1', 'name' => 'Response time', 'hosts' => [['name' => 'Host A']]]
			],
			'history' => [
				'1' => [1.0, 2.0, 3.0, 4.0, 5.0, 6.0, 7.0, 8.0, 9.0, 10.0]
			]
		];

		$rows = $this->report->compute($data);

		self::assertCount(1, $rows);

		$row = $rows[0];

		self::assertSame('1', $row['itemid']);
		self::assertSame('Host A', $row['host']);
		self::assertSame('Response time', $row['name']);
		self::assertSame(10, $row['count']);
		self::assertSame(1.0, $row['min']);
		self::assertSame(10.0, $row['max']);
		self::assertSame(5.5, $row['avg']);
		self::assertSame(5.0, $row['p50']);
		self::assertSame(9.0, $row['p90']);
		self::assertSame(10.0, $row['p95']);
		self::assertSame(10.0, $row['p99']);
	}

	public function testComputeSkipsItemsWithoutHistory(): void {
		$data = [
			'items' => [
				['itemid' => '1', 'name' => 'No data item', 'hosts' => [['name' => 'Host A']]]
			],
			'history' => []
		];

		self::assertSame([], $this->report->compute($data));
	}

	public function testComputeSortsRowsByP95Descending(): void {
		$data = [
			'items' => [
				['itemid' => '1', 'name' => 'Low', 'hosts' => [['name' => 'Host A']]],
				['itemid' => '2', 'name' => 'High', 'hosts' => [['name' => 'Host A']]]
			],
			'history' => [
				'1' => [1.0, 2.0, 3.0],
				'2' => [10.0, 20.0, 30.0]
			]
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
