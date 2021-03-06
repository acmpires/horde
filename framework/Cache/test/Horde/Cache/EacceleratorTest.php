<?php
/**
 * Copyright 2016 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Cache
 */

/**
 * This class tests the eAccelerator backend.
 *
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Cache
 */
class Horde_Cache_EacceleratorTest extends Horde_Cache_TestBase
{
    protected function _getCache($params = array())
    {
        if (!extension_loaded('eaccelerator')) {
            $this->reason = 'eAccelerator extension not loaded';
            return;
        }
        if (!function_exists('eaccelerator_gc')) {
            $this->reason = 'eAccelerator must be compiled with support for shared memory to use as caching backend.';
            return;
        }
        return new Horde_Cache(
            new Horde_Cache_Storage_Eaccelerator(array(
                'prefix' => 'horde_cache_test'
            ))
        );
    }
}
