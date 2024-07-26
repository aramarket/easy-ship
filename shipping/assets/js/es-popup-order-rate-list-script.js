jQuery( document ).ready( function( $ ) {
    // append option list in bulk action in wc order page
    var $bulkActions = $('select[name="action"], select[name="action2"]');
    $bulkActions.append('<option value="ship_shiprocket">Bulk ship by Shiprocket</option>');
    $bulkActions.append('<option value="ship_delhivery">Bulk ship by Delhivery</option>');
    $bulkActions.append('<option value="ship_nimbuspost">Bulk ship by NimbusPost</option>');
	
	// this for print label
	$('.print-label-button').on('click', function(e) {
		e.preventDefault();
		var orderId = $(this).attr('data-order-id');
		var $button = $(this);

		// Create spinner
		var $spinner = $('<div class="es-spinner"></div>');

		// Replace button with spinner
		$button.hide().after($spinner);
		
		$.ajax({
			type: 'POST',
			url: 'admin-ajax.php',
			data: {
				action  : 'es_print_label',
				orderID : orderId,
			},
			success: function(response) {
				// Remove spinner and show button
				$spinner.remove();
				$button.show();
				
				if (response.success) {
// 					alert(response.result);               
					window.location.href = response.result;
				} else {
        			alert('Fail - ' + response.message); // Use response.message for fail message
				}
			},
			error: function(response) {
				// Remove spinner and show button
				$spinner.remove();
				$button.show();
				alert('An unexpected error occurred.');
			}
		});
	});
	
	
    // Handle popup of order get rate list 
    $('#doaction, #doaction2, .ship-order-button').on('click', function(e) {
        var selectedOption = $bulkActions.val();
        var es_option_selected = false;
        var orders = [];
        var shipBy;
        
        if ($(e.target).hasClass('ship-order-button')) { 
            es_option_selected = true;
            orders.push($(this).attr('data-order-id'));
            shipBy = $(this).attr('ship-by');
        } else if (selectedOption.startsWith('ship_')) {
            es_option_selected = true;
            $('input[name="post[]"]:checked').each(function() {
                orders.push($(this).val());
            });
            shipBy = selectedOption.split('_')[1]; // Get the shipBy value from the selected option
        }
		
        if (es_option_selected) {
            e.preventDefault();
            if (orders.length > 0) {
                var popup = $('#es-popup');
                var popupContent = popup.find('.popup-article-ajax');
                popupContent.html('');
                popup.show();
                $.ajax({
                    type: 'POST',
                    url: 'admin-ajax.php',
                    data: {
                        action    : 'es_popup_rate_list',
                        order_ids : orders,
                        shipBy    : shipBy,
                    },
                    beforeSend: function() {
                        $('#loader').removeClass('hidden');
                    },   
                    success: function( response ) {
						popupContent.html(response);
                        var sum = 0;
                        $('tr.es-bulk-table-row').each(function() {
                            var selectedRate = parseInt($(this).find('select[name="selected_courier"] option:selected').text().split(' - ')[0].replace('₹', ''));
                            if (!isNaN(selectedRate)) {
                                sum += selectedRate;
                            }
                        });
                        $('#es-shipp-total').text('₹' + sum); // Update the column with the new total
                    },
                    error: function(xhr, status, error) {
                        alert('AJAX ERROR - ' + error + ' - ' + status);
				        popup.hide();
                    },
                    complete: function(){
                        $('#loader').addClass('hidden');
                    }
                });
            } else {
                alert('No orders selected!');
            }       
        }
    });
	
    // handel create bulk shipment /button (Ship Now)
    $(document).on('submit', '#es_create_bulk_shipment', function(event) {
        event.preventDefault(); // prevent form submission
        var shipBy = document.getElementById("ship_by").value;
        var orderData = []; // Array to store order values and shipping IDs
		$('tr.es-bulk-table-row').each(function() {
			var orderID = $(this).find('input[name="order_ID"]').val();
			var courierID = $(this).find('select[name="selected_courier"]').val();
			// Store the order data in an object
			var order = {
				orderID: orderID,
				courierID: courierID
			};
			// Add the order object to the array
			orderData.push(order);
		});
        if (orderData.length === 0) {
            alert("Zero order Found");
            return false;
        }
        $.ajax({
            type: 'POST',
            url: 'admin-ajax.php',
            data: {
				action   		: 'es_handel_ship_order',
				orderData		: orderData,
				shipBy 		: shipBy,
            },
            beforeSend: function() {
                $('#loader').removeClass('hidden');
            },
            success: function(response) {
//     			alert(JSON.stringify(response)); // This will show the response as a string in the alert
				if (response.success) {
					alert(response.result);
                    window.location.reload();
				} else {
        			alert('Fail - ' + response.message); // Use response.message for fail message
					$('#loader').addClass('hidden');
				}
            },
            error: function(xhr, status, error) {
                alert('AJAX ERROR - ' + error + ' - ' + status);
				$('#loader').addClass('hidden');
            },
            complete: function() {
                $('#loader').addClass('hidden');
            },
        });
        document.getElementById("es_create_bulk_shipment").reset();
    });

    // Handle Remove row of table in bulk popup rate 
    $(document).on('click', '.es-remove-row', function() {
        var row            = $(this).closest('tr');
        var grosh_total    = $('#es-grosh-total').text(); // Use .text() to get the current value
        var shipping_total = $('#es-shipp-total').text(); 
		var currentOrderCount = parseInt($('#es-order-count').text(), 10);
		
        var orderAmount = row.find('.es-row-amount').text().replace(/[^0-9]/g, ''); 
        var shipAmount  = row.find('#selected_courier option:selected').text().split(' - ')[0].replace(/[^0-9]/g, '');
		
        // Update the grosh_total column
        // Update the total based on the removed row's value
        var newGroshTotal = parseInt(grosh_total.replace('₹', '')) - orderAmount; 
		// Update the total based on the removed row's value
        var newShipTotal = parseInt(shipping_total.replace('₹', '')) - shipAmount; 
		// Update order count
		var newOrderCount = currentOrderCount - 1;

        $('#es-grosh-total').text('₹' + newGroshTotal); // Update the column with the new total
        $('#es-shipp-total').text('₹' + newShipTotal); // Update the column with the new total
		$('#es-order-count').text(newOrderCount); // Update the column with the new total
        row.remove();
    });
	
	    // Shipping total change when the selected option changes
    $(document).on('change', 'tr.es-bulk-table-row select[name="selected_courier"]', function() {
        var sum = 0;
        $('tr.es-bulk-table-row').each(function() {
            var selectedRate = parseInt($(this).find('select[name="selected_courier"] option:selected').text().split(' - ')[0].replace('₹', ''));
            if (!isNaN(selectedRate)) {
                sum += selectedRate;
            }
        });
        $('#es-shipp-total').text('₹' + sum); // Update the column with the new total
    });
	
	// Handle close popup button and icon 
    $(document).on('click', '.eashyship-close-btn', function(e) {
        var popup = $('#es-popup');
        popup.hide();
    });
});
