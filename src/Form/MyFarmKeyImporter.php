<?php

namespace Drupal\farm_myfarmkey\Form;

use Drupal\asset\Entity\Asset;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\geofield\GeoPHP\GeoPHPInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for importing MyFarmKey cadastaral geometries.
 */
class MyFarmKeyImporter extends FormBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The GeoPHP service.
   *
   * @var \Drupal\geofield\GeoPHP\GeoPHPInterface
   */
  protected $geoPHP;

  /**
   * Constructs a new KmlImporter object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\geofield\GeoPHP\GeoPHPInterface $geo_PHP
   *   The GeoPHP service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, FileSystemInterface $file_system, GeoPHPInterface $geo_PHP) {
    $this->entityTypeManager = $entity_type_manager;
    $this->fileSystem = $file_system;
    $this->geoPHP = $geo_PHP;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('file_system'),
      $container->get('geofield.geophp'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'farm_kml_import_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('GeoJSON File'),
      '#description' => $this->t('Upload your GeoJSON file here and click "Parse".'),
      '#upload_location' => 'private://geojson',
      '#upload_validators' => [
        'file_validate_extensions' => ['geojson'],
      ],
      '#required' => TRUE,
    ];

    // Build a tree for asset data.
    $form['output'] = [
      '#type' => 'container',
      '#prefix' => '<div id="parsed-assets">',
      '#suffix' => '</div>',
    ];

    // Display parse button.
    $form['output']['parse'] = [
      '#type' => 'button',
      '#value' => $this->t('Parse'),
      '#name' => 'parse',
      '#ajax' => [
        'callback' => '::assetsCallback',
        'wrapper' => 'parsed-assets',
      ],
    ];

    // Only generate the output if parse was selected.
    $submit = $form_state->getTriggeringElement();
    if (empty($submit) || $submit['#name'] != 'parse') {
      return $form;
    }

    // Get the uploaded file contents.
    /** @var \Drupal\file\FileInterface $file */
    $file_ids = $form_state->getValue('file', []);
    $file = $this->entityTypeManager->getStorage('file')->load(reset($file_ids));
    $path = $file->getFileUri();
    $file_data = file_get_contents($path);
    $json_data = Json::decode($file_data);

    // Bail if no geometries were found.
    if (!isset($json_data['features']) || !is_array($json_data['features']) || empty($json_data['features'])) {
      $this->messenger()->addWarning($this->t('No geometries could be parsed from the uploaded file.'));
      return $form;
    }

    // Change the parse button for a submit button.
    $form['output']['parse']['#access'] = FALSE;
    $form['output']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create assets'),
    ];

    // Render each asset.
    $form['output']['assets'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];
    $features = $json_data['features'];
    foreach ($features as $index => $feature) {

      // Create a fieldset for the geometry.
      $name = $feature['properties']['jurisdicational_id'] ?? $this->t('Unknown jurisdicational id');
      $form['output']['assets'][$index] = [
        '#type' => 'details',
        '#title' => $this->t('Property @number: @name', ['@number' => $index + 1, '@name' => $name]),
        '#open' => $index === 0,
      ];

      // Add asset values.
      $form['output']['assets'][$index]['name'] = [
        '#type' => 'hidden',
        '#value' => $name,
      ];

      // Include all properties in the asset data field.
      $form['output']['assets'][$index]['data'] = [
        '#type' => 'hidden',
        '#value' => Json::encode($feature['properties']),
      ];

      // ID Tags.
      $id_tags = [];
      foreach (['lot_number', 'plan_number'] as $tag_name) {
        if (!empty($feature['properties'][$tag_name])) {
          $id_tags[] = ['type' => $tag_name, 'id' => $feature['properties'][$tag_name]];
        }
      }
      $form['output']['assets'][$index]['id_tag'] = [
        '#type' => 'hidden',
        '#value' => $id_tags,
      ];

      // Include all geojson properties in the asset notes.
      $note_lines = [];
      $excluded_note_keys = ['lot_number', 'plan', 'plan_number', 'pic', 'integrity_systems_pic_details'];
      foreach ($feature['properties'] as $property_name => $property_value) {

        // Skip excluded keys and empty values.
        if (in_array($property_name, $excluded_note_keys) || empty($property_value)) {
          continue;
        }

        // Build the note line.
        $nice_name = ucfirst(str_replace('_', ' ', $property_name));
        $note_lines[] = "$nice_name: $property_value";
      }
      $default_notes = implode(PHP_EOL, $note_lines);
      $form['output']['assets'][$index]['notes'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Info'),
        '#default_value' => $default_notes,
        '#disabled' => TRUE,
        '#rows' => 12,
      ];

      // Extract the intrinsic geometry references.
      // Re-encode into json.
      $geom = $this->geoPHP->load(Json::encode($feature), 'json');
      $reduced = \geoPHP::geometryReduce($geom);
      $wkt = $reduced->out('wkt');
      $form['output']['assets'][$index]['geometry'] = [
        '#type' => 'hidden',
        '#value' => $wkt,
      ];

      $form['output']['assets'][$index]['confirm'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Create this asset'),
        '#description' => $this->t('Uncheck this if you do not want to create this asset in farmOS.'),
        '#default_value' => TRUE,
      ];
    }

    return $form;
  }

  /**
   * Ajax callback that returns the assets container after parsing geojson.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return mixed
   *   The elements to replace.
   */
  public function assetsCallback(array &$form, FormStateInterface $form_state) {
    return $form['output'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Bail if no file was uploaded.
    $file_ids = $form_state->getValue('file', []);
    if (empty($file_ids)) {
      $this->messenger()->addError($this->t('File upload failed.'));
      return;
    }

    // Load the assets to create.
    $assets = $form_state->getValue('assets', []);
    $confirmed_assets = array_filter($assets, function ($asset) {
      return !empty($asset['confirm']);
    });

    // Create new assets.
    foreach ($confirmed_assets as $asset) {
      $new_asset = Asset::create([
        'type' => 'land',
        'land_type' => 'property',
        'name' => $asset['name'],
        'data' => $asset['data'],
        'id_tag' => $asset['id_tag'],
        'notes' => $asset['notes'],
        'intrinsic_geometry' => $asset['geometry'],
        'is_location' => TRUE,
        'is_fixed' => TRUE,
      ]);

      // Save the asset.
      $new_asset->save();
      $asset_url = $new_asset->toUrl()->setAbsolute()->toString();
      $this->messenger()->addMessage($this->t('Created land asset: <a href=":url">%asset_label</a>', [':url' => $asset_url, '%asset_label' => $new_asset->label()]));
    }
  }

}
