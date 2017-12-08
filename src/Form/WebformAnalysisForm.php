<?php

namespace Drupal\webform_analysis\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform_analysis\WebformAnalysis;

/**
 * Webform Analysis settings form.
 */
class WebformAnalysisForm extends EntityForm {

  protected $analysis;

  /**
   * Get webform title.
   *
   * @return string
   *   Title.
   */
  public function getTitle() {
    return $this->entity->label();
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseFormId() {
    // Do not use seven_form_node_form_alter.
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $this->analysis = new WebformAnalysis($this->entity);

    $form['#title'] = $this->getTitle();

    $form['components_data'] = [
      '#type'       => 'container',
      '#attributes' => [
        'class' => ['webform-analysis-data'],
      ],
    ];

    $charts = [];

    foreach ($this->analysis->getComponents() as $component) {

      $class_css = 'webform-chart--' . $component;
      $header    = ['value', 'total'];

      $chart = [
        'type'     => $this->analysis->getChartType(),
        'options'  => [],
        'selector' => '.' . $class_css,
      ];

      switch ($chart['type']) {
        case '':
          $chart['data'] = $this->analysis->getComponentRows($component);
          break;

        case 'PieChart':
          $chart['options'] = ['pieHole' => 0.2];
          $chart['data'] = $this->analysis->getComponentRows($component, $header, TRUE);
          break;

        default:
          $chart['data'] = $this->analysis->getComponentRows($component, $header);
          break;
      }

      $form['components_data']['component__' . $component] = [
        '#theme' => 'webform_analysis_component',
        '#name'  => $component,
        '#title' => $this->analysis->getComponentTitle($component),
        '#data'  => [
          '#theme'  => 'table',
          '#prefix' => '<div class="' . $class_css . '">',
          '#suffix' => '</div>',
        ],
      ];

      if (!$chart['type']) {
        $form['components_data']['component__' . $component]['#data']['#rows'] = $chart['data'];
      }

      if ($chart['type'] && $chart['data']) {
        $charts[] = $chart;
      }
    }

    $form['components_settings'] = [
      '#type'               => 'details',
      '#title'              => $this->t('Add analysis components'),
      '#open'               => FALSE,
      'analysis_components' => $this->getComponents(),
    ];

    $form['analysis_chart_type'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Charts type'),
      '#default_value' => $this->analysis->getChartType(),
      '#options'       => [
        ''            => $this->t('Table'),
        'PieChart'    => $this->t('Pie Charts'),
        'ColumnChart' => $this->t('Column Charts'),
      ],
    ];

    $form['actions']['#type'] = 'actions';

    $form['actions']['submit'] = [
      '#type'        => 'submit',
      '#value'       => $this->t('Update analysis display'),
      '#button_type' => 'primary',
      '#submit' => ['::submitForm', '::save'],
    ];

    $form['#attached']['library'][] = 'webform_analysis/webform_analysis';

    if ($charts) {
      $form['#attached']['library'][] = 'webform_analysis/webform_charts';

      $form['#attached']['drupalSettings']['webformcharts'] = [
        'packages' => ['corechart'],
        'charts'   => $charts,
      ];
    }

    return $form;
  }

  /**
   * Get Components.
   *
   * @return array
   *   Components renderable.
   */
  public function getComponents() {

    foreach ($this->analysis->getElements() as $element_name => $element) {
      $options[$element_name] = isset($element['#title']) ? $element['#title'] : $element_name;
    }

    return [
      '#type'          => 'checkboxes',
      '#options'       => $options,
      '#default_value' => (array) $this->analysis->getComponents(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $this->analysis->setChartType($form_state->getValue('analysis_chart_type'));

    $components = [];
    foreach ($form_state->getValue('analysis_components') as $name => $value) {
      if ($value) {
        $components[] = $name;
      }
    }
    $this->analysis->setComponents($components);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    return $this->analysis->getWebform()->save();
  }

}
