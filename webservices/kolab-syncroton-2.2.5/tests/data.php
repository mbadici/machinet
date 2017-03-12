<?php

class data extends PHPUnit_Framework_TestCase
{
    /**
     * Test for kolab_sync_data::recurrence_to_kolab()
     */
    function test_recurrence_to_kolab()
    {
        $xml = '<!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
        <Sync xmlns="uri:AirSync" xmlns:AirSyncBase="uri:AirSyncBase" xmlns:Calendar="uri:Calendar">
        <ApplicationData>
                <Calendar:Recurrence>
                    <Calendar:Type>0</Calendar:Type>
                    <Calendar:Interval>1</Calendar:Interval>
                    <Calendar:Until>20101128T225959Z</Calendar:Until>
                </Calendar:Recurrence>
            </ApplicationData>
            </Sync>';

        $xml   = new SimpleXMLElement($xml);
        $event = new Syncroton_Model_Event($xml->ApplicationData);
        $data  = new kolab_sync_data_test;

        $result = $data->recurrence_to_kolab($event);

        $this->assertEquals('DAILY', $result['FREQ']);
        $this->assertEquals(1, $result['INTERVAL']);
        $this->assertEquals('20101128T225959Z', $result['UNTIL']->format("Ymd\THis\Z"));

        $xml = '<!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
            <Sync xmlns="uri:AirSync" xmlns:AirSyncBase="uri:AirSyncBase" xmlns:Calendar="uri:Calendar">
            <ApplicationData>
                <Calendar:Recurrence>
                    <Calendar:Type>1</Calendar:Type>
                    <Calendar:Interval>1</Calendar:Interval>
                    <Calendar:DayOfWeek>8</Calendar:DayOfWeek>
                </Calendar:Recurrence>
            </ApplicationData>
            </Sync>';

        $xml   = new SimpleXMLElement($xml);
        $event = new Syncroton_Model_Event($xml->ApplicationData);

        $result = $data->recurrence_to_kolab($event, null);

        $this->assertEquals('WEEKLY', $result['FREQ']);
        $this->assertEquals(1, $result['INTERVAL']);
        $this->assertEquals('WE', $result['BYDAY']);
    }
}

/**
 * kolab_sync_data wrapper, so we can test preotected methods too
 */
class kolab_sync_data_test extends kolab_sync_data
{
    function __construct()
    {
    }

    public function recurrence_to_kolab($data)
    {
        return parent::recurrence_to_kolab($data, null);
    }

    function toKolab(Syncroton_Model_IEntry $data, $folderId, $entry = null, $timezone = null)
    {
    }

    function getEntry(Syncroton_Model_SyncCollection $collection, $serverId, $as_array = false)
    {
    }
}
