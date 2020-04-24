<?php

namespace R;


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

class FreeLayout {

  function __construct() {
    add_action('wp_ajax_update_free_layout', [$this, 'update_free_layout_POST']);
    add_action('acf/include_field_types', [$this, 'include_field_types']);
    // add_filter('acf/update_value/type=rh_reset_free_layouts', function($value, $post_id) {
    //   pre_dump($value, true);
    // }, 999, 2);
  }

  /**
   * Register custom field types
   *
   * @return void
   */
  function include_field_types() {
    $dir = dirname(__FILE__);
    include_once("$dir/free-layout-field-types.php");
  }

  /**
   * Get a free layout by post and layout id
   *
   * @param int $post_id Post ID.
   * @param int $layout_id
   * @return void
   */
  function get_free_layout( $post_id, $layout_id ) {
    $layouts = $this->get_free_layouts($post_id);
    return $layouts[$layout_id] ?? false;
  }

  /**
   * Get all free layouts of a post
   *
   * @param int $post_id Post ID.
   * @return array
   */
  function get_free_layouts($post_id) {
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
    $layouts = $this->get_free_layouts($post_id);
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
    $layouts = $this->get_free_layouts($post_id);
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
$freeLayout = new FreeLayout();


