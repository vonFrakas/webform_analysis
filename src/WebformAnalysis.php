<?php

namespace Drupal\webform_analysis;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\webform\WebformInterface;

/**
 * WebformAnalysis.
 */
class WebformAnalysis implements WebformAnalysisInterface {

  use StringTranslationTrait;

  protected $webform;
  protected $entity;
  protected $elements;

  /**
   * Construct.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity of form.
   */
  public function __construct(EntityInterface $entity) {
    if ($entity instanceof WebformInterface) {
      $this->webform = $entity;
      $this->entity = NULL;
    }
    else {
      $this->entity = $entity;
      $this->webform = $entity->webform->entity;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getWebform() {
    return $this->webform;
  }

  /**
   * {@inheritdoc}
   */
  public function setComponents(array $components = []) {
    $this->webform->setThirdPartySetting('webform_analysis', 'components', $components);
  }

  /**
   * {@inheritdoc}
   */
  public function getComponents() {
    return (array) $this->webform->getThirdPartySetting('webform_analysis', 'components');
  }

  /**
   * {@inheritdoc}
   */
  public function setChartType($chart_type = '') {
    $this->webform->setThirdPartySetting('webform_analysis', 'chart_type', $chart_type);
  }

  /**
   * {@inheritdoc}
   */
  public function getChartType() {
    return (string) $this->webform->getThirdPartySetting('webform_analysis', 'chart_type');
  }

  /**
   * {@inheritdoc}
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
   * {@inheritdoc}
   */
  public function getDisableElementTypes() {
    return ['webform_markup', 'fieldset'];
  }

  /**
   * {@inheritdoc}
   */
  public function getComponentValuesCount($component) {

    $db    = \Drupal::database();
    $query = $db->select('webform_submission_data', 'wsd');
    $query->fields('wsd', ['value']);
    $query->addExpression('COUNT(value)', 'quantity');
    if ($this->entity) {
      $query->leftJoin('webform_submission', 'ws', 'wsd.sid = ws.sid');
    }
    $query->condition('wsd.webform_id', $this->webform->id());
    $query->condition('name', $component);
    if ($this->entity) {
      $query->condition('entity_type', $this->entity->getEntityTypeId());
      $query->condition('entity_id', $this->entity->id());
    }
    $query->groupBy('wsd.value');
    $records = $query->execute()->fetchAll();

    $values = [];
    foreach ($records as $record) {
      $values[$record->value] = (int) $record->quantity;
    }

    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function getComponentRows($component, array $header = [], $value_label_with_count = FALSE) {
    $rows = [];
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
   
    if ($header && $rows) {
      array_unshift($rows, $header);
    }

    return $rows;
  }

  /**
   * {@inheritdoc}
   */
  public function getComponentTitle($component) {
    if (!isset($this->getElements()[$component]['#title'])) {
      return $component;
    }
    return $this->getElements()[$component]['#title'];
  }

}
