/***********************************
	Admin Plugin Settings
 ***********************************/

jQuery(document).ready(function($) {
	
	// only on admin preferences pane
	if ( $('#p2p-rel-relationship-list').length < 1 ) return;
	
	// Open / close relation
	$(document).on('click', '.p2p-rel-title, .p2p-rel-edit', function () {
		
		relation = $(this).closest('li.p2p-rel-relation');
		
		if ( $(relation).hasClass('open') ) {
			$('.p2p-rel-body', relation).slideUp( function () {
				$(relation).removeClass('open');
			});
		} else {
			$('.p2p-rel-body', relation).slideDown();
			$(relation).addClass('open');
		}
		
		return false;
	});

	// Initialize select2
	if(jQuery().select2) $("#p2p-rel-relationship-list .select2").select2();
	
	// Update header for reactive title
	$(document).on('keyup', 'input.reactive_title', function () {
		relation = $(this).closest('li.p2p-rel-relation');
		title = $(this).val();
		if ( title.trim() == '' ) title = '(untitled)';
		$('.p2p-rel-header .label a.p2p-rel-title', relation).text ( title );
	});

	// Check key change, and stop if it has relationships
	$(document).on('keyup change', 'input.reactive_key', function () {
		
		relation = $(this).closest('li.p2p-rel-relation');
		if ( !$(relation).hasClass('has_relationships') ) return;
		
		if ( $(this).val() != $(this).attr('data-ddbb-key') && $(this).attr('data-ddbb-key') != '' ) {
			alert ("You cannot change this key because this relation has relationships, and it would be orphaned.");
			$(this).val( $(this).attr('data-ddbb-key') );
			
			return false;
		}
	});

	// Update header for reactive key
	$(document).on('keyup', 'input.reactive_key', function () {
		relation = $(this).closest('li.p2p-rel-relation');
		$('.p2p-rel-header .name', relation).text ( $(this).val() );
	});

	// Make sortable
	$("#p2p-rel-relationship-list .sortable").sortable({

		//items: item_selector,
		cursor: 'move',
		handle: '.icon',
		axis: 'y',
		//forcePlaceholderSize: true,
		//helper: 'clone',
		//opacity: 0.65,
		//placeholder: 'fns-rule-placeholder',
		//scrollSensitivity: 40,

		// This event is triggered when the user stopped sorting and the DOM position has changed.
		update: function( event, ui ) {
			
			updated_list();
		}
	});
	
	// We will create a new empty relationship
	$('#p2p-rel-relationship-list .p2p-rel-new-rel').click( function () {
		
		cloned = $('.p2p-relationship-empty > li:first').clone();
						
		$(cloned).appendTo("#p2p-rel-relationship-list .sortable");

		// Update header
		$(cloned).find('.p2p-rel-header .rel').text('0');
		$(cloned).find('.reactive_title').trigger('keyup');
		$(cloned).find('.reactive_key').attr('data-ddbb-key', '').trigger('keyup');
		
		// Start select2 on cloned
		if(jQuery().select2) $('.select2', cloned).select2();
		
		updated_list()
		
		// Open it
		$('.p2p-rel-header .label a.p2p-rel-title', cloned).trigger('click');
		
		return false;
	});

	// We will duplicate the requested relationship and keep his values
	$(document).on('click', '.p2p-rel-duplicate', function () {
		
		cloned = $(this).closest('li.p2p-rel-relation').clone().removeClass('open has_relationships').addClass('no_relationships');

		// Delete posible dialog 
		$(cloned).find('.p2p-rel-dialog').remove();

		// Delete posible erase relationships link 
		$(cloned).find('.p2p-rel-erase').remove();

		//Reset select 2
		$(cloned).find('.select2-container').remove();
		$(cloned).find('select.select2').removeClass('select2-hidden-accessible enhanced').removeAttr('aria-hidden');
				
		$(cloned).appendTo("#p2p-rel-relationship-list .sortable");

		// Update header
		$(cloned).find('.p2p-rel-header .rel').text('0');
		$(cloned).find('.reactive_title').val( $(cloned).find('.reactive_title').val() + ' (copy)' ).trigger('keyup');
		$(cloned).find('.reactive_key').val( $(cloned).find('.reactive_key').val() + '_copy' ).attr('data-ddbb-key', '').trigger('keyup');

		// Start select2 on cloned
		if(jQuery().select2) $('.select2', cloned).select2();
		
		updated_list()
		
		// Open it
		$('.p2p-rel-header .label a.p2p-rel-title', cloned).trigger('click');
		
		return false;
	});
	
	// Click on delete relation link
	$(document).on('click', '.p2p-rel-delete', function () {
		
		// Remove any previous dialog
		$('.p2p-rel-dialog').remove();
		
		relation = $(this).closest('li.p2p-rel-relation');
		
		if ( relation.hasClass('has_relationships') ) {
			
			// Show message with options and exit
			message = '<p class="big"><strong>This relation has ' + $( '.p2p-rel-header .rel', relation ).text() + ' relationships inside.</strong> Really do you want continue?</p>'
					  + '<ul><li><a href="#" class="p2p-rel-dialog-cancel">No, I won\'t delete anything</a></li>'
					  + '<li><a href="#" class="p2p-rel-dialog-delete-all">Yes, delete the relation with his relationships</a></li>' 
					  + '<li><a href="#" class="p2p-rel-dialog-delete-relation-only">Delete relation, but keep relationships</a>*</li></ul>'
					  + '<p>* Only if you know what are you doing: relationships will be kept in database, but will be orphans.</p>';
			$('.p2p-rel-header', relation).after('<div class="p2p-rel-dialog">' + message + '</div>');
			
			return false;
		}
		
		delete_relation(relation);
		
		return false;
	});

	// Click on erase relationship link
	$(document).on('click', '.p2p-rel-erase', function () {
		
		// Remove any previous dialog
		$('.p2p-rel-dialog').remove();
		
		relation = $(this).closest('li.p2p-rel-relation');
		
		// Show message with options and exit
		message = '<p class="big"><strong>This action will delete ' + $( '.p2p-rel-header .rel', relation ).text() + ' relationships.</strong> Really do you want continue?</p>'
				  + '<ul><li><a href="#" class="p2p-rel-dialog-cancel">No, I won\'t delete anything</a></li>'
				  + '<li><a href="#" class="p2p-rel-dialog-delete-relationships">Yes, erase this relationships, I want to restart this relation</a></li></ul>'; 
		$('.p2p-rel-header', relation).after('<div class="p2p-rel-dialog">' + message + '</div>');
				
		return false;
	});
	
	$(document).on('click', '.p2p-rel-dialog-cancel', function () {

		dialog = $(this).closest('.p2p-rel-dialog');
		close_dialog ( dialog );

		return false;
	});
	
	$(document).on('click', '.p2p-rel-dialog-delete-all', function () {
		
		relation = $(this).closest('li.p2p-rel-relation');		

		delete_relationships( relation );
		delete_relation ( relation );

		close_dialog ( $('.p2p-rel-dialog', relation) );
		
		return false;
	});

	$(document).on('click', '.p2p-rel-dialog-delete-relation-only', function () {
		
		relation = $(this).closest('li.p2p-rel-relation');		
		delete_relation ( relation );
		
		return false;
	});
	
	$(document).on('click', '.p2p-rel-dialog-delete-relationships', function () {
		
		relation = $(this).closest('li.p2p-rel-relation');		
		delete_relationships( relation );
		
		close_dialog ( $('.p2p-rel-dialog', relation) );
		
		return false;
	});
	
	function delete_relation ( relation ) {

		$(relation).css('background', '#d00').fadeOut( function () { 
			$(relation).remove();
			updated_list();
		});
	}
	
	function delete_relationships( relation ) {
		
		key = $( '.reactive_key', relation ).attr( 'data-ddbb-key' );
		$(relation).closest('form').append('<input type="hidden" name="p2p-rel-erase-relationships[]" value="' + key + '">');

		$('.p2p-rel-erase', relation).remove();
		$(relation).removeClass('has_relationships').addClass('no_relationships');
		$( '.p2p-rel-header .rel', relation).text('0');
	}

	$(document).on('click', '.p2p-rel-erase-orphan', function () {
		
		key = $( this ).attr( 'data-ddbb-key' );
		$(this).closest('form').append('<input type="hidden" name="p2p-rel-erase-relationships[]" value="' + key + '">');

		// There are more orphan relationships?
		if ( $('#p2p-rel-orphans .p2p-rel-erase-orphan').length > 1 ) {
			target = $( this ).closest('li');
			$(target).slideUp( function() {
				$(target).remove();
			});
		} else {
			$('#p2p-rel-orphans').slideUp( function () {
				$('#p2p-rel-orphans').remove();
			});
		}
		return false;
	});

	function close_dialog ( dialog ) {

		if ( dialog.length > 0 ) {
		
			$(dialog).slideUp( function () {
				$(dialog).remove();
			});
		}
	}
	
	$(document).on('change', 'select.ui_mode_combo', function () {
		refresh_lock_fields();
	});
	
	function updated_list() {

		// Remove any previous dialog
		$('.p2p-rel-dialog').remove();

		$('#p2p-rel-relationship-list .sortable > li').not('.no_relations').each (function (index, el) {
			$('.icon', el).text(index+1);
			
			// rename the fields (relationship number, first occurrence)
			$('input, select, textarea', el).each(function(idx, field) {
				var fieldname = $(field).attr('name');
				// select2 create fields without name!
				if (typeof(fieldname) != 'undefined') {
					$(field).attr('name', fieldname.replace(/\[[0-9]+\]/, '['+index+']'));
				}
			});

		});
		
		if ( $('#p2p-rel-relationship-list .sortable > li').length > 1 ) {
			$('#p2p-rel-relationship-list .no_relations').hide();
		} else {
			$('#p2p-rel-relationship-list .no_relations').show();
		}
		refresh_lock_fields();
	}
	
	function refresh_lock_fields() {
		$( '#p2p-rel-relationship-list select.ui_mode_combo' ).each (function (index, el) {
			
			wrap     = $(el).closest('.p2p-rel-two_cols');
			iam      = $(el).closest('.p2p-rel-col');
			reverse  = $('.p2p-rel-col', wrap).not(iam);

			
			if ( $(el).val() == 'hidden' ) {
				$('.filter_fields',   iam).addClass('disabled_field');
				$('.position_fields', iam).addClass('disabled_field');
				
			} else if ( $(el).val() == 'view' ) {
				$('.position_fields', iam).removeClass('disabled_field');
				$('.filter_fields',   iam).addClass('disabled_field');
				
			} else {
				$('.filter_fields',   iam).removeClass('disabled_field');
				$('.position_fields', iam).removeClass('disabled_field');
			}
		});
	}
	
	updated_list();
});



/***********************************
	Admin Metaboxes 
 ***********************************/

jQuery(document).ready(function($) {
	
	var remove_item = '<a href="#" class="remove"><span class="dashicons dashicons-dismiss"></span></a>';
	var add_item    = '<a href="#" class="add"><span class="dashicons dashicons-plus-alt"></span></a>';
	var checkbox    = '<input type="checkbox" value=""> ';
	var loading     = '<p>loading...</p>';

	// Make related items sortable
	$(".p2p-relationships-selected .sortable").sortable({

		//items: item_selector,
		cursor: 'move',
		//handle: '.column-handle',
		axis: 'y',
		//forcePlaceholderSize: true,
		//helper: 'clone',
		//opacity: 0.65,
		//placeholder: 'fns-rule-placeholder',
		//scrollSensitivity: 40,

		// This event is triggered when the user stopped sorting and the DOM position has changed.
		/*update: function( event, ui ) {

			fix_cell_colors('#shipping-rules-table-fns tbody tr');

			refresh_rules();
		}*/
	});
	
	// AJAX loading
	$('.p2p-relationships-ajax').each( function( index, ajax_wrapper ) {

		do_search( ajax_wrapper );
	});
	
	// Filter
	$( '.p2p-relationships-ajax' ).each( function ( index, ajax_wrapper ) {
		
		timeout = null;
				
		// Search
		$( '.search', ajax_wrapper ).on( 'keyup', function (e) {
			
			// Ignore arrows, shiftkeys, etc.
			if (e.which > 15 && e.which < 21) return;
			if (e.which > 32 && e.which < 46) return;
			
			if ( $(ajax_wrapper).hasClass( 'waiting_ajax' ) ) {
				$(ajax_wrapper).addClass( 'must_again' );
				
				if(e.which == 13) return false;
				return;
			}
			
			if (timeout !== null) clearTimeout( timeout );
			
			timeout = setTimeout ( 
					function () { 
						$(ajax_wrapper).addClass( 'waiting_ajax' );
						do_search( ajax_wrapper ); 
					},
					500 
			);

			if(e.which == 13) return false;
			return;
		});

		// Combos
		$( 'select', ajax_wrapper ).on('change', function () {
						
			if ( $(ajax_wrapper).hasClass( 'waiting_ajax' ) ) {
				$(ajax_wrapper).addClass( 'must_again' );
				
				return;
			}
			
			if (timeout !== null) clearTimeout( timeout );
			
			timeout = setTimeout ( 
					function () { 
						$(ajax_wrapper).addClass( 'waiting_ajax' );
						do_search( ajax_wrapper ); 
					},
					500 
			);

			return;
		});
	});
	

	// Add element
	$(document).on('click', '.p2p-relationships-chooser .add', function () {
		
		add_element( this );
		
		// Uncheck the checkbox if needed
		$( 'input', $(this).closest('li') ).prop('checked', false);
		
		update_relationships( cont );

		return false;
	});
	
	function add_element( element ) {

		cont = $(element).closest('.p2p-relationships-wrapper');

		el = $(element).closest('li');		
		id = $(el).attr('data-id');
		title = $('.title', el).html();

		// Prevent duplicate element on selection list
		found_yet = false;
		jQuery( '.p2p-relationships-selected li', cont ).each( function (index, look_el) {
			if ( $(look_el).attr('data-id') == id ) {
				found_yet = true;
				return false;
			}
		});
		
		if (found_yet) return;
		
		new_code = '<li data-id="'+id+'">#'+id + ' ' + title + remove_item + '<input type="hidden" name="'+get_field_name(cont)+'" value="'+id+'" /></li>';
		jQuery( '.p2p-relationships-selected ul', cont ).append( new_code );
	}
	
	function get_field_name( wrapper ) {

		key  = $( wrapper ).attr( 'data-key' );
		iam  = $( wrapper ).attr( 'data-iam' );
		
		return 'p2p-relationships[' + key +'_'+ iam +'][]'; 
	}
	
	// Remove element
	$(document).on('click', '.p2p-relationships-wrapper .remove', function () {
		
		remove_element( this );

		// Uncheck the checkbox if there is one and it needed
		$( 'input', $(this).closest('li') ).prop('checked', false);

		update_relationships( cont );
		
		return false;
	});
	
	function remove_element ( element ) {

		cont = $(element).closest('.p2p-relationships-wrapper');

		el = $(element).closest('li');		
		id = $(el).attr('data-id');
		
		jQuery( '.p2p-relationships-selected li', cont ).each( function (index, look_el) {
			
			if ( $(look_el).attr('data-id') == id ) $( look_el ).remove();
		});
	}
	
	// Do search (AJAX)
	function do_search( ajax_wrapper ) {
		
		$( '.p2p-relationships-ajax-in', ajax_wrapper ).html( loading );

		$.ajax({
			url: ajaxurl,
			data: { 
					action: 'p2p_relationships',
					what:   $( ajax_wrapper ).attr( 'data-what' ), 
					
					key:    $( ajax_wrapper ).attr( 'data-key' ), 
					iam:    $( ajax_wrapper ).attr( 'data-iam' ), 

					// Filters
					s:      $( '.search',   ajax_wrapper ).val(), 
					type:   $( '.type',     ajax_wrapper ).val(), 
					tax:    $( '.taxonomy', ajax_wrapper ).val(), 
			},
			error: function (xhr, status, error) {
				var errorMessage = xhr.status + ': ' + xhr.statusText
				console.log('P2P Relationships AJAX error - ' + errorMessage);
			},
			success: function (data) {
				
				$(ajax_wrapper).removeClass( 'waiting_ajax' );
				
				$( '.p2p-relationships-ajax-in', ajax_wrapper ).html( data );
				
				$( '.p2p-relationships-ajax-in li', ajax_wrapper ).each ( function (index, look_el) {
					
					$(look_el).prepend( checkbox ).append( remove_item + add_item );
				});
				
				update_relationships( ajax_wrapper );
				
				if ( $(ajax_wrapper).hasClass( 'must_again' ) ) {
					$(ajax_wrapper).removeClass( 'must_again' );
					do_search( ajax_wrapper );
				}

			},
			dataType: 'html'
		});
	}

	// Let's update the chooser list with active / passive class attributes
	function update_relationships( wrapper ) {
		
		$( '.p2p-relationships-chooser li', wrapper ).removeClass('active').addClass('passive');

		jQuery( '.p2p-relationships-selected li', wrapper ).each( function (index, el) {
			
			id = $(el).attr( 'data-id' );

			$( '.p2p-relationships-chooser li', wrapper ).each ( function (index, look_el) {
				
				if ( $(look_el).attr('data-id') == id ) $(look_el).addClass('active').removeClass('passive');
			});
		});
		
		if ( $('.p2p-relationships-selected li', wrapper).length > 0 ) {
			$('.no_relationships', wrapper).hide();
		} else {
			$('.no_relationships', wrapper).show();
		}
		
		refresh_buttons ( wrapper );
	}
	
	$(document).on('change', '.p2p-relationships-chooser input', function () {

		wrapper = $(this).closest('.p2p-relationships-wrapper');
		refresh_buttons ( wrapper );
	});
	
	// Buttons activation / deactivation
	function refresh_buttons ( wrapper ) {
		
		n_selected = $( '.p2p-relationships-chooser input:checked', wrapper ).length;
		n_total    = $( '.p2p-relationships-chooser input', wrapper ).length;
				
		$( '.buttons a', wrapper ).removeAttr('disabled').show();
		
		if ( n_total == 0 ) {
			
			$( '.buttons .unselect_all', wrapper ).hide();
			$( '.buttons a', wrapper ).attr('disabled', true);
			
		} else if ( n_selected < n_total ) {

			$( '.buttons .unselect_all', wrapper ).hide();
			
		} else {
			
			$( '.buttons .select_all', wrapper ).hide();
		}
		
		if ( n_selected == 0 ) {

			$( '.buttons .add_sel', wrapper ).attr('disabled', true);
			$( '.buttons .remove_sel', wrapper ).attr('disabled', true);
		}
	}
	
	// Buttons
	$( '.p2p-relationships-wrapper .buttons .select_all' ).click (function () {
		
		wrapper = $(this).closest('.p2p-relationships-wrapper');
		
		$( '.p2p-relationships-chooser input', wrapper ).prop('checked', true);
		
		refresh_buttons ( wrapper );
		return false;
	});


	$( '.p2p-relationships-wrapper .buttons .unselect_all' ).click (function () {
		
		wrapper = $(this).closest('.p2p-relationships-wrapper');
		
		$( '.p2p-relationships-chooser input', wrapper ).prop('checked', false);
		
		refresh_buttons ( wrapper );
		return false;
	});
	
	
	$( '.p2p-relationships-wrapper .buttons .add_sel' ).click (function () {
		
		wrapper = $(this).closest('.p2p-relationships-wrapper');
		
		$( '.p2p-relationships-chooser input:checked', wrapper ).each( function( index, el) {
			
			add_element( el );
		});
		
		$( '.p2p-relationships-chooser input', wrapper ).prop('checked', false);

		update_relationships ( wrapper );
		return false;
	});
	
	
	$( '.p2p-relationships-wrapper .buttons .remove_sel' ).click (function () {
		
		wrapper = $(this).closest('.p2p-relationships-wrapper');
		
		$( '.p2p-relationships-chooser input:checked', wrapper ).each( function( index, el) {
			
			remove_element( el );
		});
		
		$( '.p2p-relationships-chooser input', wrapper ).prop('checked', false);

		update_relationships ( wrapper );
		return false;
	});

});