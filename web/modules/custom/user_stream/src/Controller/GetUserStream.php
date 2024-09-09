<?php

namespace Drupal\user_stream\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\path_alias\AliasManagerInterface;

/**
 * Controller for handling user stream functionality.
 */
class GetUserStream extends ControllerBase {

  /**
   * Path alias manager service.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * Logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a GetUserStream object.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   *   The path alias manager service.
   */
  public function __construct(LoggerInterface $logger, AliasManagerInterface $alias_manager) {
    $this->logger = $logger;
    $this->aliasManager = $alias_manager;
  }

  /**
   * Creates an instance of GetUserStream.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   *
   * @return static
   *   Returns a new instance of this class.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory')->get('user_stream'),
      $container->get('path_alias.manager')
    );
  }

  /**
   * Redirects the user to their stream page.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirect response object.
   */
  public function transferStream(Request $request) {
    $currentUserId = $this->currentUser()->id();
    $userEntity = User::load($currentUserId);

    if ($userEntity) {
      $userArrayData = $userEntity->toArray();
      $this->logger->info('User data: @data', ['@data' => print_r($userArrayData, TRUE)]);
      $streamField = $userEntity->get('field_student_stream');
      $termId = $streamField->target_id ?? NULL;
      if ($termId) {
        $taxonomyTerm = Term::load($termId);
        if ($taxonomyTerm) {
          $path = '/taxonomy/term/' . $termId;
          $streamUrl = $this->aliasManager->getAliasByPath($path);
          $this->logger->info('Redirecting to stream URL: @url', ['@url' => $streamUrl]);
          return new RedirectResponse($streamUrl);
        }
        else {
          $this->logger->warning('Taxonomy term with ID @id not found.', ['@id' => $termId]);
        }
      }
      else {
        $this->logger->warning('No valid stream term ID found for user.');
      }
    }
    else {
      $this->logger->warning('Unable to load user entity for ID @id.', ['@id' => $currentUserId]);
    }
    return new RedirectResponse('/');
  }

}
