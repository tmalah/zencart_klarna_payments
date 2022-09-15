<?php if ($_SESSION['payment'] == 'klarna_payments') { //echo '<pre>'; print_r($order); echo '</pre>'; exit(); ?>

<script src="https://x.klarnacdn.net/kp/lib/v1/api.js" async></script>

<script type="text/javascript">

$( document ).ready(function() {
    
    //window.klarnaAsyncCallback = function () {
    
    
    $(document).on('change', 'input[name=klarna_option]', function() { 
    
        $('.klarna-option-desc').hide();
        
        try {
            Klarna.Payments.init({
                client_token: $('#klarna_client_token').val()
            })
            Klarna.Payments.load(
            {
                container: '#klarna-'+$('input[name=klarna_option]:checked').val()+'-desc',
                payment_method_category: $('input[name=klarna_option]:checked').val()
            },
            function (res) {
                console.log(res);
            }
            );
        } catch (e) {
            // Handle error. The load~callback will have been called
            // with "{ show_form: false }" at this point.
            console.log(e);
        }
        
        $('#klarna-'+$('input[name=klarna_option]:checked').val()+'-desc').show();
        
    });
    
    //}

    $('#checkout_confirmation').submit(function() { 
        
        event.preventDefault();
        
        var button = document.getElementById("btn_submit");
        
        //console.log($('input[name=klarna_option]:checked').val() ); return false;
        
        if ($('input[name=klarna_option]:checked').length && $('input[name=klarna_option]:checked').val() != '') {
        
            Klarna.Payments.authorize({
              payment_method_category: $('input[name=klarna_option]:checked').val()
            },
            
            {
            <?php if (MODULE_PAYMENT_KLARNA_TEST_MODE == 'True') {  ?>
            
              purchase_country: "GB",
              purchase_currency: "GBP",
              locale: "en-GB",
              billing_address: {
                given_name: "Test",
                family_name: "Person-uk",
                email: "<?php echo $order->customer['email_address']; ?>",
                //email: "customer@email.uk",
                //title: "",
                street_address: "New Burlington St, 10, Apt 214",
                street_address2: "",
                postal_code: "W13 3BG",
                city: "London",
                //region: "",
                phone: "<?php echo $order->customer['telephone']; ?>",
                //phone: "01895081461",
                country: "GB"
              },
              shipping_address: {
                given_name: "Test",
                family_name: "Person-uk",
                email: "<?php echo $order->customer['email_address']; ?>",
                //email: "customer@email.uk",
                //title: "",
                street_address: "New Burlington St, 10, Apt 214",
                street_address2: "",
                postal_code: "W13 3BG",
                city: "London",
                //region: "",
                phone: "<?php echo $order->customer['telephone']; ?>",
                //phone: "01895081461",
                country: "GB"
              },
            
            <?php } else { ?>
            
              purchase_country: "GB",
              purchase_currency: "GBP",
              locale: "en-GB",
              billing_address: {
                given_name: "<?php echo $order->billing['firstname']; ?>",
                family_name: "<?php echo $order->billing['lastname']; ?>",
                email: "<?php echo $order->customer['email_address']; ?>",
                //title: "",
                street_address: "<?php echo $order->billing['street_address']; ?>",
                street_address2: "<?php echo $order->billing['suburb']; ?>",
                postal_code: "<?php echo $order->billing['postcode']; ?>",
                city: "<?php echo $order->billing['city']; ?>",
                //region: "",
                phone: "<?php echo $order->customer['telephone']; ?>",
                country: "<?php echo $order->billing['country']['iso_code_2']; ?>"
              },
              shipping_address: {
                given_name: "<?php echo $order->delivery['firstname']; ?>",
                family_name: "<?php echo $order->delivery['lastname']; ?>",
                email: "<?php echo $order->customer['email_address']; ?>",
                //title: "",
                street_address: "<?php echo $order->delivery['street_address']; ?>",
                street_address2: "<?php echo $order->delivery['suburb']; ?>",
                postal_code: "<?php echo $order->delivery['postcode']; ?>",
                city: "<?php echo $order->delivery['city']; ?>",
                phone: "<?php echo $order->customer['telephone']; ?>",
                country: "<?php echo $order->delivery['country']['iso_code_2']; ?>"
              },
              
            <?php } ?>
            
            order_amount: <?php echo round((float)$order->info['total']*100, 0); ?>,
              order_tax_amount: <?php echo round((float)$order->info['tax']*100, 0); ?>,
              order_lines: [
              <?php foreach ($order->products as $product) { ?>
              {
                type: "physical",
                reference: "<?php echo $product['model']; ?>",
                name: "<?php echo $product['name']; ?>",
                quantity: <?php echo $product['qty']; ?>,
                unit_price: <?php echo round((float)$product['final_price']*100, 0); ?>,
                <?php /* //tax_rate: <?php echo $product['tax']*100; ?>, */ ?>
                total_amount: <?php echo round((float)$product['final_price'] * $product['qty']*100, 0); ?>,
                total_discount_amount: 0,
                <?php /* //total_tax_amount: <?php echo $product['tax']; ?> */ ?>
                
              },
              <?php } ?>
              
              <?php //  add shipping ?>
              {
                type: "shipping_fee",
                reference: "<?php echo $order->info['shipping_module_code']; ?>",
                name: "<?php echo $order->info['shipping_method']; ?>",
                quantity: 1,
                unit_price: <?php echo round((float)$order->info['shipping_cost']*100, 0); ?>,
                <?php /* //tax_rate: <?php echo (float)$order->info['shipping_tax']*100; ?>, */ ?>
                total_amount: <?php echo round((float)$order->info['shipping_cost']*100, 0); ?>,
                total_discount_amount: 0,
                <?php /* //total_tax_amount: <?php echo (float)$order->info['shipping_tax']*100; */ ?>
                
              },
              
              <?php //  add tax ?>
              {
                type: "sales_tax",
                name: "Tax",
                quantity: 1,
                unit_price: <?php echo round((float)$order->info['tax']*100, 0); ?>,
                total_amount: <?php echo round((float)$order->info['tax']*100, 0); ?>
              }
              
              ],
              customer: {
                date_of_birth: "10-07-1970",
              },
            }, function(res) { //console.log(res); return false;
                if (res.authorization_token && res.approved) {
                    $('#klarna_authorization_token').val(res.authorization_token);
                    $('#checkout_confirmation').unbind('submit').submit();
                    return false;
                }
                if (res.approved === false) {
                    alert('Your purchase can not be accepted. Please try again or select other payment option.');
                    
                    button.style.cursor = "pointer";
                    button.disabled = false;
                    
                    return false;
                }
            })
            
            button.style.cursor = "pointer";
            button.disabled = false;
            
            return false;
            
        } else {
            
            alert('Please select Klarna payment option');
            
            setTimeout('button_timeout_enable()', 4200);
            
            return false;
            
        }
    });
    
})

</script>

<?php } ?>