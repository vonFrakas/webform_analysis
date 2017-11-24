<?php

namespace Drupal\webform_analysis;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * WebformAnalysis.
 *
 * @author Laurent BARAN <lbaran27@gmail.com>
 */
class WebformAnalysis {

  use StringTranslationTrait;

  protected $webform;
  protected $elements;

  /**
   * Construct.
   *
   * @param string $webform_id
   *   The webform Id.
   */
  public function __construct($webform) {
    $this->webform = $webform;
  }

  /**
   * Get Webform.
   *
   * @return object
   *   Webform.
   */
  public function getWebform() {
    return $this->webform;
  }

  /**
   * Set components and save webform.
   *
   * @param array $components
   *   The components name.
   */
  public function setComponents(array $components = []) {
    $this->webform->setThirdPartySetting('webform_analysis', 'components', $components);
    $this->webform->save();
  }

  /**
   * Get Components.
   *
   * @return array
   *   Components.
   */
  public function getComponents() {
    return (array) $this->webform->getThirdPartySetting('webform_analysis', 'components');
  }

  /**
   * Set Chart Type.
   *
   * @param string $chart_type
   *   Set chart type and save webform.
   */
  public function setChartType($chart_type = '') {
    $this->webform->setThirdPartySetting('webform_analysis', 'chart_type', $chart_type);
    $this->webform->save();
  }

  /**
   * Get Chart Type.
   *
   * @return array
   *   Chart type.
   */
  public function getChartType() {
    return (string) $this->webform->getThirdPartySetting('webform_analysis', 'chart_type');
  }

  /**
   * Get Elements.
   *
   * @return array
   *   Element.
   */
  public function getElements() {
    if (!$this->elements) {
      $this->elements = $this->webform->getElementsDecoded();
      $types          = $this->getDisableElementTypes();
      foreach ($this->elements as $key => $element) {
        if (array_search($element['#type'], $types) !== FALSE) {
          unset($this->elements[$key]);
        }
      }
    }
    return $this->elements;
  }

  /**
   * Get Disable Element Types.
   *
   * @return array
   *   Element types.
   */
  public function getDisableElementTypes() {
    return ['webform_markup', 'fieldset'];
  }

  /**
   * Get Component Values Count.
   *
   * @param string $component
   *   The component name.
   *
   * @return array
   *   Values.
   */
  public function getComponentValuesCount($component) {

    $db = \Drupal::database();
    $query = $db->select('webform_submission_data', 'wsd');
    $query->fields('wsd', ['value']);
    $query->addExpression('COUNT(value)', 'quantity');
    $query->condition('webform_id', $this->webform->id());
    $query->condition('name', $component);
    $query->groupBy('wsd.value');
    $records = $query->execute()->fetchAll();

    $values = [];
    foreach ($records as $record) {
      $values[$record->value] = (int) $record->quantity;
    }

    return $values;
  }

  /**
   * Get Component Rows.
   *
   * @param string $component
   *   The component name.
   * @param array $header
   *   The first line data.
   * @param bool $value_label_with_count
   *   If true, add count to label.
   *
   * @return array
   *   Rows.
   */
  public function getComponentRows($component, array $header = [], $value_label_with_count = FALSE) {
    $rows = [];
    if ($header) {
      $rows[] = $header;
    }
    foreach ($this->getComponentValuesCount($component) as $value => $count) {
      switch ($this->getElements()[$component]['#type']) {
        case 'checkbox':
          $value_label = $value ? $this->t('Yes') : $this->t('No');
          break;

        default:
          $value_label = isset($this->getElements()[$component]['#options'][$value]) ? $this->getElements()[$component]['#options'][$value] : $value;
          break;
      }
      if ($value_label_with_count) {
        $value_label .= ' : ' . $count;
      }

      $rows[] = [(string) $value_label, $count];
    }

    return $rows;
  }

  /**
   * Get Component title.
   *
   * @param string $component
   *   The component name.
   *
   * @return string
   *   Component title.
   */
  public function getComponentTitle($component) {
    if (!isset($this->getElements()[$component]['#title'])) {
      return $component;
    }
    return $this->getElements()[$component]['#title'];
  }

}
