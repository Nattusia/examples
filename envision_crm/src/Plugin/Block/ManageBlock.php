<?php
/**
 * @file
 * Contains \Drupal\envision_crm\Plugin\Block\SensorBlock.
 */

namespace Drupal\envision_crm\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;

/**
 * Provides an 'manage' block.
 *
 * @Block(
 *   id = "envision_crm_manage_block",
 *   admin_label = @Translation("Manage Clients"),
 *   category = @Translation("EGL")
 * )
 */
class ManageBlock extends BlockBase implements ContainerFactoryPluginInterface { 
  /**
   * @var AccountProxy
   */
  protected $currentUser;

  /**
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param Drupal\Core\Session\AccountProxyInterface $currentUser
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccountProxyInterface $currentUser) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentUser = $currentUser;
  }
  
  
  /**
   * {@inheritdoc}
   */
  public function build() {
    $links = [];
    
    $params['coach'] = $this->currentUser->id();

    //dashboard
    $url = Url::fromRoute('envision_crm.coach_dashboard', $params);
    $links[] = ['url' => $url, 'title' => 'Client Dashboard'];
    
    //scheduling
    $url = Url::fromRoute('calendar_hero_integration.coach_schedule', $params);
    $links[] = ['url' => $url, 'title' => 'Scheduling'];
    
    //clients
    $url = Url::fromRoute('envision_crm.my_clients', $params);
    $links[] = ['url' => $url, 'title' => 'My Clients'];
    
    $build = [
      '#theme' => 'envision_crm_manage_block',
      '#title' => 'Manage Clients',
      '#links' => $links,
      '#cache' => ['max-age' => 0],
    ];
    return $build;
  }
  
   /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   *
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user')
    );
  }
}