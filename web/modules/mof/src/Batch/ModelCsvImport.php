<?php declare(strict_types=1);

namespace Drupal\mof\Batch;

use Drupal\mof\Entity\Model;
use Drupal\mof\ModelInterface;
use Drupal\Core\Session\AccountInterface;

class ModelCsvImport {

  /**
   * Called when the batch job has finished.
   *
   * @param bool $success
   *   A boolean indicating whether batch was successful.
   * @param array $results
   *   An array of results collected by the batch process.
   * @param array $operations
   *   If $success is false, contains the operations that remain unprocessed.
   */
  public static function finished($success, $results, $operations) {
    if ($success) {
      if (isset($results['imported']) && $results['imported'] > 0) {
        \Drupal::messenger()->addMessage(t('Imported @num models', ['@num' => $results['imported']]));
      }
      if (isset($results['updated']) && $results['updated'] > 0) {
        \Drupal::messenger()->addMessage(t('Updated @num models', ['@num' => $results['updated']]));
      }
    }
  }

  /**
   * Import model from CSV data.
   *
   * @param array $data
   *   Model data as defined in the CSV file.
   * @param array &$context
   *   A reference to the batch context array.
   */
  public static function import(array $data, array &$context) {
    $context['message'] = t('Importing model %name', ['%name' => $data['Name']]);

    if (!isset($context['results']['imported'])) {
      $context['results']['imported'] = 0;
    }

    $model = Model::create();
    if (static::setModelValues($model, $data) === SAVED_NEW) {
      $context['results']['imported']++;
    }
  }

  /**
   * Update existing model from CSV data.
   *
   * @param \Drupal\mof\ModelInterface $model
   *   The model entity we are updating.
   * @param array $data
   *   Model data as defined in the CSV file.
   * @param array &$context
   *   A reference to the batch context array.
   */
  public static function update(ModelInterface $model, array $data, array &$context) {
    $context['message'] = t('Updating model %name', ['%name' => $data['Name']]);

    if (!isset($context['results']['updated'])) {
      $context['results']['updated'] = 0;
    }

    if (static::setModelValues($model, $data) === SAVED_UPDATED) {
      $context['results']['updated']++;
    }
  }

  /**
   * Set model entity values.
   *
   * @param \Drupal\mof\ModelInterface $model
   *   The model entity we are setting values on.
   * @param array $data
   *   Model data as defined in the CSV file.
   * @return int
   *   An integer indicating SAVED_NEW or SAVED_UPDATED.
   */
  public static function setModelValues(ModelInterface $model, array $data): int {
    $licenses = static::processLicenses($data);

    $model
      ->setLabel($data['Name'])
      ->setDescription($data['Description'])
      ->setVersion($data['Version/Parameters'])
      ->setOrganization($data['Organization'])
      ->setType($data['Model Type'])
      ->setArchitecture($data['Architecture'])
      ->setTreatment($data['Training Treatment'])
      ->setOrigin($data['Base Model'])
      ->setGitHub(static::getPathFromUrl($data['Github Repo URL']))
      ->setHuggingFace(static::getPathFromUrl($data['HuggingFace Model URL']))
      ->setApprover(static::getApprover($data['Researcher']))
      ->setLicenses($licenses)
      ->setCompletedComponents(array_keys($licenses))
      ->setStatus('approved');

    return $model->save();
  }

  /**
   * Process license data.
   *
   * @param array $data
   *   License data from CSV file.
   * @return array
   *   An array suitable for model entity license data.
   */
  public static function processLicenses(array $data): array {
    return [
      9 => [
        'license' => $data['Model Architecture'] ?: 'Pending evaluation',
        'license_path' => '',
        'component_path' => '',
      ],
      16 => [
        'license' => $data['Data Preprocessing Code'] ?: 'Pending evaluation',
        'license_path' => '',
        'component_path' => '',
      ],
      7 => [
        'license' => $data['Training Code'] ?: 'Pending evaluation',
        'license_path' => '',
        'component_path' => '',
      ],
      8 => [
        'license' => $data['Inference Code'] ?: 'Pending evaluation',
        'license_path' => '',
        'component_path' => '',
      ],
      18 => [
        'license' => $data['Evaluation Code'] ?: 'Pending evaluation',
        'license_path' => '',
        'component_path' => '',
      ],
      22 => [
        'license' => $data['Supporting Libraries and Tools'] ?: 'Pending evaluation',
        'license_path' => '',
        'component_path' => '',
      ],
      15 => [
        'license' => $data['Datasets'] ?: 'Pending evaluation',
        'license_path' => '',
        'component_path' => '',
      ],
      10 => [
        'license' => $data['Model Parameters (Final)'] ?: 'Pending evaluation',
        'license_path' => '',
        'component_path' => '',
      ],
      17 => [
        'license' => $data['Model Metadata'] ?: 'Pending evaluation',
        'license_path' => '',
        'component_path' => '',
      ],
      24 => [
        'license' => $data['Model Parameters (Intermediate)'] ?: 'Pending evaluation',
        'license_path' => '',
        'component_path' => '',
      ],
      19 => [
        'license' => $data['Evaluation Data'] ?: 'Pending evaluation',
        'license_path' => '',
        'component_path' => '',
      ],
      20 => [
        'license' => $data['Sample Model Outputs'] ?: 'Pending evaluation',
        'license_path' => '',
        'component_path' => '',
      ],
      12 => [
        'license' => $data['Evaluation Results'] ?: 'Pending evaluation',
        'license_path' => '',
        'component_path' => '',
      ],
      13 => [
        'license' => $data['Model Card'] ?: 'Pending evaluation',
        'license_path' => '',
        'component_path' => '',
      ],
      14 => [
        'license' => $data['Data Card'] ?: 'Pending evaluation',
        'license_path' => '',
        'component_path' => '',
      ],
      11 => [
        'license' => $data['Technical Report'] ?: 'Pending evaluation',
        'license_path' => '',
        'component_path' => '',
      ],
      21 => [
        'license' => $data['Research Paper'] ?: 'Pending evaluation',
        'license_path' => '',
        'component_path' => '',
      ],
    ];
  }

  /**
   * Get the path from a URL string.
   *
   * @param string $url
   *   A string representing a full URL.
   * @return string
   *   The path portion of the URL.
   */
  public static function getPathFromUrl(string $url): string {
    $parsed = parse_url($url);
    return isset($parsed['path']) ? ltrim($parsed['path'], '/') : '';
  }

  /**
   * Create or load a Drupal user account.
   *
   * @param string $researcher
   *   A username used for user-lookup.
   *   An account is created if the username does not exist.
   * @return \Drupal\Core\Session\AccountInterface
   *   A Drupal user account entity.
   */
  public static function getApprover(string $researcher): AccountInterface {
    $username = strtolower(str_replace(' ', '.', $researcher));
    $user_storage = \Drupal::entityTypeManager()->getStorage('user');
    $user = $user_storage->loadByProperties(['name' => $username]);

    if (!empty($user)) {
      return reset($user);
    }

    // Generate new user with random password.
    $user = $user_storage->create([
      'name' => $username,
      'pass' => \Drupal::service('password')->hash(random_bytes(16)),
      'mail' => '',
    ]);

    $user->save();
    return $user;
  }

}

