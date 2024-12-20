<?php

namespace Drupal\pi_comp\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Diagnostic controller.
 */
class DiagnosticController extends ControllerBase {

  public function diagnose() {
    $output = '<pre>';

    // Get module path
    $module_handler = \Drupal::service('module_handler');
    $module_path = $module_handler->getModule('pi_comp')->getPath();
    $output .= "Module path: $module_path\n\n";

    // Check services.yml
    $services_file = $module_path . '/pi_comp.services.yml';
    $output .= "Services file path: $services_file\n";
    $output .= "Services file exists: " . (file_exists($services_file) ? 'YES' : 'NO') . "\n";
    if (file_exists($services_file)) {
      $output .= "Services file content:\n" . file_get_contents($services_file) . "\n\n";
    }

    // Check class files
    $parser_path = $module_path . '/src/Services/Parser.php';
    $test_path = $module_path . '/src/Services/TestService.php';

    $output .= "Parser path: $parser_path\n";
    $output .= "Parser exists: " . (file_exists($parser_path) ? 'YES' : 'NO') . "\n";
    if (file_exists($parser_path)) {
      $output .= "Parser namespace: " . $this->getNamespace($parser_path) . "\n\n";
    }

    $output .= "TestService path: $test_path\n";
    $output .= "TestService exists: " . (file_exists($test_path) ? 'YES' : 'NO') . "\n";
    if (file_exists($test_path)) {
      $output .= "TestService namespace: " . $this->getNamespace($test_path) . "\n\n";
    }

    // Check actual Parser location
    try {
      $reflection = new \ReflectionClass('Drupal\\pi_comp\\Services\\Parser');
      $output .= "Actual Parser file location: " . $reflection->getFileName() . "\n";
    }
    catch (\Exception $e) {
      $output .= "Could not reflect Parser class: " . $e->getMessage() . "\n";
    }

    // List all files in src directory
    $output .= "\nFiles in src directory:\n";
    $this->listFiles($module_path . '/src', $output);

    $output .= '</pre>';

    return [
      '#markup' => $output,
    ];
  }

  private function getNamespace($file) {
    if (!file_exists($file)) {
      return 'File not found';
    }

    $content = file_get_contents($file);
    if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
      return $matches[1];
    }

    return 'No namespace found';
  }

  private function listFiles($dir, &$output, $prefix = '') {
    if (!is_dir($dir)) {
      return;
    }

    $files = scandir($dir);
    foreach ($files as $file) {
      if ($file === '.' || $file === '..') {
        continue;
      }

      $path = $dir . '/' . $file;
      $output .= $prefix . $file . "\n";

      if (is_dir($path)) {
        $this->listFiles($path, $output, $prefix . '  ');
      }
    }
  }

}
