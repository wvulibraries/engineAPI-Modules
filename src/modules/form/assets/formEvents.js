function iframeLoaded() {
	var iFrameID = document.getElementById('idIframe');
	if(iFrameID) {
		// here you can make the height, I delete it first, then I make it again
		iFrameID.height = "";
		iFrameID.height = iFrameID.contentWindow.document.body.scrollHeight + "px";
	}
}
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
		this.$form.on('submit', this.submitForm);
		this.$editTable.on('click', '.icon-expand', this.hideForm);
		this.$editTable.on('click', '.icon-collapse', this.showForm);
		this.$editTable.on('click', '.deleteRow', this.deleteRow);
	},

	iFrameLoaded: function(iFrame){
		console.log(iFrame)
		// here you can make the height, I delete it first, then I make it again
		iFrame.height = "";
		iFrame.height = iFrame.contentWindow.document.body.scrollHeight + "px";
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
		$target.on('click', ':submit', formBuilder.submitExpandedForm);

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
			$target.closest('.expandable').html('');
		});
	},
	submitExpandedForm: function(e){
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
					var $updateField = $(n);
					var fieldName    = $updateField.attr('name');
					var value        = $updateField.attr('value');
					if($updateField.is(':visible') && fieldName){
						fieldName = fieldName.replace(/\[|\]/g, '');
						if($updateField.is(':checkbox,:radio')){
							var $target = $editStrip.find(':input[name^="'+fieldName+'['+rowID+']"][value="'+value+'"]');
							if($updateField.is(':checked')){
								$target.attr('checked','checked');
							}else{
								$target.removeAttr('checked');
							}
						}else{
							$editStrip.find(':input[name^="'+fieldName+'['+rowID+']"]').val($updateField.val());
						}
					}
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
	submitForm: function(e){
		var deletedRows = $('.deleteRow:checked').length;
		if(deletedRows){
			if(!confirm("You have marked "+deletedRows+" rows for deletion. Are you sure you want to proceed?")){
				e.preventDefault();
				e.stopPropagation();
			}
		}
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
		var $chkBox = $(this);
		var $parent = $chkBox.parents('[data-row_id]');
		var rowID = $parent.data('row_id');

		if($chkBox.is(':checked')){
			// Mark row for deletion
			var disabledFields = [];
			$parent.find(':input').not($chkBox).each(function(i,field){
				var $field = $(field);
				if(!$field.is(':disabled')){
					disabledFields.push($field);
					$field.attr('disabled','disabled');
				}
			});
			formBuilder.disabledFields[rowID] = disabledFields;
		}else{
			// Un-mark row for deletion
			$.each(formBuilder.disabledFields[rowID], function(i,$field){
				$field.removeAttr('disabled');
			});
			delete formBuilder.disabledFields[rowID];
		}
	}
};


if (typeof jQuery == 'undefined') {
	alert("JavaScript Error!\nMissing dependency!");
	if (typeof console != 'undefined') console.log("jQuery dependency not met! (jQuery is required for formEvents.js)");
}else{
	$(function(){ formBuilder.init(); });
}
