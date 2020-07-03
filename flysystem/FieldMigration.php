<?php

/**
 * @file
 * Contains \Drupal\flysystem\Form\FieldMigration.
 */

namespace Drupal\flysystem\Form;

use Drupal;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\flysystem\FlysystemFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure file system settings for this site.
 */
class FieldMigration extends FormBase {

  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Field Manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $fieldManager;

  /**
   * Field Type Manager.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManagerInterface
   */
  protected $fieldTypeManager;

  /**
   * The Flysystem factory.
   *
   * @var \Drupal\flysystem\FlysystemFactory
   */
  protected $factory;

  /**
   * Stream Wrapper Manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * Constructs a FieldMigration object.
   *
   * @param \Drupal\flysystem\FlysystemFactory $factory
   *   The FlysystemF factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager
   *   Field manager.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $field_type_manager
   *   Field type manager.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   Stream wrapper manager.
   */
  public function __construct(
    FlysystemFactory $factory,
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $field_manager,
    FieldTypePluginManagerInterface $field_type_manager,
    StreamWrapperManagerInterface $stream_wrapper_manager
  ) {

    $this->factory = $factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->fieldManager = $field_manager;
    $this->fieldTypeManager = $field_type_manager;
    $this->streamWrapperManager = $stream_wrapper_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('flysystem_factory'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('stream_wrapper_manager')
    );
  }

  /**
   * Callback for ajax requests.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  public static function ajaxCallback(
    array $form,
    FormStateInterface $form_state
  ) {
    return $form;
  }

  /**
   * Migrate the storage of the files in the field of the given entity.
   *
   * @param string $entity_type
   *   Entity type machine name.
   * @param string $entity_id
   *   Entity id.
   * @param string $field
   *   Field name.
   * @param string $scheme
   *   Scheme.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function migrateFieldStorage(
    $entity_type,
    $entity_id,
    $field,
    $scheme
  ) {
    $storage = Drupal::entityTypeManager()->getStorage($entity_type);

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $storage->load($entity_id);

    /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $entityReferenceField */
    $entityReferenceField = $entity->get($field);
    /** @var \Drupal\file\FileInterface[] $files */
    $files = $entityReferenceField->referencedEntities();

    foreach ($files as $file) {
      if (!file_exists($file->getFileUri())) {
        continue;
      }

      list($current_scheme, $path) = explode('://', $file->getFileUri());

      if ($current_scheme === $scheme) {
        continue;
      }

      $destination = "$scheme://$path";
      $destination_dir = Drupal::service('file_system')->dirname($destination);

      file_prepare_directory(
        $destination_dir,
        FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS
      );

      copy($file->getFileUri(), $destination);
      $source = clone $file;

      $file->setFileUri($destination);
      $file->save();

      // Inform modules that the file has been moved.
      Drupal::moduleHandler()->invokeAll('file_move', [$file, $source]);

      // Delete the original if it's not in use elsewhere.
      if (!Drupal::service('file.usage')->listUsage($source)) {
        $source->delete();
      }
    }
  }

  /**
   * @param $entity_type
   * @param $field_name
   * @param $scheme
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function updateFieldSettings(
    $entity_type,
    $field_name,
    $scheme
  ) {
    $field_storage = FieldStorageConfig::loadByName($entity_type, $field_name);
    $field_storage->setSetting('uri_scheme', $scheme);
    $field_storage->save();

  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'flysystem_field_migration';
  }

  /**
   * {@inheritdoc}
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $entity_type = $form_state->getValue('entity_type');
    $field = $form_state->getValue('field');
    $scheme = $form_state->getValue('scheme');

    $form['update_field_settings'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Update field settings'),
      '#default_value' => FALSE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
      '#weight' => 100,
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Migrate'),
      '#disabled' => TRUE,
    ];

    $form['#prefix'] = '<div id="flysystem-field-options-ajax-wrapper">';
    $form['#suffix'] = '</div>';

    $form['entity_type'] = [
      '#title' => $this->t('Entity type'),
      '#type' => 'select',
      '#options' => $this->getEntityTypeOptions(),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'wrapper' => 'flysystem-field-options-ajax-wrapper',
      ],
    ];

    if (!$entity_type) {
      return $form;
    }

    $form['field'] = [
      '#title' => $this->t('Field'),
      '#type' => 'select',
      '#options' => $this->getFieldOptions($entity_type),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'wrapper' => 'flysystem-field-options-ajax-wrapper',
      ],
    ];

    if (!$field) {
      return $form;
    }

    $scheme_options = $this->streamWrapperManager->getNames(
      StreamWrapperInterface::WRITE_VISIBLE
    );

    $fields = $this->getFileFields($entity_type);
    $existing_scheme = $fields[$field]->getSettings()['uri_scheme'];
    $scheme_label = $scheme_options[$existing_scheme];

    $form['scheme'] = [
      '#title' => $this->t('Migrate to'),
      '#type' => 'select',
      '#options' => $scheme_options,
      '#description' => $this->t(
        'This field currently stores files in the %scheme scheme.',
        ['%scheme' => $scheme_label]
      ),
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'wrapper' => 'flysystem-field-options-ajax-wrapper',
      ],
      '#required' => TRUE,
    ];


    if (!$scheme) {
      return $form;
    }

    unset($form['actions']['submit']['#disabled']);

    return $form;
  }

  /**
   * @return array
   */
  protected function getEntityTypeOptions() {
    $entity_type_options = [];

    foreach ($this->entityTypeManager->getDefinitions() as $entity_type) {
      if ($entity_type->entityClassImplements(
        FieldableEntityInterface::class
      )) {
        $entity_type_options[$entity_type->id()] = $entity_type->getLabel();
      }
    }

    return $entity_type_options;
  }

  /**
   * @param $entity_type_id
   *
   * @return array
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getFieldOptions($entity_type_id) {
    return array_map(
      static function (FieldStorageDefinitionInterface $field) {
        return $field->getLabel();
      },
      $this->getFileFields($entity_type_id)
    );
  }

  /**
   * @param $entity_type_id
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getFileFields($entity_type_id) {
    $fields = [];

    foreach ($this->fieldManager->getFieldStorageDefinitions($entity_type_id) as $field) {
      $field_type_definition = $this->fieldTypeManager->getDefinition(
        $field->getType()
      );

      if (!is_a(
        $field_type_definition['class'],
        EntityReferenceItem::class,
        TRUE
      )) {
        continue;
      }

      $fields[$field->getName()] = $field;
    }

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity_type = $form_state->getValue('entity_type');
    $field = $form_state->getValue('field');
    $scheme = $form_state->getValue('scheme');
    $update_field_settings = (bool) $form_state->getValue('update_field_settings');

    $query = $this->entityTypeManager->getStorage($entity_type)->getQuery();
    $entity_ids = $query->exists($field)->execute();

    $batch = [
      'operations' => [],
      'finished' => get_class($this) . '::finishBatch',
      'title' => $this->t('Migrating fields'),
      'init_message' => $this->t('Starting migration process.'),
      'progress_message' => $this->t('Completed @current step of @total.'),
      'error_message' => $this->t('Field migration has encountered an error.'),
    ];

    foreach ($entity_ids as $entity_id) {
      $batch['operations'][] = [
        get_class($this) . '::migrateFieldStorage',
        [$entity_type, $entity_id, $field, $scheme],
      ];
    }

    if ($update_field_settings) {
      $batch['operations'][] = [
        get_class($this) . '::updateFieldSettings',
        [$entity_type, $field, $scheme],
      ];
    }

    batch_set($batch);
  }

}
