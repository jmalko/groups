<?php
/**
 * class-groups-utility.php
 *
 * Copyright (c) "kento" Karim Rahimpur www.itthinx.com
 *
 * This code is released under the GNU General Public License.
 * See COPYRIGHT.txt and LICENSE.txt.
 *
 * This code is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * This header and all notices must be kept intact.
 *
 * @author Karim Rahimpur
 * @package groups
 * @since groups 1.0.0
 */

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Utility functions.
 */
class Groups_Utility {

	/**
	 * Checks an id (0 is accepted => anonymous).
	 * 
	 * @param string|int $id
	 * @return int|boolean if validated, the id as an int, otherwise false
	 */
	public static function id( $id ) {
		$result = false;
		if ( is_numeric( $id ) ) {
			$id = intval( $id );
			//if ( $id > 0 ) {
			if ( $id >= 0 ) { // 0 => anonymous
				$result = $id;
			}
		}
		return $result;
	}

	/**
	 * Returns an array of blog_ids for current blogs.
	 * @return array of int with blog ids
	 */
	public static function get_blogs() {
		global $wpdb;
		$result = array();
		if ( is_multisite() ) {
			$blogs = $wpdb->get_results( $wpdb->prepare(
				"SELECT blog_id FROM $wpdb->blogs WHERE site_id = %d AND archived = '0' AND spam = '0' AND deleted = '0' ORDER BY registered DESC",
				$wpdb->siteid
			) );
			if ( is_array( $blogs ) ) {
				foreach( $blogs as $blog ) {
					$result[] = $blog->blog_id;
				}
			}
		} else {
			$result[] = get_current_blog_id();
		}
		return $result;
	}


	public static function get_group_tree( &$tree = null ) {
		global $wpdb;
		$group_table = _groups_get_tablename( 'group' );

		if ( $tree === null ) {
			$tree = array();
			$root_groups = $wpdb->get_results( "SELECT group_id FROM $group_table WHERE parent_id IS NULL" );
			if ( $root_groups ) {
				foreach( $root_groups as $root_group ) {
					$group_id = Groups_Utility::id( $root_group->group_id );
					$tree[$group_id] = array();
				}
			}
			self::get_group_tree( $tree );
		} else {
			foreach( $tree as $group_id => $nodes ) {
				$children = $wpdb->get_results( $wpdb->prepare(
					"SELECT group_id FROM $group_table WHERE parent_id = %d",
					Groups_Utility::id( $group_id )
				) );
				foreach( $children as $child ) {
					$tree[$group_id][$child->group_id] = array();
				}
				self::get_group_tree( $tree[$group_id] );
			}
		}

		return $tree;
	}

	public static function render_group_tree( &$tree, &$output ) {
		$output .= '<ul style="padding-left:1em">';
		foreach( $tree as $group_id => $nodes ) {
			$output .= '<li>';
			$group = Groups_Group::read( $group_id );
			if ( $group ) {
				$output .= $group->name;
			}
			if ( !empty( $nodes ) ) {
				self::render_group_tree( $nodes, $output );
			}
			$output .= '</li>';
		}
		$output .= '</ul>';
	}
	
	public static function get_below_group_ids_from_user( $user_id ) {
		$groups_user = new Groups_User( $user_id );
		$user_group_ids = $groups_user->group_ids;
		$all_groups = array();

		foreach ($user_group_ids as $group){
			array_push($all_groups, $group);
			$somegroups = self::getChildrenIds($group);
			$all_groups = array_merge($all_groups, $somegroups);

		}
		return $all_groups;
	}


	public static function get_childs(&$output, $parent) {
		global $wpdb;
		$group_table = _groups_get_tablename( 'group' );
		$all_groups = $wpdb->get_results( $wpdb->prepare("SELECT group_id FROM $group_table WHERE parent_id = %d",
			Groups_Utility::id( $parent ) ));

		foreach($all_groups as $group) {
			$output[] = $group->group_id;
			self::get_childs($output, $group->group_id );
		}
		return $output;
	}

	public static function getChildrenIds($parent) {
		$output = array();
		self::get_childs($output, $parent);
		return $output;
	}

	
	

	/**
	 * Compares the two object's names, used for groups and
	 * capabilities, i.e. Groups_Group and Groups_Capability can be compared
	 * if both are of the same class. Otherwise this will return 0.
	 *
	 * @param Groups_Group|Groups_Capability $o1
	 * @param Groups_Group|Groups_Capability $o2 must match the class of $o1
	 *
	 * @return number
	 */
	public static function cmp( $o1, $o2 ) {
		$result = 0;
		if ( $o1 instanceof Groups_Group && $o2 instanceof Groups_Group ) {
			$result = strcmp( $o1->name, $o2->name );
		} else if ( $o1 instanceof Groups_Capability && $o2 instanceof Groups_Capability ) {
			$c1 = is_object( $o1->capability ) && isset( $o1->capability->capability ) ? $o1->capability->capability : '';
			$c2 = is_object( $o2->capability ) && isset( $o2->capability->capability ) ? $o2->capability->capability : '';
			$result = strcmp( $c1, $c2 );
		}
		return $result;
	}
}
