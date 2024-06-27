<?php

namespace Drupal\nr_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\nr_test\Migrate;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * New Relic Test Migration upload form.
 */
class MigrationUploadForm extends FormBase {

  /**
   * The Messenger service.
   *
   * @var Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * The migration manager service.
   *
   * @var Drupal\nr_test\Migrate
   */
  protected $migrationManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
          $container->get('messenger'),
          $container->get('nr_test.migrate')
      );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(Messenger $messenger, Migrate $migrationManager) {
    $this->messenger = $messenger;
    $this->migrationManager = $migrationManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'migration_upload_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#tree'] = TRUE;

    $form['migration'] = [
      '#type' => 'fieldset',
    ];
    $form['migration']['endpoint'] = [
      '#type' => 'url',
      '#title' => $this->t('Type in an endpoint to migrate from'),
      '#attributes' => [
        'placeholder' => 'https://jsonplaceholder.typicode.com/users',
      ],
      '#weight' => '0',
      '#states' => [
        'enabled' => [
          ':input[data-drupal-selector="edit-migration-upload"]' => [
            'value' => '',
          ],
        ],
      ],
    ];
    $form['migration']['upload'] = [
      '#type' => 'file',
      '#multiple' => FALSE,
      '#description' => $this->t('Allowed extensions: json'),
      '#upload_validators' => [
        'file_validate_extensions' => ['json'],
      ],
      '#title' => $this->t('Select a .json file for migration'),
      '#weight' => '1',
      '#states' => [
        'enabled' => [
          ':input[data-drupal-selector="edit-migration-endpoint"]' => [
            'value' => '',
          ],
        ],
      ],
    ];
    $form['migration']['actions'] = [
      '#type' => 'container',
      '#weight' => '2',
    ];
    $form['migration']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Migrate'),
      '#weight' => '2',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    if (!empty($values['migration']['upload'])) {
      $uploaded_file = $this->getRequest()->files->get('files', []);
      $file_path = $uploaded_file['migration']->getRealPath();
      $json = file_get_contents($file_path);
    }
    elseif (!empty($values['migration']['endpoint'])) {
      $endpoint = $values['migration']['endpoint'];
      try {
        $request = $this->migrationManager->httpClient->get($endpoint);
        if ($request->getStatusCode() == 200) {
          $json = $request->getBody()->getContents();
        }
      }
      catch (\Exception $e) {
        $this->messenger->addWarning($e->getMessage());
      }
    }

    if (!empty($json)) {
      $data = $this->migrationManager->processJson($json);
      $this->migrationManager->startMigration($data);
    }
  }

}
