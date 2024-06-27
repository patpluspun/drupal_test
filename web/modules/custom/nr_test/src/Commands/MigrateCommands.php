<?php

namespace Drupal\nr_test\Commands;

use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Url;
use Drupal\nr_test\Migrate;
use Drush\Commands\DrushCommands;

/**
 * New Relic Test Migration commands for drush.
 */
class MigrateCommands extends DrushCommands {

  /**
   * The Messenger service.
   *
   * @var Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * Migration Manager service.
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
   * Migrates from a given endpoint.
   *
   * Could also handle local file migration with some work.
   *
   * @param string $endpoint
   *   The endpoint to migrate from.
   *
   * @command nr:migrate
   * @aliases nr-migrate
   */
  public function runMigration(string $endpoint) {
    // Quick and dirty url validation.
    $url = Url::fromUri($endpoint);

    $this->output()->writeLn('Migrating data from ' . $url->toString());

    try {
      $request = $this->migrationManager->httpClient->get($url->toString());
      if ($request->getStatusCode() == 200) {
        $this->output()->writeLn('Received 200 response from endpoint.');
        $json = $request->getBody()->getContents();
      }
    }
    catch (\Exception $e) {
      $this->output()->writeLn($e->getMessage());
    }

    if (!empty($json)) {
      $data = $this->migrationManager->processJson($json);
      $this->migrationManager->startMigration($data);
    }
  }

}
