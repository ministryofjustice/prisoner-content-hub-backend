<?php

namespace Drupal\hmpps_auth\Plugin\OpenIDConnectClient;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\openid_connect\Plugin\OpenIDConnectClientBase;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Generic OAuth 2.0 OpenID Connect client.
 *
 * Used primarily to login to Drupal sites powered by oauth2_server or PHP
 * sites powered by oauth2-server-php.
 *
 * @OpenIDConnectClient(
 *   id = "hmpps_auth",
 *   label = @Translation("HMPPS Auth")
 * )
 */
class OpenIDConnectHMPPSAuthClient extends OpenIDConnectClientBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
        'authorization_endpoint' => 'https://sign-in-dev.hmpps.service.justice.gov.uk/auth/oauth/authorize',
        'token_endpoint' => 'https://sign-in-dev.hmpps.service.justice.gov.uk/auth/oauth/token',
        'userinfo_endpoint' => 'https://sign-in-dev.hmpps.service.justice.gov.uk/auth/oauth/userinfo',
        'end_session_endpoint' => '',
        'scopes' => ['openid', 'email'],
      ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['authorization_endpoint'] = [
      '#title' => $this->t('Authorization endpoint'),
      '#type' => 'url',
      '#default_value' => $this->configuration['authorization_endpoint'],
    ];
    $form['token_endpoint'] = [
      '#title' => $this->t('Token endpoint'),
      '#type' => 'url',
      '#default_value' => $this->configuration['token_endpoint'],
    ];
    $form['userinfo_endpoint'] = [
      '#title' => $this->t('UserInfo endpoint'),
      '#type' => 'url',
      '#default_value' => $this->configuration['userinfo_endpoint'],
    ];
    $form['end_session_endpoint'] = [
      '#title' => $this->t('End Session endpoint'),
      '#type' => 'url',
      '#default_value' => $this->configuration['end_session_endpoint'],
    ];

    $form['scopes'] = [
      '#title' => $this->t('Scopes'),
      '#type' => 'textfield',
      '#description' => $this->t('Custom scopes, separated by spaces, for example: openid email'),
      '#default_value' => implode(' ', $this->configuration['scopes']),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $configuration = $form_state->getValues();
    if ($configuration['use_well_known']) {
      $endpoints = $this->autoDiscoverEndpoints($configuration['issuer_url']);
      $this->setConfiguration([
        'authorization_endpoint' => $endpoints['authorization_endpoint'],
        'token_endpoint' => $endpoints['token_endpoint'],
        'userinfo_endpoint' => $endpoints['userinfo_endpoint'],
      ]);
    }

    if (!empty($configuration['scopes'])) {
      $this->setConfiguration(['scopes' => explode(' ', $configuration['scopes'])]);
    }

    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getClientScopes(): ?array {
    return $this->configuration['scopes'];
  }

  /**
   * {@inheritdoc}
   */
  public function getEndpoints() : array {
    return [
      'authorization' => $this->configuration['authorization_endpoint'],
      'token' => $this->configuration['token_endpoint'],
      'userinfo' => $this->configuration['userinfo_endpoint'],
      'end_session' => $this->configuration['end_session_endpoint'],
    ];
  }

  /**
   * Override this method so we can alter the url.
   */
  public function authorize(string $scope = 'openid'): Response {
    $redirect_uri = $this->getRedirectUrl()->toString(TRUE);
    $url_options = $this->getUrlOptions($scope, $redirect_uri);

    $endpoints = $this->getEndpoints();
    // Clear _GET['destination'] because we need to override it.
    $this->requestStack->getCurrentRequest()->query->remove('destination');
    $authorization_endpoint = Url::fromUri($endpoints['authorization'], $url_options)->toString(TRUE);

    // Super hacky workaround as HMPPS auth takes multiple arguments as
    // duplicate query params.  And there is no way (I can see) to do this with
    // either Drupals url builder, or with guzzle.
    // @see https://mojdt.slack.com/archives/CCMFYP4KG/p1619538462101900
    $response = new TrustedRedirectResponse($authorization_endpoint->getGeneratedUrl() . '&scope=email');
    // We can't cache the response, since this will prevent the state to be
    // added to the session. The kill switch will prevent the page getting
    // cached for anonymous users when page cache is active.
    $this->pageCacheKillSwitch->trigger();

    return $response;
  }

  /**
   * Override getRequestOptions so that we can set the Authorization header.
   */
  protected function getRequestOptions(string $authorization_code, string $redirect_uri): array {
    return [
      'form_params' => [
        'code' => $authorization_code,
        'redirect_uri' => $redirect_uri,
        'grant_type' => 'authorization_code',
      ],
      'headers' => [
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . base64_encode($this->configuration['client_id'] . ':' . $this->configuration['client_secret']),
      ],
    ];
  }
}
