<?php declare(strict_types=1);

namespace Drupal\mof\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;

final class ModelSubmitForm extends ModelForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form += parent::form($form, $form_state);

    // @todo use _title_callback on route to set this in ModelController::pageTitle().
    if ($this->entity->isNew()) {
      $form['#title'] = $this->t('Submit model');
    }
    else {
      $form['#title'] = $this->t('@model: Edit', ['@model' => $this->entity->label()]);
    }

    $form['#attached']['library'][] = 'mof/model-submit';
    $form['#attributes']['novalidate'] = 'novalidate';

    // Only admins can approve models.
    $form['status']['#access'] = $this->currentUser()->hasPermission('administer model');

    $form['repository']['widget'][0]['value']['#ajax'] = [
      'callback' => [$this, 'populateModelDetails'],
      'event' => 'keyup',
      'wrapper' => 'details-wrap',
      'progress' => [
        'type' => 'throbber',
        'message' => $this->t('Loading repository...'),
      ],
    ];

    $model_details = [
      'label',
      'organization',
      'description',
      'version',
      'type',
      'architecture',
      'treatment',
      'origin',
      'revision_information',
      'huggingface',
    ];

    $form['details'] = [
      '#type' => 'details',
      '#title' => $this->t('Model details'),
      '#open' => FALSE,
      '#weight' => -90,
      '#prefix' => '<div id="details-wrap">',
      '#suffix' => '</div>',
    ];

    // Move entity defined fields into a details element.
    foreach ($model_details as $field) {
      $form['details'][$field] = $form[$field];
      unset($form[$field]);
    }

    // Prepare html5 datalist.
    $form['details']['datalist'] = [
      '#type' => 'html_tag',
      '#tag' => 'datalist',
      '#attributes' => ['id' => 'git-tree'],
      'tree' => [],
    ];

    // Populate datalist if we're coming in with a default repo selected.
    if (!$form_state->isRebuilding() && !empty($form['repository']['widget']['#default_value'])) {
      $repo_name = $form['repository']['widget']['#default_value'][0];

      if (($repo = $form_state->get($repo_name)) === NULL) {
        $repo = $this->github->getRepo($repo_name);
        $form_state->set($repo_name, $repo);
      }

      $form['details']['datalist']['tree'] = $this->getRepoTree($repo->full_name, $repo->default_branch);
    }

    // Open when ajax rebuilds the form.
    if ($form_state->isRebuilding()) {
      $form['details']['#open'] = FALSE;
    }

    // Add fields to capture license and component paths.
    $license_data = $this->entity->getLicenses();
    foreach (['code', 'data', 'document'] as $group) {
      foreach ($form[$group]['components'] as $id => $component) {
        $form[$group]['components'][$id]['details'] = [
          'license_path' => [
            '#type' => 'textfield',
            '#title' => $this->t('License path'),
            '#default_value' => $license_data[$id]['license_path'] ?? '',
            '#parents' => ['components', $id, 'license_path'],
            '#wrapper_attributes' => [
              'class' => ['license-path-wrapper'],
            ],
            '#attributes' => [
              'list' => 'git-tree',
              'class' => ['license-path'],
              'autocomplete' => 'off',
            ],
          ],
          'component_path' => [
            '#type' => 'textfield',
            '#title' => $this->t('Component path'),
            '#default_value' => $license_data[$id]['component_path'] ?? '',
            '#parents' => ['components', $id, 'component_path'],
            '#wrapper_attributes' => [
              'class' => ['component-path-wrapper'],
            ],
            '#attributes' => [
              'list' => 'git-tree',
              'class' => ['component-path'],
              'autocomplete' => 'off',
            ],
          ],
          '#type' => 'container',
          '#attributes' => [
            'class' => ['license-details'],
          ],
          '#states' => [
            'invisible' => [
              ':input[name="components['.$id.'][license]"]' => [
                ['empty' => TRUE], 'or', ['value' => 'Component not included'], 'or', ['value' => 'License not specified']
              ],
            ],
          ],
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    if (preg_match('/\s/', $form_state->getValue('label')[0]['value'])) {
      $form_state->setErrorByName('label', $this->t('Model name cannot have spaces.'));
    }

    $extra = array_column($this->licenseHandler->getExtraOptions(), 'licenseId');

    // Re-populate git repo tree datalist.
    // Only if it's a github repository.
    $repo_url ??= $form_state->getValue('repository')[0]['value'];
    if ($repo_url !== NULL && ($path = $this->isGitHubRepository($repo_url)) !== NULL) {

      if ($form_state->get($path) === NULL) {
        $repo = $this->github->getRepo($path);
        $form_state->set($path, $repo);
      }
      else {
        $repo = $form_state->get($path);
      }

      $form['details']['datalist']['tree'] += $this->getRepoTree($repo->full_name, $repo->default_branch);
    }
  }

  /**
   * Determine if $url is a URL to a github repository, and if it is
   * return the path portion of the $url. Otherwise return NULL.
   *
   * @param string $url
   *
   * @return string The path or NULL if not a github URL.
   */
  protected function isGitHubRepository(string $url): ?string {
    $url = parse_url($url);
    return $url !== false && isset($url['host']) && $url['host'] === 'github.com' ? $url['path'] : NULL;
  }

  /**
   * Ajax callback. Returns populated model details.
   */
  public function populateModelDetails(array &$form, FormStateInterface $form_state): array {
    $repo_url ??= $form_state->getValue('repository')[0]['value'];

    if ($repo_url !== NULL && ($path = $this->isGitHubRepository($repo_url)) !== NULL) {

      if ($form_state->get($path) === NULL) {
        $repo = $this->github->getRepo($path);
        $form_state->set($path, $repo);
      }
      else {
        $repo = $form_state->get($path);
      }

      $form['details']['label']['widget'][0]['value']['#value'] = $repo->name;
      $form['details']['description']['widget'][0]['value']['#value'] = $repo->description;
      $form['details']['datalist']['tree'] = $this->getRepoTree($repo->full_name, $repo->default_branch);
    }

    return $form['details'];
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Submit');
    return $actions;
  }

  /**
   * Build a render array for datalist options.
   */
  private function getRepoTree(string $repo, string $branch): array {
    // May be called more than once during form processing.
    // This will reduce github API hits.
    static $tree = [];

    if (!isset($tree[$repo])) {
      foreach ($this
        ->github
        ->getTree($repo, $branch)
        ->tree as $item) {

        $tree[$repo][] = [
          '#type' => 'html_tag',
          '#tag' => 'option',
          '#attributes' => ['value' => $item->path],
          '#parents' => [],
        ];
      }
    }

    return $tree[$repo];
  }

}
