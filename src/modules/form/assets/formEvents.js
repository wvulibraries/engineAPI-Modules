var formBuilder = {
	initialized: false,
	disabledFields: {},

	init: function(){
		if(this.initialized) return;
		this.initialized = true;

		this.$form      = $('.editTable form');
		this.$editTable = $('.editTable table');

		// Pull ajaxHandlerURL from the <form> tag (default to window.location)
		this.ajaxURL = this.$form.data('ajax_url');
		if(typeof(this.ajaxURL) == 'undefined') this.ajaxURL = window.location;

		// Pull insertFormCallback from the <form> tag (default to __insertFormCallback)
		this.insertFormCallback = this.$form.data('insert_form_callback');
		if(typeof(this.insertFormCallback) == 'undefined') this.insertFormCallback = this.__insertFormCallback;

		// Setup event handlers
		this.$editTable.on('click', '.icon-expand', this.hideForm);
		this.$editTable.on('click', '.icon-collapse', this.showForm);
		this.$editTable.on('click', '.icon-trash', this.deleteRow);

	},

	showForm: function(e){
		e.stopPropagation();
		var $this = $(this);
		var rowID = $this.parents('[data-row_id]').data('row_id');
		var $target = $($this.data('target'));
		var ajaxData = {
			'formID': formBuilder.$form.find('input[name="__formID"]').val(),
			'rowID': rowID
		};

		// Register our 'form submission' handle
		$target.on('click', ':submit', formBuilder.submitForm);

		formBuilder.insertFormCallback(formBuilder.ajaxURL, ajaxData, $target);
		$target.closest('.expandable').data('row_id', rowID).slideDown();
		$this.removeClass('icon-collapse').addClass('icon-expand');
		formBuilder.$form.find(':submit:last').attr('disabled','disabled');
	},
	hideForm: function(e){
		e.stopPropagation();
		var $this = $(this);
		var $target = $($this.data('target'));
		$target.closest('.expandable').slideUp(function(){
			$this.removeClass('icon-expand').addClass('icon-collapse');
			if(!formBuilder.$form.find('.icon-expand').length) formBuilder.$form.find(':submit:last').removeAttr('disabled');
		});
	},
	submitForm: function(e){
		e.preventDefault();
		e.stopPropagation();
		var $submit = $(this);
		var $form = $submit.parents('.insertUpdateForm');
		var $formInputs = $form.find(':input');
		$.post(this.ajaxURL, $formInputs.serializeArray(), function(data, textStatus, jqXHR){
			if(data.success){
				var rowID = $form.closest('.expandable').data('row_id');
				var $editStrip = $('[data-row_id='+rowID+']');

				$formInputs.each(function(i,n){
					$updateField = $(n);
					$editStrip.find(':input[name^="'+$updateField.attr('name')+'['+rowID+']"]').val($updateField.val());
				});

				$form.slideUp(function(){
					$form.html(data.prettyPrint).slideDown();
					setTimeout(function(){
						$editStrip.find('.icon-expand').click();
					}, 3000);
				});
			}else{
				if(typeof(console) != 'undefined') console.log('AJAX Error: '+data.errorMsg+"\nError Code: "+data.errorCode);
				$form.prepend(data.prettyPrint);
			}
		}, 'json');
	},
	__insertFormCallback: function(url, data, $target){
		$.getJSON(url, data, function(data, textStatus, jqXHR){
			if(data.success){
				$target.html(data.form);
			}else{
				if(typeof(console) != 'undefined') console.log('AJAX Error: '+data.errorMsg+"\nError Code: "+data.errorCode);
				$target.html('Failed to load form.');
			}
		});
	},

	deleteRow: function(e){
		var $parent = $(this).parents('[data-row_id]');
		var $deletedRowIDs = $('#deletedRowIDs');
		var deletedRowIDs = $deletedRowIDs.val().length
			? $deletedRowIDs.val().split(',')
			: [];
		var rowID = $parent.data('row_id');

		if(-1 == (index = deletedRowIDs.indexOf(rowID))){
			// Delete the row
			if(confirm("Are you sure?")){
				// Delete the row
				var disabledFields = [];
				$parent.find(':input').each(function(i,field){
					var $field = $(field);
					if(!$field.is(':disabled')){
						disabledFields.push($field);
						$field.attr('disabled','disabled');
					}
				});
				formBuilder.disabledFields[rowID] = disabledFields;
				deletedRowIDs.push(rowID);
			}
		}else{
			// un-Delete the row
			$.each(formBuilder.disabledFields[rowID], function(i,$field){
				$field.removeAttr('disabled');
			});
			delete formBuilder.disabledFields[rowID];
			deletedRowIDs.splice(index, 1);
		}
		$deletedRowIDs.val( deletedRowIDs.join() );
	}
};


if (typeof jQuery == 'undefined') {
	alert("JavaScript Error!\nMissing dependency!");
	if (typeof console != 'undefined') console.log("jQuery dependency not met! (jQuery is required for formEvents.js)");
}else{
	$(function(){ formBuilder.init(); });
}
