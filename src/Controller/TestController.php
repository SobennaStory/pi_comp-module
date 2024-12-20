<?php

namespace Drupal\pi_comp\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Test controller for simple service.
 */
class TestController extends ControllerBase {

  /**
   * Test page callback.
   */
  public function test() {
    $output = '<pre>';

    // Try to get the service
    try {
      $test_service = \Drupal::service('pi_comp.test');
      $message = $test_service->getMessage();
      $output .= "Service loaded successfully!\n";
      $output .= "Message: " . $message . "\n";
    }
    catch (\Exception $e) {
      $output .= "Error loading service: " . $e->getMessage() . "\n";
    }

    $output .= "\nRegistered pi_comp services:\n";
    $container = \Drupal::getContainer();
    foreach ($container->getServiceIds() as $id) {
      if (strpos($id, 'pi_comp') === 0) {
        $output .= $id . "\n";
      }
    }

    $output .= '</pre>';

    return [
      '#markup' => $output,
    ];
  }

}
