

$('#order_gateway_2').on('click', function(){
    $("form[name='order']").attr("onsubmit", "return false");
    $("form[name='order']").attr("id", "order_frm");
});

$('#order_gateway_0, #order_gateway_1').on('click', function(){
    $("form[name='order']").removeAttr("onsubmit");
});

$.fn.hasAttr = function(name) {  
   return this.attr(name) !== undefined;
};

$('#order_save').on('click', function(){
    var form = document.getElementById("order_frm");

    if( $("form[name='order']").hasAttr('onsubmit') && $('#order_name').val() != '' && $('#order_regNo').val() != '' && $('#order_vatNo').val() != '' && $('#order_country').val() != '' && $('#order_county').val() != '' && $('#order_city').val() != '' && $('#order_street').val() != '' && $('#order_postalCode').val() != '' )
    {
        $.ajax({
            url: stripePopup,
            dataType: 'json',
            type: 'POST',
            processData: false,
            contentType: false,
            cache: false,
            data: new FormData(form),
            success: function (result) {

                if( result.status == 'success' )
                {
                    $('#stripePopup').modal('show');
                    $('#stripePopup').html(result.tabconent);

                    
                    var elements = stripe.elements();
                    var style = {
                      base: {
                        color: '#32325D',
                        fontWeight: 300,
                        fontSize: '13px',
                        fontSmoothing: 'antialiased',

                        '::placeholder': {
                          color: '#CFD7DF',
                        },
                        ':-webkit-autofill': {
                          color: '#e39f48',
                        },
                      },
                      invalid: {
                        color: '#E25950',

                        '::placeholder': {
                          color: '#FFCCA5',
                        },
                      }
                    };

                    var elementClasses = {
                      focus: 'focused',
                      empty: 'empty',
                      invalid: 'invalid',
                    };

                    // Create an instance of the card number Element
                    var cardNumber = elements.create('cardNumber', {
                      style: style,
                      classes: elementClasses,
                    });
                    // Add an instance of the card Element into the `card-number-element` <div>
                    cardNumber.mount('#paymentCardNumber');

                    // Create an instance of the card expiry Element
                    var cardExpiry = elements.create('cardExpiry', {
                      style: style,
                      classes: elementClasses,
                    });
                    // Add an instance of the card Element into the `card-expiry-element` <div>
                    cardExpiry.mount('#paymentCardExpiry');


                    // Create an instance of the card cvc Element
                    var cardCvc = elements.create('cardCvc', {
                      style: style,
                      classes: elementClasses,
                    });
                    // Add an instance of the card Element into the `card-cvc-element` <div>
                    cardCvc.mount('#paymentCardCvc');

                    // Handle real-time validation errors from the card Element.
                    cardNumber.addEventListener('change', function(event) {
                      var displayError = document.getElementById('card-errors');
                      if (event.error) {
                        displayError.textContent = event.error.message;
                      } else {
                        displayError.textContent = '';
                        displayError.style.display = "none";
                      }
                      if (event.brand) {
                        setBrandIcon(event.brand);
                      }
                    });

                    cardExpiry.addEventListener('change', function(event) {
                      var displayError = document.getElementById('card-errors');
                      if (event.error) {
                        displayError.textContent = event.error.message;
                      } else {
                        displayError.textContent = '';
                        displayError.style.display = "none";
                      }
                    });

                    cardCvc.addEventListener('change', function(event) {
                      var displayError = document.getElementById('card-errors');
                      if (event.error) {
                        displayError.textContent = event.error.message;
                      } else {
                        displayError.textContent = '';
                        displayError.style.display = "none";
                      }
                    });

                    // Handle form submission
                    var form = document.getElementById('payment-form');
                    form.addEventListener('submit', function(event) {
                      event.preventDefault();
                      var elError = $('#card-errors');

                      stripe.createToken(cardNumber).then(function(result) {
                        if (result.error) {

                          // Inform the user if there was an error
                          var errorElement = document.getElementById('card-errors');
                          errorElement.textContent = result.error.message;

                          elError.show();
                          alertHidden = setTimeout(function(){
                            elError.hide();
                          }, 3000);
                        } else {
                          // Send the token to your server
                          stripeTokenHandler(result.token);
                          elError.hide();
                        }
                      });
                    });

                    function stripeTokenHandler(token) {

                      // Insert the token ID into the form so it gets submitted to the server
                      var form = document.getElementById('customForm');
                      var hiddenInput = document.createElement('input');
                      hiddenInput.setAttribute('type', 'hidden');
                      hiddenInput.setAttribute('name', 'stripeToken');
                      hiddenInput.setAttribute('value', token.id);
                      form.appendChild(hiddenInput);
                      // Submit the form
                      paymentConfirm();
                    }

                    // Floating labels
                    var inputs = document.querySelectorAll('.add-payment-info .cell-input');
                    Array.prototype.forEach.call(inputs, function(input) {
                      input.addEventListener('focus', function() {
                        input.classList.add('focused');
                      });
                      input.addEventListener('blur', function() {
                        input.classList.remove('focused');
                      });
                      input.addEventListener('keyup', function() {
                        if (input.value.length === 0) {
                          input.classList.add('empty');
                        } else {
                          input.classList.remove('empty');
                        }
                      });
                    });

                    var labels = document.querySelectorAll('.add-payment-info .has-line-middle.field label');
                    $.each(labels, function() {
                      var that = $(this);
                      that.on('click', function() {
                        that.closest('.has-line-middle').find('.cell-input').addClass('focused');
                      });
                    });

                    var cardBrandToPfClass = {
                      'visa': 'pf-visa',
                      'mastercard': 'pf-mastercard',
                      'amex': 'pf-american-express',
                      'discover': 'pf-discover',
                      'diners': 'pf-diners',
                      'jcb': 'pf-jcb',
                      'unknown': 'pf-credit-card',
                    }

                    function setBrandIcon(brand) {
                      var brandIconElement = document.getElementById('brand-icon');
                      var pfClass = 'pf-credit-card';
                      if (brand in cardBrandToPfClass) {
                        pfClass = cardBrandToPfClass[brand];
                      }
                      for (var i = brandIconElement.classList.length - 1; i >= 0; i--) {
                        brandIconElement.classList.remove(brandIconElement.classList[i]);
                      }
                      brandIconElement.classList.add('pf');
                      brandIconElement.classList.add(pfClass);
                    }
                }
            }
        });
    }

});

function paymentConfirm() {

    $.ajax({
        url: stripePaymentProcess,
        type: 'POST',
        dataType: 'json',
        data: $('#payment-form').serialize(),
        beforeSend: function () {
            $("#card-errors").hide();
        },
        success: function (result) {
            if (result.status == 'success') {
                location.reload(true);
            } else if (result.status == 'failed') {
                $("#card-errors").show();
                $("#card-errors").html('Error :' + result.msg + '<br/>Please check and resubmit your credit card details');
                $('#paymentSubmit').attr('disabled', false);
                alertHidden = setTimeout(function(){
                  $("#card-errors").hide();
                }, 4000);
          /*$('<div id="card-errors" role="alert" class="alert alert-danger"></div>').insertBefore("#customForm")*/
            }
        }

    });

}