<?php

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
use YahnisElsts\PluginUpdateChecker\v5p7\PucFactory as VersionedPucFactory;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Check for updates using Plugin Update Checker
 * @see https://github.com/YahnisElsts/plugin-update-checker
 */
class RHFLUpdateChecker
{
  public static function init(string $entry_point): void
  {
    self::load_library();

    /** Prevent duplication of updates in rh-updater */
    add_filter('rh-updater/allow/slug=rh-free-layouts', '__return_false');

    $checker = PucFactory::buildUpdateChecker(
      'https://github.com/hirasso/rh-free-layouts/',
      $entry_point,
      'rh-free-layouts',
    );

    $checker->setBranch('main');

    if ($token = self::get_github_token()) {
      $checker->setAuthentication($token);
    }

    /**
     * Expect a "rh-free-layouts.zip" attached to every release (see release.sh)
     * @var \YahnisElsts\PluginUpdateChecker\v5p7\Vcs\GitHubApi $api
     */
    $api = $checker->getVcsApi();
    $api->enableReleaseAssets('/rh-free-layouts\.zip/i', $api::REQUIRE_RELEASE_ASSETS);

    $checker->addFilter('vcs_update_detection_strategies', [self::class, 'update_strategies'], 999);
  }

  /**
   * Load the bundled copy of plugin-update-checker, unless another plugin already did
   */
  private static function load_library(): void
  {
    if (class_exists(VersionedPucFactory::class, false)) {
      return;
    }

    require_once dirname(__DIR__) . '/lib/plugin-update-checker/plugin-update-checker.php';
  }

  /**
   * Get the RHFL_GITHUB_TOKEN for authenticated GitHub requests
   */
  private static function get_github_token(): ?string
  {
    if (
      defined('RHFL_GITHUB_TOKEN')
      && is_string(RHFL_GITHUB_TOKEN)
      && !empty(trim(RHFL_GITHUB_TOKEN))
    ) {
      return RHFL_GITHUB_TOKEN;
    }
    return null;
  }

  /**
   * Only keep the "latest_release" strategy (GitHub releases are created by release.sh)
   */
  public static function update_strategies(array $strategies): array
  {
    return ['latest_release' => $strategies['latest_release']];
  }
}
