jQuery( document ).ready( function( $ ) {
	// ADD bulk ship option
	var $bulkActions = $('select[name="action"], select[name="action2"]');
    $bulkActions.append('<option value="bulk_ship_delhivery">Bulk Ship by Delhivery</option>');
	$bulkActions.append('<option value="bulk_ship_nimbuspost">Bulk Ship by Nimbuspost</option>');
    $bulkActions.append('<option value="bulk_ship_shiprocket">Bulk Ship by Shiprocket</option>');

    // Handle single popup
	$( '.ship-order-button' ).on( 'click', function(e) {
		e.preventDefault(); // prevent default behavior
		var orderId = $(this).attr('data-order-id');
		var shipBy = $(this).attr('ship-by');
		var popup = $('#es-popup');
		var popupContent = popup.find('.popup-article-ajax');
		popupContent.html('');
		popup.show();
		$.ajax({
			type: 'POST',
			url: 'admin-ajax.php',
			data: {
				action    : 'es_handel_popup',
				order_ids : orderId,
				shipBy    : shipBy,
			},
			beforeSend: function() {
				$('#loader').removeClass('hidden');
			},
			success: function( response ) {
				popupContent.html(response);
			},
			error: function(xhr, status, error) {
				alert('es Error'+ error );
			},
			complete: function(){
				$('#loader').addClass('hidden');
			}
		});
	});

    // Handle bulk popup
    $('#doaction, #doaction2').on('click', function(e) {
        var selectedOption = $bulkActions.val();
        var es_option_selected = false;
        if (selectedOption === 'bulk_ship_delhivery') {
            var shipThrough = 'DLB';
            es_option_selected = true;
        }else if(selectedOption === 'bulk_ship_shiprocket') {
            var shipThrough = 'SRB';
            es_option_selected = true;
        }else if(selectedOption === 'bulk_ship_nimbuspost') {
            var shipThrough = 'NPB';
            es_option_selected = true;
        }
        if (es_option_selected) {
            e.preventDefault();
            var selectedOrders = [];
            $('input[name="post[]"]:checked').each(function() {
                selectedOrders.push($(this).val());
            });
            if (selectedOrders.length > 0) {
                var popup = $('#es-popup');
                var popupContent = popup.find('.popup-article-ajax');
                popupContent.html('');
                popup.show();
                $.ajax({
                    type: 'POST',
                    url: 'admin-ajax.php',
                    data: {
                        action    : 'es_handel_popup',
                        order_ids : selectedOrders,
                        shipBy    : shipThrough,
                    },
                    beforeSend: function() {
                        $('#loader').removeClass('hidden');
                    },
                    success: function( response ) {
                        popupContent.html(response);
                        var sum = 0;
                        $('tr.es-bulk-table-row').each(function() {
                            var selectedRate = parseInt($(this).find('select[name="shipping_id[]"] option:selected').text().split(' - ')[0].replace('₹', ''));
                            if (!isNaN(selectedRate)) {
                                sum += selectedRate;
                            }
                        });
                        $('#es-shipp-total').text('₹' + sum); // Update the column with the new total
                    },
                    error: function(xhr, status, error) {
                        alert('es Error'+ error );
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
	
    // handel create single shipment /button (Ship Now)
    $(document).on('submit', '#es_create_single_shipment', function(event) {
        event.preventDefault(); // prevent form submission
        var OrderId     = document.getElementById("OrderId").value;
        var shipBy      = document.getElementById("ship_by").value;
        var shipping_id = null; 
		var shipping_Button = document.getElementsByName("shipping_id");
		for (var i = 0; i < shipping_Button.length; i++) {
			if (shipping_Button[i].checked) {
				shipping_id = shipping_Button[i].value;
				break;
			}
		}
        if(!(shipBy == 'DL')){ //this line for single courier partner like - delhivery
            if (shipping_id.length === 0) {
                alert("Please select a shipping option.");
                return false;
            }
        }
        $.ajax({
            type: 'POST',
            url: 'admin-ajax.php',
            data: {
				action   		: 'es_ship_single_order',
				order_id		: OrderId,
				ship_company_ID	: shipping_id,
				ship_by 		: shipBy,
            },
            beforeSend: function() {
                $('#loader').removeClass('hidden');
            },
            success: function(response) {
                if (response == 'shipped') {
                    alert('Success - ' + response);
                    window.location.reload();
                } else {
                    alert('Fail - ' + response);
                }
            },
            error: function(xhr, status, error) {
                alert('es ERROR - ' + error);
            },
            complete: function() {
                $('#loader').addClass('hidden');
            },
        });
        document.getElementById("es_create_single_shipment").reset();
    });
    // handel create bulk shipment /button (Ship Now)
    $(document).on('submit', '#es_create_bulk_shipment', function(event) {
        event.preventDefault(); // prevent form submission
        var shipBy = document.getElementById("ship_by").value;
        var orderData = []; // Array to store order values and shipping IDs
        if(shipBy == 'DL'){ //this line for single courier partner like - delhivery
            var orderData = $('#es_create_bulk_shipment input[name="order_IDs[]"]').map(function() {
                return $(this).val();
              }).get();  
        }else{
            $('tr.es-bulk-table-row').each(function() {
                var orderID = $(this).find('input[name="order_IDs[]"]').val();
                var shippingID = $(this).find('select[name="shipping_id[]"]').val();
                // Store the order data in an object
                var order = {
                    orderID: orderID,
                    shippingID: shippingID
                };
                // Add the order object to the array
                orderData.push(order);
            });
        }
        if (orderData.length === 0) {
            alert("Zero order Found");
            return false;
        }
        $.ajax({
            type: 'POST',
            url: 'admin-ajax.php',
            data: {
				action   		: 'es_ship_bulk_order',
				order_ids		: orderData,
				ship_by 		: shipBy,
            },
            beforeSend: function() {
                $('#loader').removeClass('hidden');
            },
            success: function(response) {
                var response_json = response.replace(/\\/g, '');
                // Check if "shipped" is present in the string
                if (response_json.indexOf("shipped") !== -1) {
                    alert('Success - ' + response_json);
                    window.location.reload();
                } else {
                    alert('Fail - ' + response_json);
                }
            },
            error: function(xhr, status, error) {
                alert('es ERROR - ' + error);
            },
            complete: function() {
                $('#loader').addClass('hidden');
            },
        });
        document.getElementById("es_create_bulk_shipment").reset();
    });

    // Event handler for when the selected option changes
    $(document).on('change', 'tr.es-bulk-table-row select[name="shipping_id[]"]', function() {
        var sum = 0;
        $('tr.es-bulk-table-row').each(function() {
            var selectedRate = parseInt($(this).find('select[name="shipping_id[]"] option:selected').text().split(' - ')[0].replace('₹', ''));
            if (!isNaN(selectedRate)) {
                sum += selectedRate;
            }
        });
        $('#es-shipp-total').text('₹' + sum); // Update the column with the new total
    });

    // Handle Remove row of table in bulk popup rate 
    $(document).on('click', '.es-remove-row', function() {
        var row            = $(this).closest('tr');
        var grosh_total    = $('#es-grosh-total').text(); // Use .text() to get the current value
        var shipping_total = $('#es-shipp-total').text(); 

        var orderAmount = row.find('.es-row-amount').text().replace(/[^0-9]/g, ''); 
        var shipAmount  = row.find('#shipping_id option:selected').text().split(' - ')[0].replace(/[^0-9]/g, '');
        
        // Update the grosh_total column
        var newGroshTotal = parseInt(grosh_total.replace('₹', '')) - orderAmount; // Update the total based on the removed row's value
        var newShipTotal = parseInt(shipping_total.replace('₹', '')) - shipAmount; // Update the total based on the removed row's value

        $('#es-grosh-total').text('₹' + newGroshTotal); // Update the column with the new total
        $('#es-shipp-total').text('₹' + newShipTotal); // Update the column with the new total
        row.remove();
    });
    // Handle close popup button    
    $(document).on('click', '.eashyship-close-js', function(e) {
        var popup = $('#es-popup');
        popup.hide();
    });
    // Handle close popup button    
    $(document).on('click', '.eashyship-table tr', function(e) {
        $(this).find('#shipping_id').prop('checked', true);
    });
});
