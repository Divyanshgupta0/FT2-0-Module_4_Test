<?php

namespace Drupal\expose_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class ExposeApiController.
 *
 * @package Drupal\expose_api\Controller
 */
class ExposeApiController extends ControllerBase {

  /**
   * Entity manager interface.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   *   Entity type manager interface.
   */
  protected $entityTypeManager;

  /**
   * File url generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   *   File url generator interface.
   */
  protected $fileUrlGenerator;

  /**
   * Constructor function for dependency injection.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type service.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $fileUrlGenerator
   *   File url generator.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, FileUrlGeneratorInterface $fileUrlGenerator) {
    $this->entityTypeManager = $entityTypeManager;
    $this->fileUrlGenerator = $fileUrlGenerator;
  }

  /**
   * Summary of create.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Container interface parameter.
   *
   * @return static
   *   Returning static.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('file_url_generator')
    );
  }

  /**
   * List students data based on parameter provided.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function listStudents(Request $request) {
    $stream = $request->query->get('stream');
    $joining_year = $request->query->get('joining_year');
    $passing_year = $request->query->get('passing_year');
    $users_phone = $request->query->get('users_phone');

    $user_storage = $this->entityTypeManager->getStorage('user');

    $query = $user_storage->getQuery()
      ->condition('status', 1)
      ->condition('roles', 'student')
      ->accessCheck(TRUE);

    if (!empty($joining_year)) {
      $query->condition('field_joining_year', $joining_year);
    }

    if (!empty($passing_year)) {
      $query->condition('field_passing_year', $passing_year);
    }

    if (!empty($stream)) {
      $query->condition('field_student_stream.target_id', $stream);
    }

    if (!empty($users_phone)) {
      $query->condition('field_users_phone', $users_phone);
    }

    $uids = $query->execute();

    $users = $user_storage->loadMultiple($uids);

    $student_data = [];
    foreach ($users as $user) {
      $data = [
        'name' => $user->getDisplayName(),
        'email' => $user->getEmail(),
        'joining_year' => $user->get('field_joining_year')->value,
        'passing_year' => $user->get('field_passing_year')->value,
        'student_stream' => $user->get('field_student_stream')->entity ? $user->get('field_student_stream')->entity->label() : NULL,
        'users_phone' => $user->get('field_users_phone')->value,
      ];

      $student_data[] = $data;
    }

    return new JsonResponse($student_data);
  }

}
