<?php

declare(strict_types=1);

namespace Drupal\mof\Controller;

use Drupal\mof\ModelInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\Core\Access\AccessResult;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class ModelController extends ControllerBase {

  /** @var \Drupal\mof\ModelSerializer. */
  private $modelSerializer;

  /** @var \Drupal\mof\ModelEvaluatorInterface. */
  private $modelEvaluator;

  /** @var \Drupal\Core\Render\RendererInterfce. */
  private $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->modelSerializer = $container->get('model_serializer');
    $instance->modelEvaluator = $container->get('model_evaluator');
    $instance->renderer = $container->get('renderer');
    return $instance;
  }

  /**
   * Set the page title to the model name.
   */
  public function pageTitle(Request $request, ModelInterface $model): string|TranslatableMarkup {
    switch ($request->attributes->get('_route')) {
    case 'entity.model.badge':
      $subtitle = $this->t('Badges');
      break;

    case 'entity.model.admin_edit_form':
      $subtitle = $this->t('Admin');
      break;
    }

    $t_args = ['@model_name' => $model->label()];

    if (isset($subtitle)) {
      $t_args['@subtitle'] = $subtitle;
      return $this->t('@model_name: @subtitle', $t_args);
    }

    return $this->t('@model_name', $t_args);
  }

  /**
   * Display instructions/ markdown code for embedding badges.
   */
  public function badgePage(ModelInterface $model): array {
    $build = ['#markup' => $this->t('Use the following markdown to embed your model badges.')];
    $badges = $this->modelEvaluator->setModel($model)->generateBadge();

    for ($i = 1; $i <= 3; $i++) {
      $badge = Url::fromRoute('mof.model_badge', ['model' => $model->id(), 'class' => $i]);

      $build[$i] = [
        '#type' => 'container',
      ];

      $build[$i]['title'] = [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->modelEvaluator->getClassLabel($i),
      ];

      $build[$i]['badge'] = $badges[$i];

      $build[$i]['md'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => "![mof-class{$i}]({$badge->setAbsolute(TRUE)->toString()})",
        '#weight' => 10,
      ];

      $build[$i]['md']['copy'] = [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#attributes' => ['class' => ['btn-copy']],
        '#value' => '<i class="fas fa-copy icon"></i>',
      ];
    }

    $build['#attached']['library'][] = 'mof/model-badge';
    return $build;
  }

  /**
   * Return an SVG badge for specified model and class.
   */
  public function badge(ModelInterface $model, int $class): Response {
    $badges = $this->modelEvaluator->setModel($model)->generateBadge();
    $svg = (string)$this->renderer->render($badges[$class]);

    $response = new Response();
    $response->setContent($svg);
    $response->headers->set('Content-Length', (string)strlen($svg));
    $response->headers->set('Content-Type', 'image/svg+xml');

    return $response;
  }

  /**
   * Return a yaml representation of the model.
   */
  public function yaml(ModelInterface $model): Response {
    $response = new Response();

    try {
      $yaml = $this->modelSerializer->toYaml($model);
      $response->setContent($yaml);
      $response->headers->set('Content-Type', 'application/yaml');
      $response->headers->set('Content-Length', (string)strlen($yaml));
      $response->headers->set('Content-Disposition', 'attachment; filename="mof.yml"');
    }
    catch (\RuntimeException $e) {
      $response->setContent($e->getMessage());
      $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    return $response;
  }

  /**
   * Return a json file representation of the model.
   */
  public function json(ModelInterface $model): Response {
    $response = new Response();

    try {
      $json = $this->modelSerializer->toJson($model);
      $response->setContent($json);
      $response->headers->set('Content-Type', 'application/json');
      $response->headers->set('Content-Length', (string)strlen($json));
      $response->headers->set('Content-Disposition', 'attachment; filename="mof.json"');
    }
    catch (\RuntimeException $e) {
      $response->setContent($e->getMessage());
      $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    return $response;
  }

  /**
   * List model collection for admins.
   */
  public function collection(): array|RedirectResponse {
    return $this->entityTypeManager()->getHandler('model', 'admin_list_builder')->render();
  }

  /**
   * Approve a model.
   */
  public function setStatus(ModelInterface $model, string $status): RedirectResponse {
    $model->setApprover($this->currentUser())->setStatus($status)->save();
    return $this->redirect('entity.model.admin_collection');
  }

  /**
   * Check model pending status.
   * Access callback is used when generating badges or json.
   */
  public function pendingAccessCheck(ModelInterface $model): AccessResult {
    return AccessResult::allowedIf(!$model->isPending());
  }

}

