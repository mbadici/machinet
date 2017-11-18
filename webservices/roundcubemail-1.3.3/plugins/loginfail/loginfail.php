<?php
    class loginfail extends rcube_plugin {
        public $task = 'login';

        function init() {
            $this->add_hook('login_failed', array($this, 'login_failed'));
        }

        public function login_failed($args) {
            $rcmail = rcmail::get_instance();

            $filename = $this->home . '/loginfail.html';

            if (file_exists($filename)) {
                $html = file_get_contents($filename);
                $rcmail->output->add_footer($html);
            }
        }
    }
?>
