<?php

class body_converter extends PHPUnit_Framework_TestCase
{
    function setUp()
    {
    }


    function data_html_to_text()
    {
        return array(
            array('', ''),
            array('<div></div>', ''),
            array('<div>a</div>', 'a'),
            array('<html><head><title>title</title></head></html>', ''),
        );
    }

    /**
     * @dataProvider data_html_to_text
     */
    function test_html_to_text($html, $text)
    {
        $converter = new kolab_sync_body_converter($html, Syncroton_Model_EmailBody::TYPE_HTML);
        $output    = $converter->convert(Syncroton_Model_EmailBody::TYPE_PLAINTEXT);

        $this->assertEquals(trim($text), trim($output));
    }

    /**
     *
     */
    function test_rtf_to_text()
    {
        $rtf  = '0QAAAB0CAABMWkZ1Pzsq5D8ACQMwAQMB9wKnAgBjaBEKwHNldALRcHJx4DAgVGFoA3ECgwBQ6wNUDzcyD9MyBgAGwwKDpxIBA+MReDA0EhUgAoArApEI5jsJbzAVwzEyvjgJtBdCCjIXQRb0ORIAHxeEGOEYExjgFcMyNTX/CbQaYgoyGmEaHBaKCaUa9v8c6woUG3YdTRt/Hwwabxbt/xyPF7gePxg4JY0YVyRMKR+dJfh9CoEBMAOyMTYDMUksgSc1FGAnNhqAJ1Q3My3BNAqFfS7A';
        $rtf  = base64_decode($rtf);
        $text = 'Test';
        $html = '<pre>Test</pre>';

        $converter = new kolab_sync_body_converter($rtf, Syncroton_Model_EmailBody::TYPE_RTF);

        $output = $converter->convert(Syncroton_Model_EmailBody::TYPE_PLAINTEXT);
        $this->assertEquals(trim($text), trim($output));

        $output = $converter->convert(Syncroton_Model_EmailBody::TYPE_HTML);
        $this->assertEquals(trim($html), trim($output));
    }

}
