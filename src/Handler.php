<?php declare(strict_types=1);

/*
  Copyright (c) 2023, Manticore Software LTD (https://manticoresearch.com)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License version 2 or any later
  version. You should have received a copy of the GPL license along with this
  program; if you did not, you can find it at http://www.gnu.org/
*/
namespace Manticoresearch\Buddy\Plugin\LockTables;

use Manticoresearch\Buddy\Core\ManticoreSearch\Client as HTTPClient;
use Manticoresearch\Buddy\Core\Plugin\BaseHandler;
use Manticoresearch\Buddy\Core\Task\Task;
use Manticoresearch\Buddy\Core\Task\TaskResult;
use RuntimeException;
use parallel\Runtime;

final class Handler extends BaseHandler {
  /** @var HTTPClient $manticoreClient */
	protected HTTPClient $manticoreClient;

	/**
	 * Initialize the executor
	 *
	 * @param Payload $payload
	 * @return void
	 */
	public function __construct(public Payload $payload) {
	}

  /**
	 * Process the request
	 *
	 * @return Task
	 * @throws RuntimeException
	 */
	public function run(Runtime $runtime): Task {
		$this->manticoreClient->setPath($this->payload->path);

		$taskFn = static function (Payload $payload, HTTPClient $manticoreClient): TaskResult {
			$tables = $manticoreClient->validateTables(
				array_column($payload->tables, 'name'),
				['rt', 'percolate']
			);

			// Prepare queries to freeze and send in one packet
			$queries = [];
			foreach ($tables as $table) {
				$queries[] = "FREEZE $table";
			}
			$manticoreClient->sendRequest(implode(';', $queries));

			return new TaskResult(
				[[
					'total' => sizeof($tables),
					'error' => '',
					'warning' => '',
				],
				]
			);
		};

		return Task::createInRuntime(
			$runtime, $taskFn, [$this->payload, $this->manticoreClient]
		)->run();
	}

	/**
	 * @return array<string>
	 */
	public function getProps(): array {
		return ['manticoreClient'];
	}

	/**
	 * Instantiating the http client to execute requests to Manticore server
	 *
	 * @param HTTPClient $client
	 * $return HTTPClient
	 */
	public function setManticoreClient(HTTPClient $client): HTTPClient {
		$this->manticoreClient = $client;
		return $this->manticoreClient;
	}
}
