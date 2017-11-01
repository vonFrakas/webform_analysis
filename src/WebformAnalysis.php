<?php

namespace Drupal\webform_analysis;

use Drupal\Core\StringTranslation\StringTranslationTrait;

class WebformAnalysis {

  use StringTranslationTrait;

  protected $webform;
  protected $elements;

  public function __construct($webform_id) {
    $this->webform = \Drupal::entityTypeManager()->getStorage('webform')->load($webform_id);
  }

  /**
   * getWebform
   * @return Webform
   */
  public function getWebform() {
    return $this->webform;
  }

  /**
   * setComponents
   * @param array $components
   */
  public function setComponents($components = array()) {
    $this->webform->setSetting('analysis_components', $components);
    $this->webform->save();
  }

  /**
   * getComponents
   * @return array
   */
  public function getComponents() {
    return $this->webform->getSetting('analysis_components');
  }

  /**
   * setChartType
   * @param array $components
   */
  public function setChartType($chart_type) {
    $this->webform->setSetting('analysis_chart_type', $chart_type);
    $this->webform->save();
  }

  /**
   * getChartType
   * @return array
   */
  public function getChartType() {
    return $this->webform->getSetting('analysis_chart_type');
  }

  /**
   * getElements
   * @return array
   */
  public function getElements() {
    if (!$this->elements) {
      $this->elements = $this->webform->getElementsDecoded();
      $types = $this->getDisableElementTypes();
      foreach ($this->elements as $key => $element) {
        if (array_search($element['#type'],$types) !== false)
          unset($this->elements[$key]);
      }
    }
    return $this->elements;
  }

  /**
   * getDisableElementTypes
   * @return array
   */
  public function getDisableElementTypes() {
    return ['webform_markup','fieldset'];
  }

  /**
   * getComponentValuesCount
   * @param string $component
   * @return array
   */
  public function getComponentValuesCount($component) {

    $query   = \Drupal::database()
        ->select('webform_submission_data', 'wsd')
        ->fields('wsd', array('value'));
    $query->addExpression('COUNT(value)', 'quantity');
    $query->condition('webform_id', $this->webform->id())
        ->condition('name', $component);
    $query->groupBy('wsd.value');
    $records = $query->execute()->fetchAll();

    $values                 = [];
    foreach ($records as $record)
      $values[$record->value] = (int) $record->quantity;

    return $values;
  }

  /**
   * getComponentRows
   * @param string $component
   * @param bool $add_header
   * @param bool $value_label_with_count
   * @return array
   */
  public function getComponentRows($component, $header = array(), $value_label_with_count = false) {
    $rows   = [];
    if ($header)
      $rows[] = $header;
    foreach ($this->getComponentValuesCount($component) as $value => $count) {
      switch ($this->getElements()[$component]['#type']) {
        case 'checkbox':
          $value_label = $value 
            ? $this->t('Yes') 
            : $this->t('No');
          break;
        default:
          $value_label = isset($this->getElements()[$component]['#options'][$value]) 
            ? $this->getElements()[$component]['#options'][$value] 
            : $value;
          break;
      }
      if ($value_label_with_count)
        $value_label .= ' : ' . $count;

      $rows[] = [(string) $value_label, $count];
    }
    return $rows;
  }

  public function getComponentTitle($component) {
    if (!isset($this->getElements()[$component]['#title']))
      return $component;
    return $this->getElements()[$component]['#title'];
  }

}
