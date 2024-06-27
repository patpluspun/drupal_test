<?php

namespace Drupal\nr_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for migrated user display with companies.
 */
class UsersController extends ControllerBase {

  /**
   * The EntityTypeManager object.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $nodeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
          $container->get('entity_type.manager')
      );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->nodeManager = $entityTypeManager;
  }

  /**
   * Renders the page.
   */
  public function renderPage() {
    $users = $this->fetchUsers();

    $headers = [
      $this->t('Users'),
      $this->t('Companies'),
    ];
    $rows = [];
    foreach ($users as $user) {
      $rows[] = [
        $this->renderUser($user),
        $this->renderCompany($user->field_company->entity),
      ];
    }

    $link_text = $this->t('migration');
    $url = Url::fromRoute('nr_test.migration_upload_form');
    $link = Link::fromTextAndUrl($link_text, $url)->toString();

    $page = [
      '#type' => 'table',
      '#headers' => $headers,
      '#rows' => $rows,
      '#empty' => $this->t(
          'Please run the @migration first.', [
            '@migration' => $link,
          ]
      ),
      '#markup' => 'Page exists',
    ];

    return $page;
  }

  /**
   * Fetches user node entities for display.
   *
   * @return array
   *   An array of user node objects.
   */
  protected function fetchUsers() {
    $query = $this->nodeManager->getStorage('node');
    $users = $query->loadByProperties(
          [
            'type' => 'user',
            'status' => TRUE,
          ]
      );

    return $users;
  }

  /**
   * Helper to output a user row.
   *
   * @param \Drupal\node\Entity\Node $user
   *   The node object.
   *
   * @return string
   *   A terribly formatted blob of user data.
   */
  protected function renderUser(Node $user) {
    // dpm($user);
    $user_row =
        $this->t('Username:') . $user->label() . "\n\n" .
        $this->t('ID:') . $user->field_id->value . "\n\n" .
        $this->t('Name:') . $user->field_name->value . "\n\n" .
        $this->t('Phone:') . $user->field_phone->value . $user->field_ext->value . "\n\n" .
        $this->t('Email:') . $user->field_email->value . "\n\n" .
        $this->t('Website:') . $user->field_website->url . "\n\n" .
        $this->t('Address:') . $user->field_street->value . "\n\n" .
        $user->field_suite->value . "\n\n" .
        $user->field_city->value . "\n\n" .
        $user->field_zip->value . "\n\n" .
        $this->t('Latitude:') . $user->field_latitude->value . "\n\n" .
        $this->t('Longitude:') . $user->field_longitude->value . "\n\n";
    return $user_row;
  }

  /**
   * Helper to output a company row.
   *
   * @param \Drupal\node\Entity\Node $company
   *   The node object.
   *
   * @return string
   *   A terribly formatted blob of company data.
   */
  protected function renderCompany(Node $company) {
    // dpm($company);
    $company_row =
        $this->t('Name:') . $company->label() . "\n\n" .
        $this->t('Catchphrase:') . $company->field_catchphrase->value . "\n\n" .
        $this->t('BS:') . $company->field_jargon->value . "\n\n";
    return $company_row;
  }

}
