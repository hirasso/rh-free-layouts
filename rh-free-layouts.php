<?php 
/**
 * Plugin Name: RH Free Layouts
 * Version: 1.0.3
 * Author: Rasso Hilber
 * Description: Free drag-and-drop layouts 
 * Author URI: https://rassohilber.com
**/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


class FreeLayout {

  function __construct() {
    add_action('plugins_loaded', [$this, 'connect_to_rh_updater']);
    add_action('wp_ajax_update_free_layout', [$this, 'update_free_layout_POST']);
    add_action('acf/include_field_types', [$this, 'include_field_types']);
    add_action('wp_enqueue_scripts', [$this, 'enqueue_assets'], $this->is_plugin_dev_mode() ? 10 : PHP_INT_MAX);
  }

  /**
   * Checks if we are in plugin dev mode
   *
   * @return boolean
   */
  private function is_plugin_dev_mode() {
    return defined('RHFL_DEV_MODE') && RHFL_DEV_MODE === true;
  }

  /**
   * Connects the plugin to RH Updater
   *
   * @return void
   */
  function connect_to_rh_updater() {
    if( class_exists('\RH_Bitbucket_Updater') ) {
      new \RH_Bitbucket_Updater( __FILE__ );
    } else {
      add_action('admin_notices', [$this, 'show_notice_missing_rh_updater']);
    }
  }

  /**
   * Shows the missing updater notice
   *
   * @return void
   */
  function show_notice_missing_rh_updater() {
    global $rh_updater_notice_shown;
    if( !$rh_updater_notice_shown && current_user_can('activate_plugins') ) {
      $rh_updater_notice_shown = true;
      echo "<div class='notice notice-warning'><p>RH Updater is not installed. Custom plugins won't be updated.</p></div>";
    }
  }

  /**
   * Enqueue assets
   *
   * @return void
   */
  function enqueue_assets() {
    wp_enqueue_style('rh-free-layouts', $this->asset_uri('assets/rh-free-layouts.css'), [], null, 'all');
    if( !current_user_can('edit_posts') ) return;
    
    wp_enqueue_script( 'rh-free-layouts', $this->asset_uri('assets/rh-free-layouts.js'), ['jquery', 'jquery-ui-draggable', 'jquery-ui-resizable'], null, true );
    $settings = [
      'ajaxUrl' => admin_url('admin-ajax.php'),
    ];

    wp_localize_script( 'rh-free-layouts', 'RHFL', $settings );
  }



  /**
   * Helper function to get versioned asset urls
   *
   * @param [type] $path
   * @return void
   */
  function asset_uri( $path ) {
    $uri = plugins_url( $path, __FILE__ );
    $file = $this->get_file_path( $path );
    if( file_exists( $file ) ) {
      $version = filemtime( $file );
      $uri .= "?v=$version";
    }
    return $uri;
  }

  /**
   * Register custom field types
   *
   * @return void
   */
  function include_field_types() {
    include_once( $this->get_file_path('includes/free-layout-field-types.php') );
  }

  /**
   * Gets the path of a file
   *
   * @return void
   */
  function get_file_path( $path ) {
    $path = ltrim( $path, '/' );
    $file = plugin_dir_path( __FILE__ ) . $path;
    return $file;
  }

  /**
   * Get a free layout by post and layout id
   *
   * @param int $post_id Post ID.
   * @param int $layout_id
   * @return void
   */
  function get_layout( $layout_id, $post_id = null ) {
    $layouts = $this->get_layouts($post_id);
    return $layouts[$layout_id] ?? false;
  }

  /**
   * Get all free layouts of a post
   *
   * @param int $post_id Post ID.
   * @return array
   */
  function get_layouts($post_id = null) {
    $post_id = $post_id ?? get_queried_object_id();
    $layouts = get_post_meta($post_id, '_free_layouts', true);
    return is_array($layouts) ? $layouts : [];
    // return (array) get_field('_free_layouts', $post_id);
  }

  /**
   * Updates the layouts for a post
   *
   * @param [type] $layouts
   * @return void
   */
  function update_free_layouts($post_id, $layouts) {
    return update_post_meta($post_id, '_free_layouts', $layouts);
    // return update_field('_free_layouts', $post_id, $layouts);
  }

  /**
   * Deletes all free layouts for a post
   *
   * @param int $post_id Post ID.
   * @return void
   */
  function delete_free_layouts($post_id) {
    return delete_post_meta($post_id, '_free_layouts');
    // return delete_field( '_free_layouts', $post_id );
  }

  /**
   * Update a free layout item
   *
   * @param int $layout_id
   * @param int $post_id Post ID.
   * @param [type] $style
   * @return void
   */
  function update_free_layout_item( $layout_id, $post_id, $style ) {
    $layouts = $this->get_layouts($post_id);
    $layouts[$layout_id] = $style;
    $this->update_free_layouts($post_id, $layouts);
  }

  /**
   * Reset a free layout item
   *
   * @param int $layout_id
   * @param int $post_id Post ID.
   * @return void
   */
  function reset_free_layout_item( $layout_id, $post_id ) {
    $layouts = $this->get_layouts($post_id);
    unset($layouts[$layout_id]);
    $this->update_free_layouts($post_id, $layouts);
  }

  /**
   * Update free layout by POST request
   *
   * @return void
   */
  function update_free_layout_POST() {

    $layout_id = $_POST["layout_id"] ?? false;
    $post_id = $_POST["post_id"] ?? false;
    if( !$layout_id || !$post_id ) return;

    $css = $_POST["css"] ?? false;

    // reset item and bail early if no css given
    if( !$css ) {
      $this->reset_free_layout_item( $layout_id, $post_id );
      wp_send_json_success([
        'message' => 'Database updated',
        'layout_id' => $layout_id,
        'post_id' => $post_id,
      ]);
      return;

    }
    // update the items layout
    $style = array();
    foreach ($css as $key => $value) {
      $style[] = "{$key}: {$value};";
    }
    $style = join(" ", $style);
    $this->update_free_layout_item( $layout_id, $post_id, $style );
    wp_send_json_success([
      'message' => 'Database updated',
      'layout_id' => $layout_id,
      'post_id' => $post_id,
      'style' => $style,
    ]);
  }

  /**
   * Renders custom field type styles
   *
   * @param [type] $field
   * @return void
   */
  function render_field_group_styles( $field ) {
    $selector = str_replace('_', '-', $field->name);
    ob_start() ?>
    <style>
      .acf-field-object-<?= $selector ?> .acf-field-setting-name,
      .acf-field-object-<?= $selector ?> .acf-field-setting-required,
      .acf-field-object-<?= $selector ?> .acf-field-setting-default_value {
        display: none !important;
      }
      .acf-field-object-<?= $selector ?> .li-field-name {
        visibility: hidden;
      }
    </style>
    <?php echo ob_get_clean();
  }
}
global $freeLayout;
$freeLayout = new FreeLayout();

/**
 * Template function to render and return a free layout item
 *
 * @param int $post_id Post ID.
 * @param int $layout_id
 * @param [type] $content
 * @return void
 */
function rhfl_wrap_item( $item, $content, $post_id = null ) {
  global $freeLayout;
  $post_id = $post_id ?? get_queried_object_id();
  $layout_id = $item->rh_free_layout ?? false;
  if( !$layout_id ) {
    return "This item doesn't support free layout";
  }
  $layout = $freeLayout->get_layout($layout_id, $post_id);
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

/**
 * Returns the layout for an item
 *
 * @param [type] $item
 * @param [type] $post_id
 * @return void
 */
function rhfl_get_layout($item, $post_id = null) {
  global $freeLayout;
  $layout_id = $item->rh_free_layout ?? false;
  if( !$layout_id ) return '';
  return $freeLayout->get_layout($layout_id, $post_id);
}