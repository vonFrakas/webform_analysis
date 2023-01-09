<?php

namespace Drupal\webform_analysis;

use Drupal\Component\Render\FormattableMarkup;
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
      $this->elements = $this->webform->getElementsInitializedFlattenedAndHasValue();
    }
    return $this->elements;
  }

  /**
   * {@inheritdoc}
   */
  public function getComponentValuesCount($component) {

    $db = \Drupal::database();
    $query = $db->select('webform_submission_data', 'wsd');
    $query->fields('wsd', ['value']);
    $query->addExpression('COUNT(value)', 'quantity');
    if ($this->entity) {
      $query->leftJoin('webform_submission', 'ws', 'wsd.sid = ws.sid');
    }
    $query->condition('wsd.value', "", "!=");
    $query->condition('wsd.webform_id', $this->webform->id());
    $query->condition('name', $component);
    if ($this->entity) {
      $query->condition('entity_type', $this->entity->getEntityTypeId());
      $query->condition('entity_id', $this->entity->id());
    }
    $query->groupBy('wsd.value');
    $records = $query->execute()->fetchAll();

    $values = [];
    $total = (int) 0;
    $allNumeric = TRUE;

    foreach ($records as $record) {
      if (is_numeric($record->value)) {
        $value = $this->castNumeric($record->value);
      }
      else {
        $value = $record->value;
        $allNumeric = FALSE;
      }

      $values[$value] = (int) $record->quantity;
      $total = $total + $values[$value];

    }
    if ($allNumeric) {
      ksort($values);
    }
    $component_counts = ['values' => $values, 'total' => $total];

    return $component_counts;
  }
  public function getNumberOfAnswerers($component) {
    $db = \Drupal::database();
    $query = $db->select('webform_submission_data', 'wsd');
    $query->fields('wsd', ['sid','name',]);
    $query->condition('wsd.webform_id', $this->webform->id());
    $query->condition('name', $component);
    $results = $query->execute()->fetchAll();

    $answers = array_reduce($results, function
    ($carry,  $item) {
      $item = (array) $item;
      $carry[] = $item;
      return $carry;
    });

    if (!$answers) {
      return 0;
    }
    else {
      foreach($answers as $answer) {
        $deduper_key = $answer['sid'] . $answer['name'];
        $deduped[$deduper_key] = $deduper_key;
      }
      return count($deduped);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getComponentRows($component, array $header = [], $value_label_with_count = FALSE) {
    $rows = [];
    $component_values_count = $this->getComponentValuesCount($component);

    $element_obj = $this->getElements()[$component];
    $component_count = $component_values_count['values'];
    $total = $component_values_count['total'];
    $other_responses = [];
    $number_of_answerers = $this->getNumberOfAnswerers($component);

    foreach ($component_count as $value => $count) {

      switch ($element_obj['#type']) {
        case 'checkbox':
          $value_label = $value ? $this->t('Yes') : $this->t('No');
          break;

        case 'textarea':
          $rows[] = [(string) $value];
          continue 2;

        default:
          $value_label = $element_obj['#options'][$value] ?? $value;
          break;
      }

      if ($value_label_with_count) {
        $percentage = $this->calculatePercentage($count, $total);
        $value_label .= ' : ' . $count . ', ' . $percentage;
      }
      elseif ($element_obj['#type'] == 'webform_radios_other') {
        if (in_array($value, $element_obj['#options'])) {
          $percentage = $this->calculatePercentage($count, $total);
          $value_label = $element_obj['#options'][$value] ?? $value;
          $rows[] = [(string) $value_label, $count, $percentage];
        } else {
          $other_responses[] = ['response' => $value, 'count' => $count];
          $other_counts[] = $count;
        }
      }
      elseif ($element_obj['#type'] == 'webform_checkboxes_other') {
        if (in_array($value, $element_obj['#options'])) {
          $percentage = $this->calculatePercentage($count, $number_of_answerers);
          $value_label = $element_obj['#options'][$value] ?? $value;
          $rows[] = [(string) $value_label, $count, $percentage];
        } else {
          $other_responses[] = ['response' => $value, 'count' => $count];
          $other_counts[] = $count;
        }
      }
      elseif ($element_obj['#type'] == 'webform_checkboxes') {
          $percentage = $this->calculatePercentage($count, $number_of_answerers);
          $value_label = $element_obj['#options'][$value] ?? $value;
          $rows[] = [(string) $value_label, $count, $percentage];
      }
      else {
          $percentage = $this->calculatePercentage($count, $total);
          $rows[] = [(string) $value_label, $count, $percentage];
      }
    }
    if (!empty($other_responses)) {
      $count = array_sum($other_counts);
      if($element_obj['#type'] == 'webform_checkboxes_other') {
        $percentage = $this->calculatePercentage($count, $number_of_answerers);
      } else {
        $percentage = $this->calculatePercentage($count, $total);
      }

      $rows['other'] = [t('<span class="other">Other
      <small>(view)</small></span>'), $count,
        $percentage];

      $rows['other_responses'] = [
        'response' => [
          'data' => [
            '#markup' => t('<b>Response</b>'),
          ],
          'class' => 'other-response',
        ],
        'count' => [
          'data' => [
            '#markup' => t('<b>Count</b>'),
          ],
          'class' => 'other-response',
          'colspan' => 2,
        ],
      ];

      foreach($other_responses as $key => $other_response) {
        $rows['other_' . $key] = [
          'other_value' => [
            'data' => [
              '#markup' => $other_response['response'],
            ],
            'class' => 'other-response response',
            'id' => 'response-' . $key,
          ],
          'other_value_count' => [
            'data' => [
              '#markup' => $other_response['count'],
            ],
            'class' => 'other-response count',
            'id' => 'count-' . $key,
            'colspan' => 2,
          ],
        ];

      }
    }

    if ($header && $rows) {
      array_unshift($rows, $header);
    }
    if ($element_obj['#type'] == 'webform_checkboxes_other' || $element_obj['#type'] == 'webform_checkboxes') {
        $rows['xyz'] = [(string) 'Number of answerers', '',
          $number_of_answerers];
    }

    return $rows;
  }

  private function calculatePercentage($count, $total): string {
    $calc_percentage = ($count / $total) * 100;
    return round($calc_percentage, 1) . '%';
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

  /**
   * {@inheritdoc}
   */
  public function getComponentType($component) {
    if (!isset($this->getElements()[$component]['#type'])) {
      return $component;
    }
    return $this->getElements()[$component]['#type'];
  }

  /**
   * {@inheritdoc}
   */
  public static function getChartTypeOptions() {
    return [
      ''            => t('Table'),
      'PieChart'    => t('Pie Chart'),
      'ColumnChart' => t('Column Chart'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isInt($i = '') {
    return ($i === (string) (int) $i);
  }

  /**
   * {@inheritdoc}
   */
  public function castNumeric($i = '') {
    return $this->isInt($i) ? (int) $i : (float) $i;
  }

}
