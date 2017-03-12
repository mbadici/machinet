<?php

/**
 * Tine 2.0
 *
 * @package     ActiveSync
 * @license     http://www.tine20.org/licenses/agpl-nonus.txt AGPL Version 1 (Non-US)
 *              NOTE: According to sec. 8 of the AFFERO GENERAL PUBLIC LICENSE (AGPL),
 *              Version 1, the distribution of the Tine 2.0 ActiveSync module in or to the
 *              United States of America is excluded from the scope of this license.
 * @copyright   Copyright (c) 2009 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Jonas Fischer <j.fischer@metaways.de>
 */

class kolab_sync_timezone_converter
{
    /**
     * holds the instance of the singleton
     *
     * @var kolab_sync_timezone_onverter
     */
    private static $_instance = NULL;

    protected $_startDate = array();

    /**
     * If set then the timezone guessing results will be cached.
     * This is strongly recommended for performance reasons.
     *
     * @var rcube_cache
     */
    protected $cache = null;

    /**
     * array of offsets known by ActiceSync clients, but unknown by php
     * @var array
     */
    protected $_knownTimezones = array(
        '0AIAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA==' => array(
            'Pacific/Kwajalein' => 'MHT'
        )
    );

    /**
     * don't use the constructor. Use the singleton.
     *
     * @param $_logger
     */
    private function __construct()
    {
    }

    /**
     * don't clone. Use the singleton.
     *
     */
    private function __clone()
    {
    }

    /**
     * the singleton pattern
     *
     * @return kolab_sync_timezone_converter
     */
    public static function getInstance()
    {
        if (self::$_instance === NULL) {
            self::$_instance = new kolab_sync_timezone_converter();
        }

        return self::$_instance;
    }

    /**
     * Returns an array of timezones that match to the {@param $_offsets}
     *
     * If {@see $_expectedTimezone} is set then the method will terminate as soon
     * as the expected timezone has matched and the expected timezone will be the
     * first entry to the returned array.
     *
     * @param string|array $_offsets
     *
     * @return array
     */
    public function getListOfTimezones($_offsets)
    {
        if (is_string($_offsets) && isset($this->_knownTimezones[$_offsets])) {
            $timezones = $this->_knownTimezones[$_offsets];
        }
        else {
            if (is_string($_offsets)) {
                // unpack timezone info to array
                $_offsets = $this->_unpackTimezoneInfo($_offsets);
            }

            if (!$this->_validateOffsets($_offsets)) {
                return array();
            }
            $this->_setDefaultStartDateIfEmpty($_offsets);

            $cacheId   = $this->_getCacheId('timezones', $_offsets);
            $timezones = $this->_loadFromCache($cacheId);

            if (!is_array($timezones)) {
                $timezones = array();
                foreach (DateTimeZone::listIdentifiers() as $timezoneIdentifier) {
                    $timezone = new DateTimeZone($timezoneIdentifier);
                    if (false !== ($matchingTransition = $this->_checkTimezone($timezone, $_offsets))) {
                        $timezones[$timezoneIdentifier] = $matchingTransition['abbr'];
                    }
                }
                $this->_saveInCache($timezones, $cacheId);
            }
        }

//        $this->_log(__METHOD__, __LINE__, 'Matching timezones: '.print_r($timezones, true));

//        if (empty($timezones)) {
//            throw new ActiveSync_TimezoneNotFoundException('No timezone found for the given offsets');
//        }

        return $timezones;
    }

    /**
     * Returns PHP timezone that matches to the {@param $_offsets}
     *
     * If {@see $_expectedTimezone} is set then the method will return this timezone if it matches.
     *
     * @param string|array $_offsets          Activesync timezone definition
     * @param string       $_expectedTomezone Expected timezone name
     *
     * @return string Expected timezone name
     */
    public function getTimezone($_offsets, $_expectedTimezone = null)
    {
        $timezones = $this->getListOfTimezones($_offsets);

        if ($_expectedTimezone && isset($timezones[$_expectedTimezone])) {
            return $_expectedTimezone;
        }
        else {
            return key($timezones);
        }
    }

    /**
     * Unpacks {@param $_packedTimezoneInfo} using {@see unpackTimezoneInfo} and then
     * calls {@see getTimezoneForOffsets} with the unpacked timezone info
     *
     * @param String $_packedTimezoneInfo
     * @return String [timezone abbreviation e.g. CET, MST etc.]
     *
     */
//    public function getTimezoneForPackedTimezoneInfo($_packedTimezoneInfo)
//    {
//        $offsets = $this->_unpackTimezoneInfo($_packedTimezoneInfo);
//        $matchingTimezones = $this->getTimezoneForOffsets($offsets);
//        $maxMatches = 0;
//        $matchingAbbr = null;
//        foreach ($matchingTimezones as $abbr => $timezones) {
//            if (count($timezones) > $maxMatches) {
//                $maxMatches = count($timezones);
//                $matchingAbbr = $abbr;
//            }
//        }
//        return $matchingAbbr;
//    }

    /**
     * Return packed string for given {@param $_timezone}
     * @param String               $_timezone
     * @param String | int | null  $_startDate
     * @return String
     */
    public function encodeTimezone($_timezone, $_startDate = null)
    {
        foreach ($this->_knownTimezones as $packedString => $knownTimezone) {
            if (array_key_exists($_timezone, $knownTimezone)) {
                return $packedString;
            }
        }

        $offsets = $this->getOffsetsForTimezone($_timezone, $_startDate);
        return $this->_packTimezoneInfo($offsets);
    }

    /**
     * get offsets for given timezone
     *
     * @param string $_timezone
     * @param $_startDate
     * @return array
     */
    public function getOffsetsForTimezone($_timezone, $_startDate = null)
    {
        $this->_setStartDate($_startDate);

        $cacheId = $this->_getCacheId('offsets', array($_timezone));

        if (false === ($offsets = $this->_loadFromCache($cacheId))) {
            $offsets = $this->_getOffsetsTemplate();

            try {
                $timezone = new DateTimeZone($_timezone);
            }
            catch (Exception $e) {
//                $this->_log(__METHOD__, __LINE__, ": could not instantiate timezone {$_timezone}: {$e->getMessage()}");
                return null;
            }

            list($standardTransition, $daylightTransition) = $this->_getTransitionsForTimezoneAndYear($timezone, $this->_startDate['year']);

            if ($standardTransition) {
                $offsets['bias'] = $standardTransition['offset']/60*-1;
                if ($daylightTransition) {
                    $offsets = $this->_generateOffsetsForTransition($offsets, $standardTransition, 'standard');
                    $offsets = $this->_generateOffsetsForTransition($offsets, $daylightTransition, 'daylight');
                    $offsets['standardHour'] += $daylightTransition['offset']/3600;
                    $offsets['daylightHour'] += $standardTransition['offset']/3600;

                    //@todo how do we get the standardBias (is usually 0)?
                    //$offsets['standardBias'] = ...

                    $offsets['daylightBias'] = ($daylightTransition['offset'] - $standardTransition['offset'])/60*-1;
                }
            }

            $this->_saveInCache($offsets, $cacheId);
        }

        return $offsets;
    }

    /**
     *
     *
     * @param array $_offsets
     * @param array $_transition
     * @param String $_type
     * @return array
     */
    protected function _generateOffsetsForTransition(Array $_offsets, Array $_transition, $_type)
    {
        $transitionDateParsed = getdate($_transition['ts']);

        $_offsets[$_type . 'Month']      = $transitionDateParsed['mon'];
        $_offsets[$_type . 'DayOfWeek']  = $transitionDateParsed['wday'];
        $_offsets[$_type . 'Minute']     = $transitionDateParsed['minutes'];
        $_offsets[$_type . 'Hour']       = $transitionDateParsed['hours'];

        for ($i=5; $i>0; $i--) {
            if ($this->_isNthOcurrenceOfWeekdayInMonth($_transition['ts'], $i)) {
                $_offsets[$_type . 'Day'] = $i;
                break;
            };
        }

        return $_offsets;
    }

    /**
     * Test if the weekday of the given {@param $_timestamp} is the {@param $_occurence}th occurence of this weekday within its month.
     *
     * @param int $_timestamp
     * @param int $_occurence [1 to 5, where 5 indicates the final occurrence during the month if that day of the week does not occur 5 times]
     *
     * @return bool
     */
    protected function _isNthOcurrenceOfWeekdayInMonth($_timestamp, $_occurence)
    {
        if ($_occurence <= 1) {
            return true;
        }

        $original = new DateTime('@'.$_timestamp);
        $orig     = $original->format('n');

        if ($_occurence == 5) {
            $modified = clone($original);
            $modified->modify('1 week');
            $mod = $modified->format('n');

            // modified date is a next month
            return $mod > $orig || ($mod == 1 && $orig == 12);
        }

        $modified = clone($original);
        $modified->modify(sprintf('-%d weeks', $_occurence - 1));
        $mod = $modified->format('n');

        if ($mod != $orig) {
            return false;
        }

        $modified = clone($original);
        $modified->modify(sprintf('-%d weeks', $_occurence));
        $mod = $modified->format('n');

        // modified month is earlier than original
        return $mod < $orig || ($mod == 12 && $orig == 1);
    }

    /**
     * Check if the given {@param $_standardTransition} and {@param $_daylightTransition}
     * match to the object property {@see $_offsets}
     *
     * @param Array $standardTransition
     * @param Array $daylightTransition
     *
     * @return bool
     */
    protected function _checkTransition($_standardTransition, $_daylightTransition, $_offsets)
    {
        if (empty($_standardTransition) || empty($_offsets)) {
            return false;
        }

        $standardOffset = ($_offsets['bias'] + $_offsets['standardBias']) * 60 * -1;

        //check each condition in a single if statement and break the chain when one condition is not met - for performance reasons
        if ($standardOffset == $_standardTransition['offset'] ) {

            if (empty($_offsets['daylightMonth']) && (empty($_daylightTransition) || empty($_daylightTransition['isdst']))) {
                //No DST
                return true;
            }

            $daylightOffset = ($_offsets['bias'] + $_offsets['daylightBias']) * 60 * -1;

            // the milestone is sending a positive value for daylightBias while it should send a negative value
            $daylightOffsetMilestone = ($_offsets['bias'] + ($_offsets['daylightBias'] * -1) ) * 60 * -1;

            if ($daylightOffset == $_daylightTransition['offset'] || $daylightOffsetMilestone == $_daylightTransition['offset']) {
                $standardParsed = getdate($_standardTransition['ts']);
                $daylightParsed = getdate($_daylightTransition['ts']);

                if ($standardParsed['mon'] == $_offsets['standardMonth'] && 
                    $daylightParsed['mon'] == $_offsets['daylightMonth'] &&
                    $standardParsed['wday'] == $_offsets['standardDayOfWeek'] &&
                    $daylightParsed['wday'] == $_offsets['daylightDayOfWeek']
                ) {
                    return $this->_isNthOcurrenceOfWeekdayInMonth($_daylightTransition['ts'], $_offsets['daylightDay']) &&
                           $this->_isNthOcurrenceOfWeekdayInMonth($_standardTransition['ts'], $_offsets['standardDay']);
                }
            }
        }

        return false;
    }

    /**
     * decode timezone info from activesync
     *
     * @param string $_packedTimezoneInfo the packed timezone info
     * @return array
     */
    protected function _unpackTimezoneInfo($_packedTimezoneInfo)
    {
        $timezoneUnpackString = 'lbias/a64standardName/vstandardYear/vstandardMonth/vstandardDayOfWeek/vstandardDay/vstandardHour/vstandardMinute/vstandardSecond/vstandardMilliseconds/lstandardBias/a64daylightName/vdaylightYear/vdaylightMonth/vdaylightDayOfWeek/vdaylightDay/vdaylightHour/vdaylightMinute/vdaylightSecond/vdaylightMilliseconds/ldaylightBias';

        $timezoneInfo = unpack($timezoneUnpackString, base64_decode($_packedTimezoneInfo));

        return $timezoneInfo;
    }

    /**
     * encode timezone info to activesync
     *
     * @param array $_timezoneInfo
     * @return string
     */
    protected function _packTimezoneInfo($_timezoneInfo)
    {
        if (!is_array($_timezoneInfo)) {
            return null;
        }

        $packed = pack(
            "la64vvvvvvvvla64vvvvvvvvl",
            $_timezoneInfo['bias'],
            $_timezoneInfo['standardName'],
            $_timezoneInfo['standardYear'],
            $_timezoneInfo['standardMonth'],
            $_timezoneInfo['standardDayOfWeek'],
            $_timezoneInfo['standardDay'],
            $_timezoneInfo['standardHour'],
            $_timezoneInfo['standardMinute'],
            $_timezoneInfo['standardSecond'],
            $_timezoneInfo['standardMilliseconds'],
            $_timezoneInfo['standardBias'],
            $_timezoneInfo['daylightName'],
            $_timezoneInfo['daylightYear'],
            $_timezoneInfo['daylightMonth'],
            $_timezoneInfo['daylightDayOfWeek'],
            $_timezoneInfo['daylightDay'],
            $_timezoneInfo['daylightHour'],
            $_timezoneInfo['daylightMinute'],
            $_timezoneInfo['daylightSecond'],
            $_timezoneInfo['daylightMilliseconds'],
            $_timezoneInfo['daylightBias']
        );

        return base64_encode($packed);
    }

    /**
     * Returns complete offsets array with all fields empty
     *
     * Used e.g. when reverse-generating ActiveSync Timezone Offset Information
     * based on a given Timezone, {@see getOffsetsForTimezone}
     *
     * @return unknown_type
     */
    protected function _getOffsetsTemplate()
    {
        return array(
            'bias'              => 0,
            'standardName'      => '',
            'standardYear'      => 0,
            'standardMonth'     => 0,
            'standardDayOfWeek' => 0,
            'standardDay'       => 0,
            'standardHour'      => 0,
            'standardMinute'    => 0,
            'standardSecond'    => 0,
            'standardMilliseconds' => 0,
            'standardBias'      => 0,
            'daylightName'      => '',
            'daylightYear'      => 0,
            'daylightMonth'     => 0,
            'daylightDayOfWeek' => 0,
            'daylightDay'       => 0,
            'daylightHour'      => 0,
            'daylightMinute'    => 0,
            'daylightSecond'    => 0,
            'daylightMilliseconds' => 0,
            'daylightBias'      => 0
        );
    }

    /**
     * Validate and set offsets
     *
     * @param array $value
     *
     * @return bool Validation result
     */
    protected function _validateOffsets($value)
    {
        // validate $value
        if ((!empty($value['standardMonth']) || !empty($value['standardDay']) || !empty($value['daylightMonth']) || !empty($value['daylightDay'])) &&
            (empty($value['standardMonth']) || empty($value['standardDay']) || empty($value['daylightMonth']) || empty($value['daylightDay']))
        ) {
            // It is not possible not set standard offsets without setting daylight offsets and vice versa
            return false;
        }

        return true;
    }

    /**
     * Parse and set object property {@see $_startDate}
     *
     * @param String | int      $_startDate
     * @return void
     */
    protected function _setStartDate($_startDate)
    {
        if (empty($_startDate)) {
            $this->_setDefaultStartDateIfEmpty();
            return;
        }

        $startDateParsed = array();

        if (is_string($_startDate)) {
            $startDateParsed['string'] = $_startDate;
            $startDateParsed['ts']     = strtotime($_startDate);
        }
        else if (is_int($_startDate)) {
            $startDateParsed['ts']     = $_startDate;
            $startDateParsed['string'] = strftime('%F', $_startDate);
        }
        else {
            $this->_setDefaultStartDateIfEmpty();
            return;
        }

        $startDateParsed['object'] = new DateTime($startDateParsed['string']);

        $startDateParsed = array_merge($startDateParsed, getdate($startDateParsed['ts']));

        $this->_startDate = $startDateParsed;
    }

    /**
     * Set default value for object property {@see $_startdate} if it is not set yet.
     * Tries to guess the correct startDate depending on object property {@see $_offsets} and
     * falls back to current date.
     *
     * @param array $_offsets [offsets may be avaluated for a given start year]
     * @return void
     */
    protected function _setDefaultStartDateIfEmpty($_offsets = null)
    {
        if (!empty($this->_startDate)) {
            return;
        }

        if (!empty($_offsets['standardYear'])) {
            $this->_setStartDate($_offsets['standardYear'].'-01-01');
        }
        else {
            $this->_setStartDate(time());
        }
    }

    /**
     * Check if the given {@param $_timezone} matches the {@see $_offsets}
     * and also evaluate the daylight saving time transitions for this timezone if necessary.
     *
     * @param DateTimeZone $timezone
     * @param array        $offsets
     *
     * @return array|bool
     */
    protected function _checkTimezone(DateTimeZone $timezone, $offsets)
    {
        list($standardTransition, $daylightTransition) = $this->_getTransitionsForTimezoneAndYear($timezone, $this->_startDate['year']);
        if ($this->_checkTransition($standardTransition, $daylightTransition, $offsets)) {
//            echo 'Matching timezone ' . $timezone->getName();
//            echo 'Matching daylight transition ' . print_r($daylightTransition, 1);
//            echo 'Matching standard transition ' . print_r($standardTransition, 1);
            return $standardTransition;
        }

        return false;
    }

    /**
     * Returns the standard and daylight transitions for the given {@param $_timezone}
     * and {@param $_year}.
     *
     * @param DateTimeZone $_timezone
     * @param $_year
     * @return Array
     */
    protected function _getTransitionsForTimezoneAndYear(DateTimeZone $_timezone, $_year)
    {
        $standardTransition = null;
        $daylightTransition = null;

        if (version_compare(PHP_VERSION, '5.3.0', '>=')) {
            // Since php version 5.3.0 getTransitions accepts optional start and end parameters.
            $start = mktime(0, 0, 0, 12, 1, $_year - 1);
            $end   = mktime(24, 0, 0, 12, 31, $_year);
            $transitions = $_timezone->getTransitions($start, $end);
        } else {
            $transitions = $_timezone->getTransitions();
        }

        $index = 0;            //we need to access index counter outside of the foreach loop
        $transition = array(); //we need to access the transition counter outside of the foreach loop
        foreach ($transitions as $index => $transition) {
            if (strftime('%Y', $transition['ts']) == $_year) {
                if (isset($transitions[$index+1]) && strftime('%Y', $transitions[$index]['ts']) == strftime('%Y', $transitions[$index+1]['ts'])) {
                    $daylightTransition = $transition['isdst'] ? $transition : $transitions[$index+1];
                    $standardTransition = $transition['isdst'] ? $transitions[$index+1] : $transition;
                }
                else {
                    $daylightTransition = $transition['isdst'] ? $transition : null;
                    $standardTransition = $transition['isdst'] ? null : $transition;
                }
                break;
            }
            else if ($index == count($transitions) -1) {
                $standardTransition = $transition;
            }
        }

        return array($standardTransition, $daylightTransition);
    }

    protected function _getCacheId($_prefix, $_offsets)
    {
        return $_prefix . md5(serialize($_offsets));
    }

    protected function _loadFromCache($key)
    {
        if ($cache = $this->getCache) {
            return $cache->get($key);
        }

        return false;
    }

    protected function _saveInCache($value, $key)
    {
        if ($cache = $this->getCache) {
            $cache->set($key, $value);
        }
    }

    /**
     * Getter for the cache engine object
     */
    protected function getCache()
    {
        if ($this->cache === null) {
            $rcube = rcube::get_instance();
            $cache = $rcube->get_cache_shared('activesync');
            $this->cache = $cache ? $cache : false;
        }

        return $this->cache;
    }
}
