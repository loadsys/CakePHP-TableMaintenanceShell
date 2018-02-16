<?php
App::uses('AppShell', 'Console/Command');
App::uses('ConnectionManager', 'Model');
App::uses('Hash', 'Utility');

/**
 * Class: TableMaintenanceShell
 *
 * @see AppShell
 */
class TableMaintenanceShell extends AppShell {

	/**
	 * The various `status` field results MySQL provides that should be
	 * considered an error.
	 *
	 * @var array
	 */
	public $errorMsgs = [
		'Error',
		'error',
		'Warning',
		'warning',
	];

	/**
	 * getOptionParser
	 *
	 * Define command line options for automatic processing and enforcement.
	 *
	 * @codeCoverageIgnore Cake core
	 * @return object A parser object
	 */
	public function getOptionParser() {
		$parser = parent::getOptionParser();
		$parser->addSubcommand('run', [
			'help' => 'Run MySQL maintenance on one or all tables',
			'parser' => [
				'arguments' => [
					'action' => [
						'help' => 'Action to perform: check, analyze, optimize, repair',
						'required' => true,
						'choices' => ['check', 'analyze', 'optimize', 'repair'],
					],
					'table' => [
						'help' => 'Table to check or "ALL" for all tables',
						'required' => true,
					],
				],
			],
		])
		->description([
			'Use this command to check, analyze, optimize, or repair MySQL tables',
		]);

		return $parser;
	}

	/**
	 * Method to "run" various table maintenance actions and output results.
	 *
	 * @return void
	 */
	public function run() {
		$action = strtoupper($this->args[0]);
		$table = $this->args[1];
		$lockMode = $action == 'CHECK' ? 'READ' : 'WRITE';

		$db = $this->getDataSource();

		$tables = [$table];
		if ($tables[0] == 'ALL') {
			$tables = $this->getAllTableNames($db);
		}

		foreach ($tables as $table) {
			$query = [
				'lock' => 'LOCK TABLES `' . $table . '` ' . $lockMode . ';',
				'action' => $action . ' TABLE `' . $table . '`;',
				'unlock' => 'UNLOCK TABLES;',
			];

			$tableParams = $db->readTableParameters($table);

			if (empty($tableParams)) {
				$this->out(
					'<error>Error for `' . $action . '` on `' . $table . '`: Table does not exist</error>',
					1,
					Shell::QUIET
				);
				continue;
			}

			$db->query($query['lock']);
			$result = $db->query($query['action']);
			$db->query($query['unlock']);

			$msgType = Hash::extract($result, '{n}.0.Msg_type');
			$msgText = Hash::combine($result, '{n}.0.Msg_type', '{n}.0.Msg_text');

			$error = array_intersect($msgType, $this->errorMsgs);
			$error = !empty($error);

			if ($error) {
				$errorMsgs = json_encode($msgText);
				$this->out(
					'<error>Error message(s) for `' . $action . '` on `' . $table . '`: ' . $errorMsgs . '</error>',
					1,
					Shell::QUIET
				);
			} else {
				$msg = 'Success for `' . $action . '` on `' . $table . '`';
				if (count($msgText) > 1) {
					$infoMsgs = json_encode($msgText);
					$this->out("<info>{$msg}: {$infoMsgs}</info>", 1, Shell::NORMAL);
				} else {
					$this->out($msg, 1, Shell::NORMAL);
				}
			}
		}
	}

	/**
	 * Wrapper method for ConnectionManager::getDataSource()
	 *
	 * @param string $dataSource The dataSource config to use
	 * @return object The dataSource object
	 */
	protected function getDataSource($dataSource = 'default') {
		return ConnectionManager::getDataSource($dataSource);
	}

	/**
	 * Gets a list of tables only (no views) from the currently selected
	 * database.
	 *
	 * @param $db A MySQL db object created by ConnectionManager::getDataSource()
	 * @return array An array of database tables
	 */
	protected function getAllTableNames($db) {
		$tables = $db->query("SHOW FULL TABLES WHERE Table_Type = 'BASE TABLE'");
		$tables = Hash::remove($tables, '{n}.TABLE_NAMES.Table_type');

		return Hash::extract($tables, '{n}.TABLE_NAMES.{s}');
	}
}
