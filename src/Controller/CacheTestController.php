<?php

namespace Drupal\pi_comp\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Cache test controller.
 */
class CacheTestController extends ControllerBase {

  public function test() {
    $module_path = \Drupal::service('extension.list.module')->getPath('pi_comp');
    $services_file = $module_path . '/pi_comp.services.yml';

    $output = '<pre>';
    $output .= "Services file location: " . $services_file . "\n";
    $output .= "Services file exists: " . (file_exists($services_file) ? 'YES' : 'NO') . "\n";
    if (file_exists($services_file)) {
      $output .= "\nServices file content:\n";
      $output .= file_get_contents($services_file) . "\n";
    }

    // Try to get paths for both classes
    $output .= "\nClass file paths:\n";
    $output .= "Parser: " . $module_path . "/src/Services/Parser.php\n";
    $output .= "Parser exists: " . (file_exists($module_path . "/src/Services/Parser.php") ? 'YES' : 'NO') . "\n";
    $output .= "PimmProjectManager: " . $module_path . "/src/Services/PimmProjectManager.php\n";
    $output .= "PimmProjectManager exists: " . (file_exists($module_path . "/src/Services/PimmProjectManager.php") ? 'YES' : 'NO') . "\n";

    // Check container's services info
    $container = \Drupal::getContainer();
    if ($container) {
      $output .= "\nContainer service IDs:\n";
      $services = array_filter($container->getServiceIds(), function($id) {
        return strpos($id, 'pi_comp') === 0;
      });
      $output .= implode("\n", $services) . "\n";
    }

    $output .= '</pre>';

    return [
      '#type' => 'markup',
      '#markup' => $output,
    ];
  }
}
