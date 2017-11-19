<?php
/*
 +--------------------------------------------------------------------------+
 | This file is part of the Kolab File API                                  |
 |                                                                          |
 | Copyright (C) 2011-2014, Kolab Systems AG                                |
 |                                                                          |
 | This program is free software: you can redistribute it and/or modify     |
 | it under the terms of the GNU Affero General Public License as published |
 | by the Free Software Foundation, either version 3 of the License, or     |
 | (at your option) any later version.                                      |
 |                                                                          |
 | This program is distributed in the hope that it will be useful,          |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of           |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the             |
 | GNU Affero General Public License for more details.                      |
 |                                                                          |
 | You should have received a copy of the GNU Affero General Public License |
 | along with this program. If not, see <http://www.gnu.org/licenses/>      |
 +--------------------------------------------------------------------------+
 | Author: Aleksander Machniak <machniak@kolabsys.com>                      |
 | Author: Jeroen van Meeuwen <vanmeeuwen@kolabsys.com>                     |
 +--------------------------------------------------------------------------+
*/

class file_locale
{
    protected static $translation = array();
    protected $language;

    /**
     * Localization initialization.
     */
    protected function locale_init()
    {
        $language = $this->get_language();
        $LANG     = array();

        if (!$language) {
            $language = 'en_US';
        }

        $this->language = $language;

        @include __DIR__ . "/locale/en_US.php";

        if ($language != 'en_US') {
            @include __DIR__ . "/locale/$language.php";
        }

        setlocale(LC_ALL, $language . '.utf8', $language . 'UTF-8', 'en_US.utf8', 'en_US.UTF-8');

        self::$translation = $LANG;
    }

    /**
     * Returns system language (locale) setting.
     *
     * @return string Language code
     */
    protected function get_language()
    {
        $aliases = array(
            'de' => 'de_DE',
            'en' => 'en_US',
            'pl' => 'pl_PL',
        );

        // UI language
        $langs = !empty($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '';
        $langs = explode(',', $langs);

        if (!empty($_SESSION['user']) && !empty($_SESSION['user']['language'])) {
            array_unshift($langs, $_SESSION['user']['language']);
        }
        if (!empty($_SESSION['env']) && !empty($_SESSION['env']['language'])) {
            array_unshift($langs, $_SESSION['env']['language']);
        }

        foreach (array_unique($langs) as $lang) {
            $lang = explode(';', $lang);
            $lang = $lang[0];
            $lang = str_replace('-', '_', $lang);

            if (file_exists(__DIR__ . "/locale/$lang.php")) {
                return $lang;
            }

            if (isset($aliases[$lang]) && ($alias = $aliases[$lang])
                && file_exists(__DIR__ . "/locale/$alias.php")
            ) {
                return $alias;
            }
        }

        return null;
    }

    /**
     * Returns translation of defined label/message.
     *
     * @return string Translated string.
     */
    public static function translate()
    {
        $args = func_get_args();

        if (is_array($args[0])) {
            $args = $args[0];
        }

        $label = $args[0];

        if (isset(self::$translation[$label])) {
            $content = trim(self::$translation[$label]);
        }
        else {
            $content = $label;
        }

        for ($i = 1, $len = count($args); $i < $len; $i++) {
            $content = str_replace('$'.$i, $args[$i], $content);
        }

        return $content;
    }
}
