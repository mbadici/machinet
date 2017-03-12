<?php

class message extends PHPUnit_Framework_TestCase
{
    function setUp()
    {
    }


    /**
     * Test message parsing and headers setting
     */
    function test_headers()
    {
        $source  = file_get_contents(TESTS_DIR . '/src/mail.plain');
        $message = new kolab_sync_message($source);
        $headers = $message->headers();

        $this->assertArrayHasKey('MIME-Version', $headers);
        $this->assertCount(8, $headers);
        $this->assertEquals('kolab@domain.tld', $headers['To']);

        // test set_header()
        $message->set_header('to', 'test@domain.tld');
        $headers = $message->headers();

        $this->assertCount(8, $headers);
        $this->assertEquals('test@domain.tld', $headers['To']);
    }

    /**
     * Test message parsing
     */
    function test_source()
    {
        $source  = file_get_contents(TESTS_DIR . '/src/mail.plain');
        $message = new kolab_sync_message($source);
        $result  = $message->source();

        $this->assertEquals($source, str_replace("\r\n", "\n", $result));
    }

    /**
     * Test adding attachments to the message
     */
    function test_attachment()
    {
        $source = file_get_contents(TESTS_DIR . '/src/mail.plain');
        $mixed  = file_get_contents(TESTS_DIR . '/src/mail.plain.mixed');
        $mixed2 = file_get_contents(TESTS_DIR . '/src/mail.mixed');

        // test adding attachment to text/plain message
        $message = new kolab_sync_message($source);
        $message->add_attachment('aaa', array(
            'content_type' => 'text/plain',
            'encoding'     => '8bit',
        ));

        $result = $message->source();
        $result = str_replace("\r\n", "\n", $result);
        if (preg_match('/boundary="([^"]+)"/', $result, $m)) {
            $mixed = str_replace('BOUNDARY', $m[1], $mixed);
        }

        $this->assertEquals($mixed, $result);

        // test adding attachment to multipart/mixed message
        $message = new kolab_sync_message($mixed);
        $message->add_attachment('aaa', array(
            'content_type' => 'text/plain',
            'encoding'     => 'base64',
        ));

        $result = $message->source();
        $result = str_replace("\r\n", "\n", $result);
        if (preg_match('/boundary="([^"]+)"/', $result, $m)) {
            $mixed2 = str_replace('BOUNDARY', $m[1], $mixed2);
        }

        $this->assertEquals($mixed2, $result);
    }

    /**
     * Test appending a text to the message
     */
    function test_append()
    {
        // test appending text to text/plain message
        $source = file_get_contents(TESTS_DIR . '/src/mail.plain');
        $append = file_get_contents(TESTS_DIR . '/src/mail.plain.append');

        $message = new kolab_sync_message($source);
        $message->append('a');

        $result  = $message->source();
        $result  = str_replace("\r\n", "\n", $result);
        $this->assertEquals($append, $result);
    }

    /**
     * Test recoding the message
     */
    function test_recode_message_1()
    {
        $source = file_get_contents(TESTS_DIR . '/src/mail.recode1');
        $result = file_get_contents(TESTS_DIR . '/src/mail.recode1.out');

        $message = kolab_sync_message::recode_message($source);

        $this->assertEquals($result, $message);
    }

    /**
     * Test recoding the message
     */
    function test_recode_message_2()
    {
        $source = file_get_contents(TESTS_DIR . '/src/mail.recode2');
        $result = file_get_contents(TESTS_DIR . '/src/mail.recode2.out');

        $message = kolab_sync_message::recode_message($source);

        $this->assertEquals($result, $message);
    }

    /**
     * Test recoding the message
     */
    function test_recode_message_3()
    {
        $source = file_get_contents(TESTS_DIR . '/src/mail.recode3');
        $result = file_get_contents(TESTS_DIR . '/src/mail.recode3.out');

        $message = kolab_sync_message::recode_message($source);

        $this->assertEquals($result, $message);
    }

    /**
     * Test recoding the message
     */
    function test_recode_message_4()
    {
        $source = file_get_contents(TESTS_DIR . '/src/mail.recode4');
        $result = file_get_contents(TESTS_DIR . '/src/mail.recode4.out');

        $message = kolab_sync_message::recode_message($source);

        $this->assertEquals($result, $message);
    }
}
