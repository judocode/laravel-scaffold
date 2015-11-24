<?php

abstract class TestCase extends PHPUnit_Framework_TestCase
{
	public function __call($method, $args)
	{
		$result = call_user_func_array([PHPUnit_Framework_Assert::class,$method], $args);
		return $result;
	}

	public function tearDown()
	{
		\Mockery::close();
	}
}