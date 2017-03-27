<?php
/*
Plugin Name: Fotosnap: Activity and Event Tracking
Plugin URI:
Description: Tracks events associated with users (such as logins) and posts (such as updates) in a way that can easily be reported and queried against. Creates a new table, fs_activity.
Version: 1.0
Author: juellez
Text Domain: fslog
*/

# Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

global $fslog_db_version;
$fslog_db_version = '1.0';

function fslog_install() {
  global $wpdb;
  global $fslog_db_version;

  $table_name = 'fs_activity';
  
  $charset_collate = $wpdb->get_charset_collate();

  $sql = "CREATE TABLE `fs_activity` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `user_login` varchar(128) DEFAULT NULL,
    `user_id` int(11) DEFAULT NULL,
    `post_id` int(11) DEFAULT NULL,
    `activity` varchar(36) DEFAULT NULL,
    `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    `meta` text,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `activity` (`activity`)
  ) $charset_collate;";

  require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
  dbDelta( $sql );

  add_option( 'fslog_db_version', $fslog_db_version );
}
register_activation_hook( __FILE__, 'fslog_install' );

/*
 * track an activity
 * @param array with at least one of the following keys
 *  'user_id' 
 *  'user_login'
 *  'post_id'
 *  @param string - action e.g. 'login', 'emailed'
 *  @param array (optional) - additional metadata (will be serialized)
 *  @return int - log id
 */
function fslog_logit_raw( $ids, $action, $meta = '' ){
  // check for ids
  $user_id      =  empty($ids['user_id'])     ? 0  : $ids['user_id'] + 0;
  $user_login   =  empty($ids['user_login'])  ? '' : $ids['user_login'];
  $post_id      =  empty($ids['post_id'])     ? 0  : $ids['post_id'] + 0;

  $row = array(
    'user_id' => $user_id,
    'user_login' => $user_login,
    'post_id' => $post_id,
    'activity' => $action,
    'meta' => serialize($meta)
  );
  $formats = array(
    '%d', // user id = #
    '%s', // user login = string
    '%d', // post id = #
    '%s', // action = string
    '%s'  // meta = string
  );
  global $wpdb;
  return $wpdb->insert( 'fs_activity', $row );
}

function fslog_get_activity_raw( $filters, $limit = 10 ){
  // this is internal/admin only
  $where = array(1);

  if( !empty($filters['post_id']) ){
    $where[] = ' post_id = ' . $filters['post_id'];
  }
  if( !empty($filters['action']) ){
    $where[] = ' activity = "' . $filters['action'] . '"';
  }
  if( !empty($filters['actions']) ){
    $where[] = ' activity in ("' . implode('","', $filters['actions']) . '")';
  }

  $where = implode(' AND ', $where);

  global $wpdb;
  $sql = "
    SELECT a.*, u.user_email
    FROM fs_activity a
    LEFT JOIN wp_users u ON (a.user_id = u.ID)
    WHERE $where
    ORDER BY `timestamp` DESC
    LIMIT $limit
  ";

  return $wpdb->get_results($sql);
}

/* helper functions */

function fslog_get_post_events( $post_id, $actions = false ){
  $events = fslog_get_activity_raw( array(
    'post_id' => $post_id,
    'actions' => $actions
    ) );
  if( $events ){
    foreach($events as $i => $row){
      $row->meta = unserialize($row->meta);
      $events[$i] = $row;
    }
  }
  return $events;
}

function fslog_log_post_event( $post_id, $action, $meta ){
  $user_id = get_current_user_id();
  return fslog_logit_raw( array('post_id'=>$post_id,'user_id'=>$user_id), $action, $meta );
}

