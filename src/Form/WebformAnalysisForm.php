<?php

namespace Drupal\webform_analysis\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform_analysis\WebformAnalysis;

/**
 * Webform Analysis settings form.
 * 
 * @author Laurent BARAN <lbaran27@gmail.com>
 */
class WebformAnalysisForm extends FormBase {

  protected $analysis;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'webform_analysis_form';
  }

  /**
   * Get webform title.
   *
   * @return string Title.
   */
  public function getTitle() {

    $webform_id = $this->getWebformIdFromRoute();
    if (empty($webform_id)) {
      return '';
    }

    $webform = \Drupal::entityTypeManager()->getStorage('webform')->load($webform_id);
    return $webform->label();
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $webform_id = $this->getWebformIdFromRoute();
    if (empty($webform_id)) {
      return [];
    }

    $this->analysis = new WebformAnalysis($webform_id);

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

      if ($chart['type']) {
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
    ];

    $form['#attached']['library'][] = 'webform_analysis/webform_analysis';
    $form['#attached']['library'][] = 'webform_analysis/webform_charts';

    $form['#attached']['drupalSettings']['webformcharts'] = [
      'packages' => ['corechart'],
      'charts'   => $charts,
    ];

    return $form;
  }

  /**
   * Get Components.
   *
   * @return array Components renderable.
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
   * Get Webform Id.
   *
   * @return string Webform Id.
   */
  public function getWebformIdFromRoute() {
    $route = $this->getRouteMatch();
    if (empty($route)) {
      return '';
    }

    $webform_id = $route->getParameter('webform');
    if (empty($webform_id)) {
      return '';
    }

    return $webform_id;
  }

}
