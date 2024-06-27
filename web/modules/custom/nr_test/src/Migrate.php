<?php

namespace Drupal\nr_test;

use Drupal\Component\Serialization\Json;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationManager;
use Drupal\node\Entity\Node;
use GuzzleHttp\ClientInterface;

/**
 * New Relic Test Migration service.
 */
class Migrate {

  use DependencySerializationTrait;
  use StringTranslationTrait;

  /**
   * The EntityTypeManager object.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $nodeManager;

  /**
   * The StringTranslation trait.
   *
   * @var \Drupal\Core\StringTranslation\StringTranslationTrait
   */
  protected $stringTranslation;

  /**
   * The HTTP Client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  public $httpClient;

  /**
   * The Messenger service.
   *
   * @var Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
          $container->get('entity_type.manager'),
          $container->get('string_translation'),
          $container->get('http_client'),
          $container->get('messenger')
      );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, TranslationManager $stringTranslation, ClientInterface $httpClient, Messenger $messenger) {
    $this->nodeManager = $entityTypeManager;
    $this->stringTranslation = $stringTranslation;
    $this->httpClient = $httpClient;
    $this->messenger = $messenger;
  }

  /**
   * Initiates migration via batch process.
   *
   * @param array $data
   *   The parsed json as a php array.
   */
  public function startMigration($data) {
    $batch = [
      'title' => $this->t('Processing json data'),
      'operations' => [
      [
        [$this, 'processUsers'],
        [
          $data,
        ],
      ],
      ],
      'finished' => [$this, 'processUsersFinished'],
    ];
    batch_set($batch);

    if (PHP_SAPI === 'cli') {
      drush_backend_batch_process();
    }
  }

  /**
   * Processes a json file and inserts/updates users.
   *
   * @param string $file
   *   The json file.
   *
   * @return array
   *   A structured array of user and company data.
   */
  public function processJson(string $file) {
    $json = Json::decode($file);
    if (json_last_error() === JSON_ERROR_NONE) {
      return $json;
    }
  }

  /**
   * Batch processes the uploaded json data into user accounts.
   *
   * @param array $data
   *   A structured array of user data.
   * @param array $context
   *   The batch context array.
   */
  public function processUsers($data, &$context) {
    if (empty($context['sandbox'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['offset'] = 0;
      $context['sandbox']['created'] = 0;
      $context['sandbox']['updated'] = 0;
      $context['sandbox']['max'] = count($data);
    }

    $limit = 10;
    $slice = array_slice($data, $context['sandbox']['offset'], $limit);

    foreach ($slice as $userdata) {
      // Handle the company first.
      $company = $this->processCompany($userdata['company']);
      $phone = $this->processPhone($userdata['phone']);

      $user = Node::create(
            [
              'type' => 'user',
              'title' => $userdata['username'],
              'status' => 1,
            ]
        );
      $user->set('field_id', $userdata['id']);
      $user->set('field_name', $userdata['name']);
      $user->set('field_email', $userdata['email']);
      $user->set('field_website', 'https://' . $userdata['website']);
      $user->set('field_company', $company->id());
      $user->set('field_phone', $phone['number']);
      $user->set('field_ext', $phone['ext']);
      $user->set('field_street', $userdata['address']['street']);
      $user->set('field_suite', $userdata['address']['suite']);
      $user->set('field_city', $userdata['address']['city']);
      $user->set('field_zip', $userdata['address']['zipcode']);
      $user->set('field_latitude', $userdata['address']['geo']['lat']);
      $user->set('field_longitude', $userdata['address']['geo']['lng']);
      $user->save();
      $context['sandbox']['created']++;
      $op = 'Created';

      // Update results and sandbox.
      $context['results'][] = $op . ' user: ' . $userdata['name'];
      $context['sandbox']['progress']++;
    }

    // Update offset for next batch.
    $context['sandbox']['offset'] += $limit;

    // Check if we're finished.
    if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
      $max = $context['sandbox']['max'];
      $progress = $context['sandbox']['progress'];
      $context['finished'] = $progress / $max;
    }
  }

  /**
   * Notifies that processing is finished and displays results.
   *
   * @param bool $success
   *   Completed successfully or not.
   * @param array $results
   *   The results of the operation.
   * @param array $operations
   *   The unprocessed operations.
   * @param string $elapsed
   *   Final processing time.
   */
  public function processUsersFinished($success, $results, $operations, $elapsed) {
    if ($success) {
      $message = $this
        ->formatPlural(count($results), 'One user processed.', '@count users processed.');
    }
    else {
      $message = $this->t('Something went wrong.');
    }
    $this->messenger->addStatus($message);
  }

  /**
   * Finds or creates a company node to reference to the user.
   *
   * @param array $companydata
   *   An array of data about the company.
   *
   * @return \Drupal\node\Entity\Node
   *   The resulting node object.
   */
  protected function processCompany($companydata) {
    // This isn't necessary for this test, but in a real world migration it can
    // be important to check for existing entities so as to not create dupes.
    $company = $this->nodeManager->getStorage('node')
      ->loadByProperties(
              [
                'type' => 'company',
                'title' => $companydata['name'],
              ]
          );

    if (!empty($company)) {
      return reset($company);
    }
    else {
      $company = Node::create(
            [
              'type' => 'company',
              'title' => $companydata['name'],
              'status' => 1,
            ]
        );
      $company->set('field_catchphrase', $companydata['catchPhrase'])
        ->set('field_jargon', $companydata['bs'])
        ->save();
    }

    return $company;
  }

  /**
   * Parses and normalizes phone number.
   *
   * @param string $phonedata
   *   The string containing the phone, country code, and extension.
   *
   * @return array
   *   An array with the phone number normalized and the extension.
   */
  protected function processPhone($phonedata) {
    $phone = [];
    // First let's separate the extension, if any.
    if (str_contains($phonedata, 'x')) {
      $data = explode('x', $phonedata);
      $phone['number'] = $data[0];
      $phone['ext'] = 'x' . $data[1];
    }
    else {
      $phone['number'] = $phonedata;
      $phone['ext'] = '';
    }
    $phone['number'] = str_replace(['(', ')'], ' ', $phone['number']);
    $phone['number'] = trim($phone['number']);
    $phone['number'] = str_replace([' ', '  ', '.'], '-', $phone['number']);

    // Add the American country code. International numbers would be handled
    // differently of course.
    if (!str_starts_with($phone['number'], '1-')) {
      $phone['number'] = '1-' . $phone['number'];
    }

    return $phone;
  }

}
