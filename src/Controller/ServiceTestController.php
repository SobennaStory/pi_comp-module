<?php

namespace Drupal\pi_comp\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Extension\ExtensionPathResolver;

/**
 * Test controller for debugging service registration.
 */
class ServiceTestController extends ControllerBase {

  protected $extensionPathResolver;

  public function __construct(ExtensionPathResolver $extension_path_resolver) {
    $this->extensionPathResolver = $extension_path_resolver;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('extension.path.resolver')
    );
  }

  public function debug() {
    $build = [];
    $build['#markup'] = '<pre>';

    $module_path = $this->extensionPathResolver->getPath('module', 'pi_comp');

    // Debug environment
    $build['#markup'] .= "\n=== Environment Info ===\n";
    $build['#markup'] .= "PHP Version: " . phpversion() . "\n";
    $build['#markup'] .= "Module Path: " . $module_path . "\n";

    // Debug file existence
    $build['#markup'] .= "\n=== File System Check ===\n";
    $files_to_check = [
      '/pi_comp.services.yml',
      '/src/Services/PimmProjectManager.php',
      '/src/Services/PimmProjectManagerInterface.php',
      '/src/Services/Parser.php',
      '/src/Services/ParserInterface.php',
    ];

    foreach ($files_to_check as $file) {
      $full_path = $module_path . $file;
      $build['#markup'] .= "$file: " . (file_exists($full_path) ? "EXISTS" : "MISSING") . "\n";
      if (file_exists($full_path)) {
        $build['#markup'] .= "  Permissions: " . substr(sprintf('%o', fileperms($full_path)), -4) . "\n";
        $build['#markup'] .= "  File size: " . filesize($full_path) . " bytes\n";

        if (strpos($file, '.php') !== false) {
          $content = file_get_contents($full_path);
          $build['#markup'] .= "  Namespace declaration: " . $this->extractNamespace($content) . "\n";
          $build['#markup'] .= "  Class/Interface name: " . $this->extractClassName($content) . "\n";
        }

        if (strpos($file, '.yml') !== false) {
          $build['#markup'] .= "  Content:\n" . file_get_contents($full_path) . "\n";
        }
      }
    }

    // Debug class loading
    $build['#markup'] .= "\n=== Class Loading ===\n";
    $classes_to_check = [
      'Drupal\\pi_comp\\Services\\PimmProjectManager',
      'Drupal\\pi_comp\\Services\\PimmProjectManagerInterface',
      'Drupal\\pi_comp\\Services\\Parser',
      'Drupal\\pi_comp\\Services\\ParserInterface',
    ];

    foreach ($classes_to_check as $class) {
      $build['#markup'] .= "$class: " . (class_exists($class) ? "LOADED" : "NOT FOUND") . "\n";
      if (class_exists($class)) {
        $reflection = new \ReflectionClass($class);
        $build['#markup'] .= "  File: " . $reflection->getFileName() . "\n";
        if ($reflection->isInterface()) {
          $build['#markup'] .= "  Type: Interface\n";
        } else {
          $build['#markup'] .= "  Type: Class\n";
          $build['#markup'] .= "  Implements: " . implode(', ', $reflection->getInterfaceNames()) . "\n";
        }
      }
    }

    // Debug service availability
    $build['#markup'] .= "\n=== Service Availability ===\n";
    $container = \Drupal::getContainer();

    $services_to_check = [
      'entity_type.manager',
      'database',
      'current_user',
      'pi_comp.parser',
      'pi_comp.project_manager',
    ];

    foreach ($services_to_check as $service_id) {
      $build['#markup'] .= "$service_id: " . ($container->has($service_id) ? "AVAILABLE" : "NOT FOUND") . "\n";
      try {
        $service = \Drupal::service($service_id);
        $build['#markup'] .= "  Successfully loaded\n";
        $build['#markup'] .= "  Class: " . get_class($service) . "\n";

        if (in_array($service_id, ['pi_comp.parser', 'pi_comp.project_manager'])) {
          $reflection = new \ReflectionClass($service);
          $build['#markup'] .= "  Defined in: " . $reflection->getFileName() . "\n";
          $build['#markup'] .= "  Implements: " . implode(', ', $reflection->getInterfaceNames()) . "\n";
        }
      }
      catch (\Exception $e) {
        $build['#markup'] .= "  Error loading: " . $e->getMessage() . "\n";
      }
    }

    // List all pi_comp services
    $build['#markup'] .= "\n=== All pi_comp Services ===\n";
    $all_services = $container->getServiceIds();
    foreach ($all_services as $service_id) {
      if (strpos($service_id, 'pi_comp') !== false) {
        $build['#markup'] .= $service_id . "\n";
      }
    }

    $build['#markup'] .= '</pre>';
    return $build;
  }

  protected function extractNamespace($content) {
    if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
      return $matches[1];
    }
    return 'Not found';
  }

  protected function extractClassName($content) {
    if (preg_match('/(class|interface)\s+(\w+)/', $content, $matches)) {
      return $matches[2];
    }
    return 'Not found';
  }
}
