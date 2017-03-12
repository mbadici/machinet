<?php

class data_tasks extends PHPUnit_Framework_TestCase
{
    function data_prio()
    {
        return array(
            array(0, null),
            array(1, 2),
            array(2, 2),
            array(3, 2),
            array(4, 2),
            array(5, 1),
            array(6, 0),
            array(7, 0),
            array(8, 0),
            array(9, 0),
            // invalid input
            array(10, null),
        );
    }

    function data_importance()
    {
        return array(
            array(0, 9),
            array(1, 5),
            array(2, 1),
            // invalid input
            array(null,  null),
            array(5, null),
        );
    }

    /**
     * Test for kolab_sync_data_tasks::prio_to_importance()
     * @dataProvider data_prio()
     */
    function test_prio_to_importance($input, $output)
    {
        $data   = new kolab_sync_data_tasks_test;
        $result = $data->prio_to_importance($input);

        $this->assertEquals($output, $result);
    }

    /**
     * Test for kolab_sync_data_tasks::importance_to_prio()
     * @dataProvider data_importance()
     */
    function test_importance_to_prio($input, $output)
    {
        $data   = new kolab_sync_data_tasks_test;
        $result = $data->importance_to_prio($input);

        $this->assertEquals($output, $result);
    }
}

/**
 * kolab_sync_data_tasks wrapper, so we can test preotected methods too
 */
class kolab_sync_data_tasks_test extends kolab_sync_data_tasks
{
    function __construct()
    {
    }

    public function prio_to_importance($value)
    {
        return parent::prio_to_importance($value);
    }

    public function importance_to_prio($value)
    {
        return parent::importance_to_prio($value);
    }
}
