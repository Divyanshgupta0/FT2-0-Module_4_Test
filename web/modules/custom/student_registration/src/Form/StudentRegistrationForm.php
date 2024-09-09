<?php

namespace Drupal\student_registration\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\file\Entity\File;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Mail\MailManagerInterface;

/**
 * This is registration class for StudentRegistrationForm.
 */
class StudentRegistrationForm extends FormBase {

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;
  protected $mailManager;

  /**
   * File system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructor functionn.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager service.
   */
  public function __construct(MailManagerInterface $mail_manager, EntityTypeManagerInterface $entity_type_manager, FileSystemInterface $file_system, MessengerInterface $messenger, LanguageManagerInterface $language_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->mailManager = $mail_manager;
    $this->fileSystem = $file_system;
    $this->messenger = $messenger;
    $this->languageManager = $language_manager;
  }

  /**
   * Summary of create.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Container parameter.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.mail'),
      $container->get('entity_type.manager'),
      $container->get('file_system'),
      $container->get('messenger'),
      $container->get('language_manager')
    );
  }

  /**
   * Summary of getFormId.
   *
   * @return string
   *   It is returning string.
   */
  public function getFormId() {
    return 'student_registration_form';
  }

  /**
   * Summary of buildForm.
   *
   * @param array $form
   *   Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form State.
   *
   * @return array
   *   Returning Array.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $vid = 'student_streams';
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $term_ids = $term_storage->getQuery()
      ->condition('vid', $vid)
      ->accessCheck(FALSE)
      ->execute();
    $terms = $term_storage->loadMultiple($term_ids);
    $options = [];
    foreach ($terms as $term) {
      $options[$term->id()] = $term->getName();
    }

    $form['full_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Full Name'),
      '#required' => TRUE,
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email Address'),
      '#required' => TRUE,
    ];

    $form['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#required' => TRUE,
    ];

    $form['mobile_number'] = [
      '#type' => 'tel',
      '#title' => $this->t('Mobile Number'),
      '#required' => TRUE,
    ];

    $form['stream'] = [
      '#type' => 'select',
      '#title' => $this->t('Stream'),
      '#options' => $options,
      '#required' => TRUE,
    ];

    $form['joining_year'] = [
      '#type' => 'number',
      '#title' => $this->t('Joining Year'),
      '#required' => TRUE,
    ];

    $form['passing_year'] = [
      '#type' => 'number',
      '#title' => $this->t('Passing Year'),
      '#required' => TRUE,
    ];

    $form['picture'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Profile Picture'),
      '#upload_location' => 'public://profile_pictures/',
      '#default_value' => [],
      '#required' => FALSE,
      '#description' => $this->t('Upload a profile picture.'),
      '#multiple' => FALSE,
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg gif'],
        'file_validate_size' => [2560000],
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Register'),
    ];

    return $form;
  }

  /**
   * Summary of validateForm.
   *
   * @param array $form
   *   Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form State.
   *
   * @return void
   *   Reurning Void.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Summary of submitForm.
   *
   * @param array $form
   *   Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form State.
   *
   * @return void
   *   Returning Void.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $user = User::create([
      'name' => $values['full_name'],
      'mail' => $values['email'],
      'pass' => $values['password'],
      'field_users_phone' => $values['mobile_number'],
      'field_student_stream' => ['target_id' => $values['stream']],
      'field_joining_year' => $values['joining_year'],
      'field_passing_year' => $values['passing_year'],
      'status' => 1,
      'roles' => ['student'],
    ]);
    if (!empty($values['picture'])) {
      $file = File::load(reset($values['picture']));
      if ($file) {
        $file->setPermanent();
        $file->save();
        $user->set('user_picture', ['target_id' => $file->id()]);
      }
    }
    $user->save();
    $this->messenger->addMessage($this->t('Registration successful.'));
    $form_state->setRedirect('user.login');
    $admin_email = \Drupal::config('system.site')->get('mail');
    $this->userMail($form_state->getValue('email'), $user->id(), $form_state->getValues());
    $this->adminMail($admin_email, $form_state->getValues());
  }

  /**
   * Summary of userMail.
   *
   * @param mixed $email
   *   User email.
   * @param mixed $user_id
   *   User id.
   * @param array $user_data
   *   User data.
   *
   * @return void
   *   Returning nothing.
   */
  public function userMail($email, $user_id, array $user_data) {
    $module = 'student_registration';
    $key = 'user_mail';
    $params = ['user_id' => $user_id, 'user_data' => $user_data];
    $langcode = \Drupal::languageManager()->getDefaultLanguage()->getId();

    $this->mailManager->mail($module, $key, $email, $langcode, $params);
  }

  /**
   * Summary of adminMail.
   *
   * @param mixed $email
   *   Email of user.
   * @param array $user_data
   *   User data.
   *
   * @return void
   *   Returning Void.
   */
  public function adminMail($email, array $user_data) {
    $module = 'student_registration';
    $key = 'admin_mail';
    $params = ['user_data' => $user_data];
    $langcode = \Drupal::languageManager()->getDefaultLanguage()->getId();

    $this->mailManager->mail($module, $key, $email, $langcode, $params);
  }

}
