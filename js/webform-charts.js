(function ($, Drupal, settings) {

  "use strict";

  Drupal.behaviors.WebformCharts = {
    attach: function (context, settings) {

      var webformcharts = settings.webformcharts;

      google.charts.load('current', {'packages': webformcharts.packages});

      google.charts.setOnLoadCallback(function () {

        webformcharts.charts.forEach(function (chart) {

          var data = new google.visualization.arrayToDataTable(chart.data);
          var options = chart.options;
          var chart = new google.visualization[chart.type](document.querySelector(chart.selector));
          chart.draw(data, options);

        });
      });

    }
  };
})(jQuery, Drupal, drupalSettings);
