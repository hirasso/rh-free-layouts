<?php

acf_register_field_type( 'rh_acf_field_free_layout' );
acf_register_field_type( 'rh_acf_field_reset_free_layouts' );

/**
 * Register a custom ACF field type
 * 
 * @url reference https://github.com/AdvancedCustomFields/acf-field-type-template/blob/master/acf-FIELD-NAME/fields/class-NAMESPACE-acf-field-FIELD-NAME-v5.php
 */
class rh_acf_field_free_layout extends \acf_field {
	
	function initialize() {
		// vars
		$this->name = 'rh_free_layout';
		$this->label = __("Free Layout");
		$this->category = __("Free Layout");
    $this->defaults = [
      'readonly' => 1,
    ];
	}

  function load_value( $value, $post_id, $field ) {
    if( !$value ) $value = uniqid("free_layout_");
    return $value;
  } 

  function update_value( $value, $post_id, $field ) {
    global $freeLayout;
    // maybe reset the custom layout for this ID
    $reset = intval($_POST["rh_reset_$value"] ?? 0);
    if( $reset ) {
      $freeLayout->reset_free_layout_item( $value, $post_id );
    }
    return $value;
  }
  
  /**
   * Load field (sets the name programmatically)
   *
   * @param [type] $field
   * @return void
   */
  function load_field( $field ) {
    $field['name'] = $this->name;
    $field['_name'] = $this->name;
    $field['required'] = 0;
    return $field;
  }

  /**
   * Render the field
   *
   * @param [type] $field
   * @return void
   */
  function render_field( $field ) {
    $field = json_decode(json_encode($field));
    echo "<!-- Rendered by free-layout.php -->\n";
    echo "<input type='hidden' value='$field->value' name='$field->name' id='$field->id'></input>\n";

    acf_render_field([
      'type'			=> 'true_false',
      'label'			=> false,
      'name'			=> "rh_reset_$field->value",
      'message'   => __("Reset this item's free layout"),
    ]);
  }

  /**
   * Render custom field group styles for this field
   *
   * @return void
   */
  function field_group_admin_enqueue_scripts() {
    global $freeLayout;
    $freeLayout->render_field_group_styles( $this );
  }
	
}


/**
 * Register a custom ACF field type
 * 
 * @url reference https://github.com/AdvancedCustomFields/acf-field-type-template/blob/master/acf-FIELD-NAME/fields/class-NAMESPACE-acf-field-FIELD-NAME-v5.php
 */
class rh_acf_field_reset_free_layouts extends \acf_field_true_false {
	
	function initialize() {
		// vars
		$this->name = 'rh_reset_free_layouts';
		$this->label = __("Reset Free Layouts");
		$this->category = __("Free Layout");
    $this->defaults = [];
	}

  /**
   * Resets the free layout if activated
   *
   * @param [type] $value
   * @param [type] $post_id
   * @param [type] $field
   * @return void
   */
  function update_value( $value, $post_id, $field ) {
    global $freeLayout;
    if( $value ) {
      $freeLayout->delete_free_layouts($post_id);
    }
    return '';
  }
  
  /**
   * Load field (sets the name programmatically)
   *
   * @param [type] $field
   * @return void
   */
  function load_field( $field ) {
    $field['name'] = $this->name;
    $field['_name'] = $this->name;
    $field['required'] = 0;
    return $field;
  }

  /**
   * Render custom field group styles for this field
   *
   * @return void
   */
  function field_group_admin_enqueue_scripts() {
    global $freeLayout;
    $freeLayout->render_field_group_styles( $this );
  }
	
}
