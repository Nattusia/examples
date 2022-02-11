<?php

namespace Drupal\calendar_hero_integration\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;
use Drupal\calendar_hero_integration\Common;

/**
 * Route subscriber.
 */
class DashboardRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = parent::getSubscribedEvents();
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -300];
    return $events;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    /** @var \Symfony\Component\Routing\Route $route */

    if ($route = $collection->get('envision_crm.coach_dashboard')) {
      $defaults = $route->getDefaults();
      $defaults['_controller'] =
      'Drupal\calendar_hero_integration\Controller\CalendarHeroIntegrationController::dashboardController';
      $route->setDefaults($defaults);
    }

  }

}
