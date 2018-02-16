<?php
App::uses('ConnectionManager', 'Model');
App::uses('ShellDispatcher', 'Console');
App::uses('TableMaintenanceShell', 'TableMaintenance.Console/Command');

/**
 * TestTableMaintenanceShell - Class to overwrite protected properties and methods
 * with public ones.
 */
class TestTableMaintenanceShell extends TableMaintenanceShell {

	public function getDataSource($dataSource = 'default') {
		return parent::getDataSource($dataSource);
	}

	public function getAllTableNames($db) {
		return parent::getAllTableNames($db);
	}
}

/**
 * Class TableMaintenanceShellTest
 */
class TableMaintenanceShellTest extends CakeTestCase {

	/**
	 * Fixtures
	 *
	 * @var array
	 */
	public $fixtures = [];

	/**
	 * resultSuccess
	 *
	 * @var mixed
	 */
	public $resultSuccess = [
		[
			[
				'Table' => 'vagrant.blocks',
				'Op' => 'check',
				'Msg_type' => 'status',
				'Msg_text' => 'OK'
			]
		]
	];

	/**
	 * resultSuccessWithInfo
	 *
	 * @var mixed
	 */
	public $resultSuccessWithInfo = [
		[
			[
				'Table' => 'vagrant.shelltest',
				'Op' => 'optimize',
				'Msg_type' => 'note',
				'Msg_text' => 'Table does not support optimize, doing recreate + analyze instead',
			]
		],
		[
			[
				'Table' => 'vagrant.shelltest',
				'Op' => 'optimize',
				'Msg_type' => 'status',
				'Msg_text' => 'OK',
			]
		]
	];

	/**
	 * resultWithError
	 *
	 * @var mixed
	 */
	public $resultWithError = [
		[
			[
				'Table' => 'vagrant.categories',
				'Op' => 'repair',
				'Msg_type' => 'info',
				'Msg_text' => 'Wrong bytesec: 1- 0- 0 at 0; Skipped'
			]
		],
		[
			[
				'Table' => 'vagrant.categories',
				'Op' => 'repair',
				'Msg_type' => 'warning',
				'Msg_text' => 'Number of rows changed from 32 to 0'
			]
		],
		[
			[
				'Table' => 'vagrant.categories',
				'Op' => 'repair',
				'Msg_type' => 'status',
				'Msg_text' => 'OK'
			]
		]
	];

	/**
	 * setUp
	 *
	 * @return void
	 */
	public function setUp() {
		parent::setUp();
		$this->Shell = $this->getMockBuilder('TestTableMaintenanceShell')
			->setMethods(['out', 'getDataSource', 'getAllTableNames'])
			->getMock();
	}

	/**
	 * tearDown method
	 *
	 * @return void
	 */
	public function tearDown() {
		parent::tearDown();
		unset($this->Dispatch, $this->Shell);
	}

	/**
	 * Confirm the expected output is displayed for any action when a table
	 * does not exist.
	 *
	 * @dataProvider provideTestRunInvalidTable
	 * @return void
	 */
	public function testRunInvalidTable($action, $table, $out) {
		$this->Shell->args = [$action, $table];

		$db = $this->getMockBuilder('ConnectionManager')
			->disableOriginalConstructor()
			->setMethods(['getDataSource', 'readTableParameters'])
			->getMock();
		$db->expects($this->once())
			->method('readTableParameters')
			->with($this->identicalTo($table))
			->will($this->returnValue([]));

		$this->Shell->expects($this->once())
			->method('getDataSource')
			->will($this->returnValue($db));
		$this->Shell->expects($this->once())
			->method('out')
			->with($this->identicalTo($out));

		$this->Shell->run();
	}

	/**
	 * provideTestRunInvalidTable
	 *
	 * @return void
	 */
	public function provideTestRunInvalidTable() {
		return [
			[
				'check',
				'foo',
				'<error>Error for `CHECK` on `foo`: Table does not exist</error>',
			],
			[
				'analyze',
				'foo',
				'<error>Error for `ANALYZE` on `foo`: Table does not exist</error>',
			],
			[
				'optimize',
				'foo',
				'<error>Error for `OPTIMIZE` on `foo`: Table does not exist</error>',
			],
			[
				'repair',
				'foo',
				'<error>Error for `REPAIR` on `foo`: Table does not exist</error>',
			],
		];
	}

	/**
	 * Confirm the expected success output is displayed for any action.
	 *
	 * @dataProvider provideTestRunSingleTableSuccess
	 * @return void
	 */
	public function testRunSingleTableSuccess($action, $table, $queryLock, $queryAction, $out) {
		$this->Shell->args = [$action, $table];

		$db = $this->getMockBuilder('ConnectionManager')
			->disableOriginalConstructor()
			->setMethods(['getDataSource', 'readTableParameters', 'query'])
			->getMock();
		$db->expects($this->once())
			->method('readTableParameters')
			->with($this->identicalTo($table))
			->will($this->returnValue([
				'charset' => 'utf8',
				'collate' => 'utf8_unicode_ci',
				'engine' => 'InnoDB',
			]));

		$db->expects($this->at(1))
			->method('query')
			->with($this->identicalTo($queryLock));
		$db->expects($this->at(2))
			->method('query')
			->with($this->identicalTo($queryAction))
			->will($this->returnValue($this->resultSuccess));
		$db->expects($this->at(3))
			->method('query')
			->with($this->identicalTo('UNLOCK TABLES;'));

		$this->Shell->expects($this->once())
			->method('getDataSource')
			->will($this->returnValue($db));
		$this->Shell->expects($this->once())
			->method('out')
			->with($this->identicalTo($out));

		$this->Shell->run();
	}

	/**
	 * provideTestRunSingleTableSuccess
	 *
	 * @return void
	 */
	public function provideTestRunSingleTableSuccess() {
		return [
			[
				'check',
				'foo',
				'LOCK TABLES `foo` READ;',
				'CHECK TABLE `foo`;',
				'Success for `CHECK` on `foo`',
			],
			[
				'analyze',
				'foo',
				'LOCK TABLES `foo` WRITE;',
				'ANALYZE TABLE `foo`;',
				'Success for `ANALYZE` on `foo`',
			],
			[
				'optimize',
				'foo',
				'LOCK TABLES `foo` WRITE;',
				'OPTIMIZE TABLE `foo`;',
				'Success for `OPTIMIZE` on `foo`',
			],
			[
				'repair',
				'foo',
				'LOCK TABLES `foo` WRITE;',
				'REPAIR TABLE `foo`;',
				'Success for `REPAIR` on `foo`',
			],
		];
	}

	/**
	 * Confirm the expected error output is dispayed if any part of the response
	 * contains error or warning keys.
	 *
	 * @return void
	 */
	public function testRunSingleTableWithErrors() {
		$action = 'repair';
		$table = 'foo';
		$queryLock = "LOCK TABLES `$table` WRITE;";
		$queryAction = "REPAIR TABLE `$table`;";
		$out = '<error>Error message(s) for `REPAIR` on `foo`: {"info":"Wrong bytesec: 1- 0- 0 at 0; Skipped","warning":"Number of rows changed from 32 to 0","status":"OK"}</error>';

		$this->Shell->args = [$action, $table];

		$db = $this->getMockBuilder('ConnectionManager')
			->disableOriginalConstructor()
			->setMethods(['getDataSource', 'readTableParameters', 'query'])
			->getMock();
		$db->expects($this->once())
			->method('readTableParameters')
			->with($this->identicalTo($table))
			->will($this->returnValue([
				'charset' => 'utf8',
				'collate' => 'utf8_unicode_ci',
				'engine' => 'InnoDB',
			]));

		$db->expects($this->at(1))
			->method('query')
			->with($this->identicalTo($queryLock));
		$db->expects($this->at(2))
			->method('query')
			->with($this->identicalTo($queryAction))
			->will($this->returnValue($this->resultWithError));
		$db->expects($this->at(3))
			->method('query')
			->with($this->identicalTo('UNLOCK TABLES;'));

		$this->Shell->expects($this->once())
			->method('getDataSource')
			->will($this->returnValue($db));
		$this->Shell->expects($this->once())
			->method('out')
			->with($this->identicalTo($out));

		$this->Shell->run();
	}

	/**
	 * Confirm the expected info output is dispayed if any part of the response
	 * contains multiple non-error keys.
	 *
	 * @return void
	 */
	public function testRunSingleTableWithInfo() {
		$action = 'optimize';
		$table = 'foo';
		$queryLock = "LOCK TABLES `$table` WRITE;";
		$queryAction = "OPTIMIZE TABLE `$table`;";
		$out = '<info>Success for `OPTIMIZE` on `foo`: {"note":"Table does not support optimize, doing recreate + analyze instead","status":"OK"}</info>';

		$this->Shell->args = [$action, $table];

		$db = $this->getMockBuilder('ConnectionManager')
			->disableOriginalConstructor()
			->setMethods(['getDataSource', 'readTableParameters', 'query'])
			->getMock();
		$db->expects($this->once())
			->method('readTableParameters')
			->with($this->identicalTo($table))
			->will($this->returnValue([
				'charset' => 'utf8',
				'collate' => 'utf8_unicode_ci',
				'engine' => 'InnoDB',
			]));

		$db->expects($this->at(1))
			->method('query')
			->with($this->identicalTo($queryLock));
		$db->expects($this->at(2))
			->method('query')
			->with($this->identicalTo($queryAction))
			->will($this->returnValue($this->resultSuccessWithInfo));
		$db->expects($this->at(3))
			->method('query')
			->with($this->identicalTo('UNLOCK TABLES;'));

		$this->Shell->expects($this->once())
			->method('getDataSource')
			->will($this->returnValue($db));
		$this->Shell->expects($this->once())
			->method('out')
			->with($this->identicalTo($out));

		$this->Shell->run();
	}

	/**
	 * Confirm the expected info output is dispayed when the ALL action is used
	 * to run commands on all tables.
	 *
	 * @return void
	 */
	public function testRunAll() {
		$action = 'check';
		$table = 'ALL';

		$this->Shell->args = [$action, $table];

		$db = $this->getMockBuilder('ConnectionManager')
			->disableOriginalConstructor()
			->setMethods(['getDataSource', 'readTableParameters', 'query'])
			->getMock();
		$db->expects($this->at(0))
			->method('readTableParameters')
			->with($this->identicalTo('foo'))
			->will($this->returnValue([
				'charset' => 'utf8',
				'collate' => 'utf8_unicode_ci',
				'engine' => 'InnoDB',
			]));
		$db->expects($this->at(4))
			->method('readTableParameters')
			->with($this->identicalTo('bar'))
			->will($this->returnValue([
				'charset' => 'utf8',
				'collate' => 'utf8_unicode_ci',
				'engine' => 'InnoDB',
			]));

		$db->expects($this->at(1))
			->method('query')
			->with($this->identicalTo('LOCK TABLES `foo` READ;'));
		$db->expects($this->at(2))
			->method('query')
			->with($this->identicalTo('CHECK TABLE `foo`;'))
			->will($this->returnValue($this->resultSuccess));
		$db->expects($this->at(3))
			->method('query')
			->with($this->identicalTo('UNLOCK TABLES;'));
		$db->expects($this->at(5))
			->method('query')
			->with($this->identicalTo('LOCK TABLES `bar` READ;'));
		$db->expects($this->at(6))
			->method('query')
			->with($this->identicalTo('CHECK TABLE `bar`;'))
			->will($this->returnValue($this->resultSuccess));
		$db->expects($this->at(7))
			->method('query')
			->with($this->identicalTo('UNLOCK TABLES;'));

		$this->Shell->expects($this->once())
			->method('getAllTableNames')
			->with($db)
			->will($this->returnValue(['foo', 'bar']));
		$this->Shell->expects($this->once())
			->method('getDataSource')
			->will($this->returnValue($db));
		$this->Shell->expects($this->at(2))
			->method('out')
			->with($this->identicalTo('Success for `CHECK` on `foo`'));
		$this->Shell->expects($this->at(3))
			->method('out')
			->with($this->identicalTo('Success for `CHECK` on `bar`'));

		$this->Shell->run();
	}

	/**
	 * Confirm an instance of Mysql can be returned.
	 *
	 * @return void
	 */
	public function testGetDataSource() {
		$shell = new TestTableMaintenanceShell;
		$result = $shell->getDataSource();

		$this->assertInstanceOf('Mysql', $result);
	}

	/**
	 * Confirm the method can return data in the expected format.
	 *
	 * @return void
	 */
	public function testGetAllTableNames() {
		$data = [
			[
				'TABLE_NAMES' => [
					'Tables_in_vagrant' => 'user_types',
					'Table_type' => 'BASE TABLE'
				]
			],
			[
				'TABLE_NAMES' => [
					'Tables_in_vagrant' => 'users',
					'Table_type' => 'BASE TABLE'
				]
			],
			[
				'TABLE_NAMES' => [
					'Tables_in_vagrant' => 'users_revisions',
					'Table_type' => 'BASE TABLE'
				]
			]
		];

		$expected = [
			'user_types',
			'users',
			'users_revisions',
		];

		$db = $this->getMockBuilder('ConnectionManager')
			->disableOriginalConstructor()
			->setMethods(['query'])
			->getMock();

		$db->expects($this->once())
			 ->method('query')
			 ->with("SHOW FULL TABLES WHERE Table_Type = 'BASE TABLE'")
			 ->will($this->returnValue($data));

		$shell = new TestTableMaintenanceShell;
		$result = $shell->getAllTableNames($db);

		$this->assertSame($expected, $result);
	}
}
