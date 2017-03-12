<?php

class timezone_converter extends PHPUnit_Framework_TestCase
{
    function setUp()
    {
    }


    function test_list_timezones()
    {
        $converter = timezone_converter_test::getInstance();

        $input  = 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAoAAAAEAAIAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAMAAAAFAAEAAAAAAAAAxP///w==';
        $output = $converter->getListOfTimezones($input, 'UTC');

        $this->assertTrue(is_array($output));
    }
}

class timezone_converter_test extends kolab_sync_timezone_converter
{
    // disable cache
    function getCache()
    {
        return null;
    }
}
