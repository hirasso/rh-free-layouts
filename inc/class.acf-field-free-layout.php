<?php

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
	}

  function update_value( $value, $post_id, $field ) {
    if ( empty( $value ) ) {
      $value = uniqid('free_layout_');
    } else {
      // maybe reset the custom layout for this ID
      $reset = (bool) intval($_POST["rh_reset_$value"] ?? 0);
      if( $reset ) {
        rhfl()->reset_free_layout_item( $value, $post_id );
        $value = uniqid('free_layout_');
      }
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
    // necessary, if not present, 'update_value' will not be fired
    $input_type = 'hidden';
    $readonly = '';
    if( rhfl()->is_plugin_dev_mode() ) {
      $input_type = 'text';
      $readonly = 'readonly';
    }
    printf(
      "<input type='$input_type' $readonly name='%s' value='%s' id='%s'>",
      esc_attr( $field['name'] ),
      esc_attr( $field['value'] ),
      esc_attr( $field['id'] )
    );
    // render reset ui if field has a value
    if( $field['value'] ) {
      acf_render_field([
        'type'			=> 'true_false',
        'label'			=> false,
        'name'			=> sprintf( "rh_reset_%s", esc_attr( $field['value'] ) ),
        'message'   => __("Reset this item's free layout"),
      ]);
    } else {
      _e('Save the post to generate a layout id');
    }
    
  }

  /**
   * Prepare the field
   *
   * @param [type] $field
   * @return void
   */
  function prepare_field( $field ) {
    if( empty($field['show_reset_checkbox']) ) {
      $field['wrapper']['class'] .= ' hidden';
    }
    return $field;
  }

  /**
   * Render custom field group styles for this field
   *
   * @return void
   */
  function field_group_admin_enqueue_scripts() {
    rhfl()->render_field_group_styles( $this );
  }

  /**
   * Render custom field settings
   *
   * @return void
   */
  function render_field_settings( $field ) {
    acf_render_field_setting( $field, array(
      'label'			=> __('Show Reset Checkbox?'),
      'instructions'	=> '',
      'name'			=> 'show_reset_checkbox',
      'type'			=> 'true_false',
      'ui'			=> 1,
    ));
  }
	
}