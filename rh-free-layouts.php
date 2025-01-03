<?php
/**
 * Plugin Name: RH Free Layouts
 * Version: 1.4.1
 * Author: Rasso Hilber
 * Description: Free drag-and-drop layouts
 * Author URI: https://rassohilber.com
 **/


if (!defined('ABSPATH')) exit; // Exit if accessed directly

require_once(__DIR__ . '/inc/class.singleton.php');

class RHFreeLayouts extends RHSingleton
{

  /**
   * Constructor
   */
  public function __construct()
  {
    add_action('wp_ajax_update_free_layout', [$this, 'update_free_layout_POST']);
    add_action('acf/include_field_types', [$this, 'include_field_types']);
    add_action('wp_enqueue_scripts', [$this, 'enqueue_style'], 10);
    add_action('wp_enqueue_scripts', [$this, 'enqueue_script'], 10);
  }

  /**
   * Checks if we are in plugin dev mode
   *
   * @return boolean
   */
  public function is_plugin_dev_mode()
  {
    return defined('RHFL_DEV_MODE') && RHFL_DEV_MODE === true;
  }

  /**
   * Gets capability for layout editing
   *
   * @return void
   */
  private function get_capability()
  {
    return apply_filters('rhfl/settings/capability', 'edit_posts');
  }

  /**
   * Checks current users capability against plugin setting
   *
   * @return void
   */
  private function is_edit_mode_enabled()
  {
    return current_user_can($this->get_capability());
  }

  /**
   * Enqueue style
   *
   * @return void
   */
  public function enqueue_style()
  {
    wp_enqueue_style('rh-free-layouts', $this->asset_uri('assets/rh-free-layouts.css'), [], null, 'all');
  }

  /**
   * Enqueue scripts
   *
   * @return void
   */
  public function enqueue_script()
  {

    if (!$this->is_edit_mode_enabled()) return;

    wp_enqueue_script('rh-free-layouts', $this->asset_uri('assets/rh-free-layouts.js'), ['jquery', 'jquery-ui-draggable', 'jquery-ui-resizable'], null, false);
    $settings = [
      'ajaxUrl' => admin_url('admin-ajax.php'),
      'initEditMode' => false,
      'options' => apply_filters('rhfl/options', (object) [
        'containerSelector' => 'body', // initialize on this
        'groupSelector' => 'body', // group items inside  of this
        'cancelDraggingSelector' => 'a'
      ]),
    ];

    wp_localize_script('rh-free-layouts', 'RHFL', $settings);
  }

  /**
   * Helper function to get versioned asset urls
   *
   * @param [type] $path
   * @return void
   */
  public function asset_uri($path)
  {
    $uri = plugins_url($path, __FILE__);
    $file = $this->get_file_path($path);
    if (file_exists($file)) {
      $version = filemtime($file);
      $uri .= "?v=$version";
    }
    return $uri;
  }

  /**
   * Register custom field types
   *
   * @return void
   */
  public function include_field_types()
  {
    include_once($this->get_file_path('inc/class.acf-field-free-layout.php'));
    include_once($this->get_file_path('inc/class.acf-field-reset-free-layouts.php'));

    acf_register_field_type('rh_acf_field_free_layout');
    acf_register_field_type('rh_acf_field_reset_free_layouts');
  }

  /**
   * Gets the path of a file
   *
   * @return void
   */
  public function get_file_path($path): string
  {
    $path = ltrim($path, '/');
    $file = plugin_dir_path(__FILE__) . $path;
    return $file;
  }

  /**
   * Get a free layout by post and layout id
   *
   * @param int $post_id Post ID.
   * @param int $layout_id
   * @return void
   */
  public function get_layout($layout_id, $post_id = null)
  {
    $layouts = $this->get_layouts($post_id);
    return $layouts[$layout_id] ?? false;
  }

  /**
   * Get all free layouts of a post
   *
   * @param int $post_id Post ID.
   * @return array
   */
  public function get_layouts($post_id = null)
  {
    $post_id = $post_id ?? get_queried_object_id();
    $layouts = get_post_meta($post_id, '_free_layouts', true);
    return is_array($layouts) ? $layouts : [];
  }

  /**
   * Updates the layouts for a post
   *
   * @param [type] $layouts
   * @return void
   */
  public function update_free_layouts($post_id, $layouts)
  {
    do_action('rhfl/update_free_layouts', $post_id, $layouts);
    $this->maybe_delete_super_cache_post($post_id);
    return update_post_meta($post_id, '_free_layouts', $layouts);
  }

  /**
   * Deletes the cache for a post cached by wp super cache
   *
   * @param int $post_id
   * @return void
   * @author Rasso Hilber <mail@rassohilber.com>
   */
  private function maybe_delete_super_cache_post($post_id)
  {
    if (function_exists('wpsc_delete_post_cache')) wpsc_delete_post_cache($post_id);
  }

  /**
   * Deletes all free layouts for a post
   *
   * @param int $post_id Post ID.
   * @return void
   */
  public function delete_free_layouts($post_id)
  {
    do_action('rhfl/delete_free_layouts', $post_id);
    return delete_post_meta($post_id, '_free_layouts');
  }

  /**
   * Update a free layout item
   *
   * @param int $layout_id
   * @param int $post_id Post ID.
   * @param [type] $style
   * @return void
   */
  public function update_free_layout_item($layout_id, $post_id, $style)
  {
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
  public function reset_free_layout_item($layout_id, $post_id)
  {
    $layouts = $this->get_layouts($post_id);
    unset($layouts[$layout_id]);
    $this->update_free_layouts($post_id, $layouts);
  }

  /**
   * Update free layout by POST request
   *
   * @return void
   */
  public function update_free_layout_POST()
  {

    $updated_message = apply_filters('rhfl/message_database_updated', 'Database updated');
    $layout_id = $_POST["layout_id"] ?? false;
    $post_id = $_POST["post_id"] ?? false;
    if (!$layout_id || !$post_id) return;

    $css = $_POST["css"] ?? false;
    $post_id = intval($post_id);

    // reset item and bail early if no css given
    if (!$css) {
      $this->reset_free_layout_item($layout_id, $post_id);
      wp_send_json_success([
        'message' => $updated_message,
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
    $this->update_free_layout_item($layout_id, $post_id, $style);
    wp_send_json_success([
      'message' => $updated_message,
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
  public function render_field_group_styles($field)
  {
    $selector = str_replace('_', '-', $field->name);
    ob_start() ?>
    <style>
      .acf-field-object-<?= $selector ?>.acf-field-setting-name,
      .acf-field-object-<?= $selector ?>.acf-field-setting-required,
      .acf-field-object-<?= $selector ?>.acf-field-setting-default_value {
        display: none !important;
      }

      .acf-field-object-<?= $selector ?>.li-field-name {
        visibility: hidden;
      }
    </style>
  <?php echo ob_get_clean();
  }

  /**
   * Fires the edit mode
   *
   * @return void
   */
  public function get_edit_mode_js()
  {
    if (!$this->is_edit_mode_enabled()) return;
    ob_start() ?>
    <script>
      if (RHFL.initEditMode) {
        // console.log('[rhfl] edit mode initiated');
        RHFL.initEditMode(RHFL.options);
      } else {
        // console.log('[rhfl] waiting for edit mode...')
        jQuery(document).ready(function() {
          // console.log('[rhfl] ...edit mode initiated');
          RHFL.initEditMode(RHFL.options);
        });
      }
    </script>
  <?php echo ob_get_clean();
  }

  /**
   * Wraps a layout item
   *
   * @param [type] $item
   * @param [type] $content
   * @param [type] $post_id
   * @return void
   */
  public function wrap_item($item, $content, $post_id = null)
  {
    $post_id = $post_id ?? get_queried_object_id();
    $layout_id = $item->rh_free_layout ?? false;
    if (!$layout_id) {
      return "This item doesn't support free layout";
    }
    $layout = $this->get_layout($layout_id, $post_id);
    ob_start() ?>

    <div class="free-layout_item-wrap">
      <div class="free-layout_item" <?= $layout ? "style='$layout'" : '' ?> data-layout-id="<?= $layout_id ?>" data-post-id="<?= $post_id ?>">

        <?= $content ?>

      </div><!-- /layout_item -->
    </div><!-- /layout_item-wrap -->

<?php return ob_get_clean();
  }
}

/**
 * Returns the instance
 *
 * @return RHFreeLayouts
 */
function rhfl()
{
  return RHFreeLayouts::getInstance();
}
rhfl();
