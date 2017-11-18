<?php

/**
 * Kolab Tags backend
 *
 * @author Aleksander Machniak <machniak@kolabsys.com>
 *
 * Copyright (C) 2014, Kolab Systems AG <contact@kolabsys.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

class kolab_tags_backend
{
    private $tag_cols = array('name', 'category', 'color', 'parent', 'iconName', 'priority', 'members');

    const O_TYPE     = 'relation';
    const O_CATEGORY = 'tag';


    /**
     * Tags list
     *
     * @param array $filter Search filter
     *
     * @return array List of tags
     */
    public function list_tags($filter = array())
    {
        $config   = kolab_storage_config::get_instance();
        $default  = true;
        $filter[] = array('type', '=', self::O_TYPE);
        $filter[] = array('category', '=', self::O_CATEGORY);

        // for performance reasons assume there will be no more than 100 tags (per-folder)

        return $config->get_objects($filter, $default, 100);
    }

    /**
     * Create tag object
     *
     * @param array $tag Tag data
     *
     * @return boolean|array Tag data on success, False on failure
     */
    public function create($tag)
    {
        $config = kolab_storage_config::get_instance();
        $tag    = array_intersect_key($tag, array_combine($this->tag_cols, $this->tag_cols));
        $tag['category'] = self::O_CATEGORY;

        // Create the object
        $result = $config->save($tag, self::O_TYPE);

        return $result ? $tag : false;
    }

    /**
     * Update tag object
     *
     * @param array $tag Tag data
     *
     * @return boolean|array Tag data on success, False on failure
     */
    public function update($tag)
    {
        // get tag object data, we need _mailbox
        $list    = $this->list_tags(array(array('uid', '=', $tag['uid'])));
        $old_tag = $list[0];

        if (!$old_tag) {
            return false;
        }

        $config = kolab_storage_config::get_instance();
        $tag    = array_intersect_key($tag, array_combine($this->tag_cols, $this->tag_cols));
        $tag    = array_merge($old_tag, $tag);

        // Update the object
        $result = $config->save($tag, self::O_TYPE, $tag['uid']);

        return $result ? $tag : false;
    }

    /**
     * Remove tag object
     *
     * @param string $uid Object unique identifier
     *
     * @return boolean True on success, False on failure
     */
    public function remove($uid)
    {
        $config = kolab_storage_config::get_instance();

        return $config->delete($uid);
    }
}
