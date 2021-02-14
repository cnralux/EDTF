<?php

declare( strict_types = 1 );

namespace EDTF;

class HumanizationResult {

	public static function newNonHumanized(): self {
		return new self( false );
	}

	public static function newSimpleHumanization( string $humanization ): self {
		$instance = new self( true );
		$instance->simpleHumanization = $humanization;
		return $instance;
	}

	/**
	 * @param string[] $humanization
	 */
	public static function newStructuredHumanization( array $humanization ): self {
		$instance = new self( true );
		$instance->structuredHumanization = $humanization;
		return $instance;
	}

	private bool $wasHumanized;
	private ?string $simpleHumanization = null;
	private ?string $contextMessage = null;
	private array $structuredHumanization = [];

	private function __construct( bool $wasHumanized ) {
		$this->wasHumanized = $wasHumanized;
	}

	public function wasHumanized(): bool {
		return $this->wasHumanized;
	}

	public function isOneMessage(): bool {
		return $this->contextMessage === null;
	}

	public function getContextMessage(): string {
		return $this->contextMessage ?? '';
	}

	public function getSimpleHumanization(): string {
		return $this->simpleHumanization ?? '';
	}

	/**
	 * @return string[]
	 */
	public function getStructuredHumanization(): array {
		return $this->structuredHumanization;
	}

}
