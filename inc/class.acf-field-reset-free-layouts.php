<?php 

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
    if( $value ) {
      rhfl()->delete_free_layouts($post_id);
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
    rhfl()->render_field_group_styles( $this );
  }
	
}