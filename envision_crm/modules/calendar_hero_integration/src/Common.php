<?php
namespace Drupal\calendar_hero_integration;

use Drupal\Core\Url;
use Drupal\Core\Datetime\DrupalDateTime;

class Common {

  public function getRequestUrl($configIndex = '') {
    $config = \Drupal::config('custom_rest_login.settings');
    $provider_url = $config->get('provider-url');

    $api_part = ($configIndex) ? $config->get($configIndex) : '';

    return $provider_url . $api_part;
  }

  public function getAuthorizeUrl() {

  }

  /**
   * Generic request function.
   *
   * @param string $method
   *   The REST API method.
   * @param string $url
   *   Request url.
   * @param string $body.
   *   Json encoded array.
   *
   * @return object
   *   The response object.
   */
  public function basicRequest($method, $url, $body = '', $authToken = '') {

    $client = \Drupal::httpClient();
    $options = [
      'verify' => FALSE,
      'headers' => [
        //'Content-Type' => 'application/x-www-form-urlencoded',
        //'Accept' => 'application/json',
        'Authorization' => $authToken, //'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpZCI6IjYxMDFiYTMyYjg0NWUxMDAyMGQzMzViMSIsIm5hbWUiOiLQoNGD0L3QsCDQk9GD0LTQtNC-0LnRgtC40YAiLCJlbWFpbCI6InJ1bmFsZXR0ZXJzQGdtYWlsLmNvbSIsImFkbWluIjpmYWxzZSwiaWF0IjoxNjI3ODk4ODk3fQ.t9mK8f5AybxRXyoNaZ6-ucSxDHlrhYgjI_20oADcEYI',
      ],
      'http_errors' => FALSE,
    ];

    if ($body) {
      $options['body'] = json_encode($body);

    }
    // if ($authToken) {
    //   $options['headers']['Authorization'] = 'Bearer ' . $authToken;
    // }
    $response = $client->request($method, $url, $options);
    return $response;
  }

  public function contactExists($mail, $coach) {
    $url = 'https://api.calendarhero.com/contact?search=' . $mail;
    $token = $this->getHeroToken($coach);
    $response = $this->basicRequest('GET', $url, '', $token);
    $resp = json_decode($response->getBody()->getContents());

    return !empty($resp);
  }

  public function createContact($user, $coach) {
    $contact = [
      'name' => $user->getDisplayName(),
      'email' => [$user->getEmail()],
    ];

    $url = 'https://api.calendarhero.com/contact';
    $token = $this->getHeroToken($coach);
    $response = $this->basicRequest('POST', $url, $contact, $token);
    $resp = $response->getBody()->getContents();

    return $resp;
  }

  public function FloodIsAllowed() {
    $flood = \Drupal::flood();
    $config_factory = \Drupal::config('user.flood');
    $client_ip = \Drupal::request()->getClientIp();
    $allowed = TRUE;
    if (!$flood->isAllowed(
      'user.failed_login_ip',
      $config_factory->get('ip_limit'),
      $config_factory->get('ip_window'),
      $client_ip)) {

      $allowed = FALSE;
    }

    $flood->register('user.failed_login_ip', $config_factory->get('ip_window'), $client_ip);

    return $allowed;
  }

  function clearFlood() {
    $flood = \Drupal::Flood();
    $client_ip = \Drupal::request()->getClientIp();
    $flood->clear('user.failed_login_ip', $client_ip);
  }

  public function getHeroToken($user) {
    $token = FALSE;
    if ($user->hasField('field_calendar_hero_token')) {
      $token_value = $user->field_calendar_hero_token->getValue();
      if (($token_value) && (!empty($token_value[0]['value']))) {
        $token = $token_value[0]['value'];
      }
    }

    return $token;
  }

  public function getHeroUser($token) {
    $url = 'https://api.calendarhero.com/user';
    $response = $this->basicRequest('GET', $url, '', $token);
    if ($response->getStatusCode() != 200) {
      return $response->getReasonPhrase();
    }
    else {
      return json_decode($response->getBody()->getContents());
    }
  }

  public function getMeetingTypes($coach, $type = '') {

    $token = $this->getHeroToken($coach);

    $meetings_url = 'https://api.calendarhero.com/user/meeting';
    $response = $this->basicRequest('GET', $meetings_url, '', $token);
    $meetings = json_decode($response->getBody()->getContents());

    if (!empty($type)) {
      $meetings = $meetings->{$type};
    }

    return $meetings;
  }

  public function createMeeting($coach, $body) {
    $token = $this->getHeroToken($coach);
    $url = 'https://api.calendarhero.com/meeting/tasks';
    $response = $this->basicRequest('POST', $url, $body, $token);

    if ($response->getStatusCode() == 200) {
      $resp = json_decode($response->getBody()->getContents());
    }
    else {
      $resp = $response->getReasonPhrase();
    }

    return $resp;
  }

  public function cancelMeeting($coach, $meetingId) {
    $token = $this->getHeroToken($coach);
    $url = 'https://api.calendarhero.com/meeting/tasks/' . $meetingId;
    $response = $this->basicRequest('DELETE', $url, '', $token);

    if ($response->getStatusCode() == 204) {
      //$resp = json_decode($response->getBody()->getContents());
      $resp = TRUE;
    }
    else {
      $resp = $response->getReasonPhrase();
    }

    return $resp;
  }

  public function registerWebhook($webhook, $coach, $token, $delete = FALSE) {
    $url = 'https://api.calendarhero.com/webhook/' . $webhook;
    $params = [
      'webhook' => $webhook,
      'coach' => $coach,
    ];
    $options['absolute'] = TRUE;
    $webhook_url = Url::fromRoute('calendar_hero_integration.webhook_catch', $params, $options)->toString();

    $body = [
      'hookUrl'=> $webhook_url,
    ];

    $body = $delete ? '' : $body;
    $type = $delete ? 'DELETE' : 'POST';

    $response = $this->basicRequest($type, $url, $body, $token);

    return $response;
  }

  public function getMeetings($token, $state = 'upcoming') {
    $url = 'https://api.calendarhero.com/meeting/tasks?state=' . $state;
    $response = $this->basicRequest('GET', $url, '', $token);

    if ($response->getStatusCode() == 200) {
      $resp = json_decode($response->getBody()->getContents());
    }
    else {
      $resp = $response->getReasonPhrase();
    }

    return $resp;
  }


  public function getMeetingsbyDate($token) {
    $date = new \Datetime('+ 1 year');
    $searchEnd = $date->format('Y-m-d');
    $url = 'https://api.calendarhero.com/meeting?start=2021-06-01&end='.$searchEnd;
    $response = $this->basicRequest('GET', $url, '', $token);

    if ($response->getStatusCode() == 200) {
      $resp = json_decode($response->getBody()->getContents());
    }
    else {
      $resp = $response->getReasonPhrase();
    }

    return $resp;
  }

  /**
   * Checks if url meets conditions to show calendar on the page.
   *
   * @return array
   *   The render array with theme if conditions pass or with message if they do not.
   */
  public function checkCalendarPage($query) {

    $build = [
      '#markup' => '<div>' . t('Something went wrong. Please contact the site administrator.') . '</div>',
    ];
    $current_user = \Drupal::currentUser();
    if (!isset($query['token'])) {
      $build = [
        '#markup' => '<div>' .
        t('There is not appropriate token in your link. Please use the link which sent to you by mail, or contact your coach.')
        . '</div>',
      ];
    }
    else {
      if (!$uids = calendar_hero_integration_get_uid_by_token($query['token'])) {
        $build = [
          '#markup' => '<div>' .
            t('The link token is wrong or has been expired. Please contact your coach to get the appropriate link.')
        . '</div>',
        ];
      }
      else {
        $guest = \Drupal::entityTypeManager()->getStorage('user')->load($uids['guest_uid']);
        if (!$guest) {
          $build = [
            '#markup' => '<div>' . t('Probably you are blocked. Please contact the site administrator.') . '</div>'
          ];
        }
        else {

          $build = $uids;
          //$calendar_page = $this->getUserCalendarPage($uids['guest_uid'], $uids['host_uid'], $template);
          //$build = !empty($calendar_page) ? $calendar_page : $build;
        }
      }
    }

    return $build;
  }

  public function getEventToReschedule($query) {
    $event = NULL;
    if (isset($query['reschedule'])) {
      if ($event = \Drupal::entityTypeManager()->getStorage('opigno_calendar_event')->load($query['reschedule'])) {
       $event = $event;
      }
    }

    return $event;
  }

  public function checkConnection($user) {
    $build['#markup'] = '<div>We can\'t establish the Calendar Hero connection.
    <br /> Please, set the appropriate Calendar Hero token in your coach dashboard page.</div>';

    if ($token = $this->getHeroToken($user)) {
      if ($user->hasField('field_calendar_hero_id')) {
        //$id_value = $user->field_calendar_hero_id->getValue();
        //if (($id_value) && (!empty($id_value[0]['value']))) {
          $hero_user = $this->getHeroUser($token);
          if (!is_object($hero_user)) {
            $build['#markup'] = '<div>We can\'t establish the Calendar Hero connection.<br />
            The server response is "' . $hero_user . '".</div>';
          }
          else {
          //  if ($hero_user->id == $id_value[0]['value']) {
              $build = TRUE;
          //  }
          }
        //}

      }
    }

    return $build;
  }
}
