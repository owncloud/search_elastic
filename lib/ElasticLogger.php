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

	public function emergency($message, array $context = []) {
		$this->logger->emergency($message, ['extraFields' => $context]);
	}

	public function alert($message, array $context = []) {
		$this->logger->alert($message, ['extraFields' => $context]);
	}

	public function critical($message, array $context = []) {
		$this->logger->critical($message, ['extraFields' => $context]);
	}

	public function error($message, array $context = []) {
		$this->logger->error($message, ['extraFields' => $context]);
	}

	public function warning($message, array $context = []) {
		$this->logger->warning($message, ['extraFields' => $context]);
	}

	public function notice($message, array $context = []) {
		$this->logger->notice($message, ['extraFields' => $context]);
	}

	public function info($message, array $context = []) {
		$this->logger->info($message, ['extraFields' => $context]);
	}

	public function debug($message, array $context = []) {
		$this->logger->debug($message, ['extraFields' => $context]);
	}

	public function log($level, $message, array $context = []) {
		$this->logger->log($level, $message, ['extraFields' => $context]);
	}
}
