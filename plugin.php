<?php 
/**
 * Plugin Name: RH Free Layouts
 * Version: 1.0.0
 * Author: Rasso Hilber
 * Description: Free drag-and-drop layouts 
 * Author URI: https://rassohilber.com
**/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

global $rh_updater_notice_shown;
add_action('plugins_loaded', function() {
  if( class_exists('\RH_Bitbucket_Updater') ) {
    new \RH_Bitbucket_Updater( __FILE__ );
  } else {
    add_action('admin_notices', function() {
      global $rh_updater_notice_shown;
      if( !$rh_updater_notice_shown && current_user_can('activate_plugins') ) {
        $rh_updater_notice_shown = true;
        echo "<div class='notice notice-warning'><p>RH_Updater is not installed. Custom plugins won't be updated.</p></div>";
      }
    });
  }
});