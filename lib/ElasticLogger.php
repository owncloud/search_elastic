<?php

namespace OCA\Search_Elastic;

use OCP\ILogger;
use Psr\Log\LoggerInterface;

// phan picks up logger interface from other apps so throws error here
// @phan-suppress-next-line PhanRedefinedInheritedInterface
class ElasticLogger implements LoggerInterface {
	/**
	 * @var ILogger
	 */
	private $logger;

	public function __construct(ILogger $logger) {
		$this->logger = $logger;
	}

	public function emergency(string|\Stringable $message, array $context = []): void {
		$this->logger->emergency($message, ['extraFields' => $context]);
	}

	public function alert(string|\Stringable $message, array $context = []): void {
		$this->logger->alert($message, ['extraFields' => $context]);
	}

	public function critical(string|\Stringable $message, array $context = []): void {
		$this->logger->critical($message, ['extraFields' => $context]);
	}

	public function error(string|\Stringable $message, array $context = []): void {
		$this->logger->error($message, ['extraFields' => $context]);
	}

	public function warning(string|\Stringable $message, array $context = []): void {
		$this->logger->warning($message, ['extraFields' => $context]);
	}

	public function notice(string|\Stringable $message, array $context = []): void {
		$this->logger->notice($message, ['extraFields' => $context]);
	}

	public function info(string|\Stringable $message, array $context = []): void {
		$this->logger->info($message, ['extraFields' => $context]);
	}

	public function debug(string|\Stringable $message, array $context = []): void {
		$this->logger->debug($message, ['extraFields' => $context]);
	}

	public function log($level, string|\Stringable $message, array $context = []): void {
		$this->logger->log($level, $message, ['extraFields' => $context]);
	}
}
