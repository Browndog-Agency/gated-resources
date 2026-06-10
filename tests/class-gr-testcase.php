<?php
namespace BrownDog\GatedResources\Tests;

use Brain\Monkey;
use PHPUnit\Framework\TestCase;

abstract class GR_TestCase extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
