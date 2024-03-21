<?php

namespace Drupal\openy_node_alert\Plugin\rest\resource;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\openy_node_alert\Service\AlertManager;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "alerts_rest_resource",
 *   label = @Translation("OpenY Alerts resource"),
 *   uri_paths = {
 *     "canonical" = "/alerts"
 *   }
 * )
 */
class AlertsRestResource extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The alias manager that caches alias lookups based on the request.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * The Path Matcher.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  protected $pathMatcher;

  /**
   * The current path.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPath;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The current Request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The router doing the actual routing.
   *
   * @var \Symfony\Component\Routing\Matcher\RequestMatcherInterface
   */
  protected $router;

  /**
   * The alert manager.
   *
   * @var \Drupal\openy_node_alert\Service\AlertManager
   */
  protected $alertManager;

  /**
   * Language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a new AlertsRestResource object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   A current user instance.
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   *   The alias manager.
   * @param \Drupal\Core\Path\PathMatcherInterface $path_matcher
   *   The Path Matcher.
   * @param \Drupal\Core\Path\CurrentPathStack $current_path
   *   The current path.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Symfony\Component\Routing\Matcher\RequestMatcherInterface $router
   *   The router doing the actual routing.
   * @param AlertManager $alert_manager
   *   The alert manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user,
    AliasManagerInterface $alias_manager,
    PathMatcherInterface $path_matcher,
    CurrentPathStack $current_path,
    EntityTypeManagerInterface $entity_type_manager,
    ModuleHandlerInterface $module_handler,
    Request $request,
    RequestMatcherInterface $router,
    AlertManager $alert_manager,
    LanguageManagerInterface $language_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    $this->currentUser = $current_user;
    $this->aliasManager = $alias_manager;
    $this->pathMatcher = $path_matcher;
    $this->currentPath = $current_path;
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->request = $request;
    $this->router = $router;
    $this->alertManager = $alert_manager;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('openy_node_alert'),
      $container->get('current_user'),
      $container->get('path_alias.manager'),
      $container->get('path.matcher'),
      $container->get('path.current'),
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('router.no_access_checks'),
      $container->get('openy_node_alert.alert_manager'),
      $container->get('language_manager')
    );
  }

  /**
   * Responds to GET requests.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \HttpException
   */
  public function get() {

    // You must to implement the logic of your REST Resource here.
    // Use current user after pass authentication to validate access.
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }

    $uri = $this->request->query->get('uri');

    // Extract the language code from the URI.
    $langcode = substr($uri, 1, 2);
    $language = $this->languageManager->getLanguage($langcode);
    $alert_language = NULL;
    if ($language) {
      $alert_language = $language->getId();
    }

    // Check if the first segment is a valid language code.
    $request_langcode = substr($this->request->getPathInfo(), 1, 2);
    $request_language = $this->languageManager->getLanguage($request_langcode);

    if ($language && !$request_language || $request_language && $request_language->getId() != $alert_language) {
      // Redirect to the same request URL with the language code added after the basePath.
      $redirectUrl = $this->request->getSchemeAndHttpHost() . '/' . $langcode . $this->request->getRequestUri();
      $response = new ModifiedResourceResponse(NULL, 303); // 303 See Other
      $response->headers->set('Location', $redirectUrl);
      return $response;
    }

    $result = $this->router->match($uri);
    if (!isset($result['node'])) {
      return new ModifiedResourceResponse('Node not found');
    }

    [$sendAlerts, $alerts] = $this->alertManager->getAlerts($result['node'], $alert_language);

    $this->moduleHandler->alter('openy_node_alert_get', $sendAlerts, $alerts);

    return new ModifiedResourceResponse($sendAlerts, 200);
  }
}
