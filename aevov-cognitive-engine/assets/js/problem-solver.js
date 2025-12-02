(function ($) {
  'use strict';

  var ProblemSolver = {
    init: function () {
      $('#problem-solver-form').on('submit', this.solveProblem);
    },

    solveProblem: function (e) {
      e.preventDefault();
      var problem = $('#problem').val();

      $.ajax({
        url: '/wp-json/aevov-cognitive/v1/solve',
        method: 'POST',
        data: {
          problem: problem
        },
        beforeSend: function (xhr) {
          // You might need a nonce if the endpoint requires authentication.
        }
      }).done(function (response) {
        if (response.solution) {
          $('#solution-container').html(response.solution);
        } else {
          alert('Error solving problem.');
        }
      }).fail(function () {
        alert('Error solving problem.');
      });
    }
  };

  $(document).ready(function () {
    ProblemSolver.init();
  });

})(jQuery);
