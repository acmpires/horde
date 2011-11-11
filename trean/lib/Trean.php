<?php
/**
 * Trean Base Class.
 *
 * $Horde: trean/lib/Trean.php,v 1.93 2009-11-29 15:51:42 chuck Exp $
 *
 * Copyright 2002-2009 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you did not
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 *
 * @author  Mike Cochrane <mike@graftonhall.co.nz>
 * @package Trean
 */
class Trean
{
    /**
     * Returns the specified permission for the current user.
     *
     * @param string $permission  A permission, currently only 'max_folders'
     *                            and 'max_bookmarks'.
     *
     * @return mixed  The value of the specified permission.
     */
    function hasPermission($permission)
    {
        $perms = $GLOBALS['injector']->getInstance('Horde_Perms');
        if (!$perms->exists('trean:' . $permission)) {
            return true;
        }

        $allowed = $perms->getPermissions('trean:' . $permission, $GLOBALS['registry']->getAuth());
        if (is_array($allowed)) {
            switch ($permission) {
            case 'max_folders':
            case 'max_bookmarks':
                $allowed = max($allowed);
                break;
            }
        }

        return $allowed;
    }

    /**
     * Returns an apropriate icon for the given bookmark.
     */
    function getFavicon($bookmark)
    {
        global $registry;

        // Initialize VFS.
        try {
            $vfs = $GLOBALS['injector']->getInstance('Horde_Core_Factory_Vfs')->create();
            if ($bookmark->favicon
                && $vfs->exists('.horde/trean/favicons/', $bookmark->favicon)) {
                return Horde_Util::addParameter(Horde::url('favicon.php'),
                                                'bookmark_id', $bookmark->id);
            }
        } catch (Exception $e) {
        }

        // Default to the protocol icon.
        $protocol = substr($bookmark->url, 0, strpos($bookmark->url, '://'));
        return Horde_Themes::img('/protocol/' . (empty($protocol) ? 'http' : $protocol) . '.png');
    }
}
