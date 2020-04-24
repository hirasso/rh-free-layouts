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

/**
 * Template function to render and return a free layout item
 *
 * @param int $post_id Post ID.
 * @param int $layout_id
 * @param [type] $content
 * @return void
 */
function wrap_free_layout_item( $item, $content ) {
  global $freeLayout;
  $post_id = get_queried_object_id();
  $layout_id = $item->rh_free_layout ?? false;
  if( !$layout_id ) {
    return "This item doesn't support free layout";
  }
  $layout = $freeLayout->get_free_layout($post_id, $layout_id);
  ob_start() ?>

  <div class="free-layout_item-wrap">
  <div class="free-layout_item"
    <?= $layout ? "style='$layout'" : '' ?>
    data-layout-id="<?= $layout_id ?>" 
    data-post-id="<?= $post_id ?>">

    <?= $content ?>

  </div><!-- /layout_item -->
  </div><!-- /layout_item-wrap -->
  
  <?php return ob_get_clean();
}