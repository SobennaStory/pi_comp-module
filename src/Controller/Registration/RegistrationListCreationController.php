<?php

namespace Drupal\pi_comp\Controller\Registration;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Render\RendererInterface;

class RegistrationListCreationController extends ControllerBase {

  protected $formBuilder;
  protected $renderer;

  public function __construct(FormBuilderInterface $form_builder, RendererInterface $renderer) {
    $this->formBuilder = $form_builder;
    $this->renderer = $renderer;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder'),
      $container->get('renderer')
    );
  }

  public function build() {
    $form = $this->formBuilder->getForm('Drupal\pi_comp\Form\Registration\RegistrationListCreation');

    return [
      '#type' => 'markup',
      '#markup' => $this->renderer->render($form),
      '#attached' => [
        'library' => [
          'pi_comp/pi_comp_styles',
        ],
      ],
    ];
  }
}
