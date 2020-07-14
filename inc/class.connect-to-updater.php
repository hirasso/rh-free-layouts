<?php 

if( !class_exists('RH_Connect_To_Updater') ) :

class RH_Connect_To_Updater {
  
  private $file = null;

  /**
   * Constructor
   */
  public function __construct($file) {
    $this->file = $file;
    add_action('plugins_loaded', [$this, 'connect']);
  }

  /**
   * Connects the plugin to RH Updater
   *
   * @return void
   */
  public function connect() {
    if( class_exists('\RH_Bitbucket_Updater') ) {
      new \RH_Bitbucket_Updater( $this->file );
    } else {
      add_action('admin_notices', [$this, 'show_notice_missing_rh_updater']);
      add_action('network_admin_notices', [$this, 'show_notice_missing_rh_updater']);
    }
  }

  /**
   * Shows the missing updater notice
   *
   * @return void
   */
  public function show_notice_missing_rh_updater() {
    global $rh_updater_notice_shown, $pagenow;
    if( 
      $rh_updater_notice_shown 
      || !current_user_can('activate_plugins')
      || !in_array($pagenow, ['plugins.php', 'update-core.php']) ) {
      return;
    }
    $rh_updater_notice_shown = true;
    $plugin_data = get_plugin_data( $this->file );
    ob_start(); ?>
    <div class="notice notice-warning">
      <p>RH Updater is not installed. Custom plugins by like „<?= $plugin_data['Name'] ?>“ won't receive updates.</p>
    </div>
    <?php echo ob_get_clean();
  }
  
}

endif;