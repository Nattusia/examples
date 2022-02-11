<?php

namespace Drupal\calendar_hero_integration\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\coaching_package\Helper;

/**
 * Provides an example block.
 *
 * @Block(
 *   id = "calendar_hero_integration_reports",
 *   admin_label = @Translation("Calendar Hero Report Management"),
 *   category = @Translation("Calendar Hero Integration")
 * )
 */
class CalendarHeroReportsBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $links = $this->getLinks();

     $build = [
      '#theme' => 'calendar_hero_integration_reports',
      '#title' => 'Reports Management',
      '#links' => $links,
      //'#cache' => ['max-age' => 0],
    ];

    return $build;
  }

  public function getLinks() {
    $routes = [
      'calendar_hero_integration.calendar_hero_import',
      'calendar_hero_integration.calendar_hero_report'
    ];

    $links = [];

    foreach ($routes as $route) {
      $url = Url::fromRoute($route);
      $router = \Drupal::service('router.no_access_checks');
      //ksm(get_class_methods($router));
      $collection = $router->getRouteCollection();
      //ksm(get_class_methods($collection->get($route)));
      //ksm($collection->get($route)->getMethods());
      $defaults = $collection->get($route)->getDefaults();
      $link_text = $defaults['_title'];
      $link = Link::fromTextAndUrl($link_text, $url)->toRenderable();

      $links[] = $link;
    }


    return $links;
  }

}
