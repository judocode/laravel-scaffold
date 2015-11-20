<?php

abstract class TestCase extends PHPUnit_Framework_TestCase
{
	public function __call($method, $args)
	{

		#return PHPUnit_Framework_Assert::assertTrue(false);
		#$result = call_user_func_array([PHPUnit_Framework_Assert::class, $method], $args);
		$result = call_user_func_array('PHPUnit_Framework_Assert::'.$method, $args);
		return $result;
	}
}