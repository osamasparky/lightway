// (function ($) {
//     "use strict";

//     var gateway = 'other';
//     $('body').on('change', 'input[name="gateway"]', function (e) {
//         e.preventDefault();

//         var submitButton = $('button#paymentSubmit');

//         submitButton.removeAttr('disabled');

//         $('html, body').animate({
//             scrollTop: submitButton.offset().top - 250
//         }, 600);

//         gateway = $(this).attr('data-class');
//     });

//     $('body').on('click', '#paymentSubmit', function (e) {
//         e.preventDefault();

//         $(this).addClass('loadingbar primary').prop('disabled', true);

//         if (gateway === 'Razorpay') {
//             $('.razorpay-payment-button').trigger('click');
//         } else {
//             $(this).closest('form').trigger('submit');
//         }
//     });
// })(jQuery);
(function ($) {
  "use strict";

  var gateway = 'other';

  $('body').on('change', 'input[name="gateway"]', function (e) {
    e.preventDefault();

    var submitButton = $('button#paymentSubmit');
    submitButton.removeAttr('disabled');

    $('html, body').animate({
      scrollTop: submitButton.offset().top - 250
    }, 600);

    gateway = $(this).attr('data-class');
  });

  // =========================
  // OPEN MODAL
  // =========================
  $('body').on('click', '#paymentSubmit', function (e) {
    e.preventDefault();
    $('#giftModal').modal('show');
  });

  // =========================
  // TOGGLE GIFT FORM (FIXED)
  // =========================
  $('body').on('change', 'input[type="radio"][name^="sale_type"]', function () {

    let name = $(this).attr('name');
    let id = name.match(/\d+/)[0];

    let form = $('#gift-form-' + id);

    if ($(this).val() === 'other') {
      form.stop(true, true).slideDown().removeClass('d-none');
    } else {
      form.stop(true, true).slideUp(function () {
        $(this).addClass('d-none');
      });
    }
  });

  // =========================
  // CONFIRM GIFT + SUBMIT
  // =========================
$('#confirmGift').on('click', function () {

    let valid = true;

    $('input[name^="sale_type"]:checked').each(function () {

        let id = $(this).attr('name').match(/\d+/)[0];

        if ($(this).val() === 'other') {

            let container = $('#gift-form-' + id);

            let email = container.find('input[name*="[email]"]').val();
            let fullName = container.find('input[name*="[full_name]"]').val();

            if (!email || !fullName) {
                valid = false;
            }
        }
    });

    if (!valid) {
        alert('Please fill required gift information');
        return;
    }

    $('#giftModal input').prop('disabled', false);

    $('#giftModal').modal('hide');

    $('#paymentForm').submit();
});
})(jQuery);

