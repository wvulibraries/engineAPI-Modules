var formBuilder = {
	initialized: false,
	init: function(){
		if(this.initialized) return;
		this.initialized = true;

		this.$form      = $('.editTable form');
		this.$editTable = $('.editTable table');

		// Pull insertFormURL from the <form> tag (default to window.location)
		this.insertFormURL = this.$form.data('insert_form_url');
		if(typeof(this.insertFormURL) == 'undefined') this.insertFormURL = window.location;

		// Pull insertFormCallback from the <form> tag (default to __insertFormCallback)
		this.insertFormCallback = this.$form.data('insert_form_callback');
		if(typeof(this.insertFormCallback) == 'undefined') this.insertFormCallback = this.__insertFormCallback;

		// Setup event handlers
		this.$editTable.on('click', '.icon-expand', this.hideForm);
		this.$editTable.on('click', '.icon-collapse', this.showForm);
		this.$editTable.on('change', 'iframe', this.updateIFrame);
	},

	showForm: function(e){
		e.stopPropagation();
		var $this = $(this);
		var rowID = $this.parents('[data-row_id]').data('row_id');
		var iFrame = $($this.data('target_iframe'));
		var ajaxData = {
			'formID': formBuilder.$form.find('input[name="__formID"]').val(),
			'rowID': rowID
		};

		formBuilder.insertFormCallback(formBuilder.insertFormURL, ajaxData, iFrame);
		iFrame.closest('.expandable').slideDown();
		$this.removeClass('icon-collapse').addClass('icon-expand');
	},
	hideForm: function(e){
		e.stopPropagation();
		var $this = $(this);
		var iFrame = $($this.data('target_iframe'));

		iFrame.closest('.expandable').slideUp();
		$this.removeClass('icon-expand').addClass('icon-collapse');
	},
	updateIFrame:function(e){
		var $iframe = $(this);

		// Set the height of the iframe to the height of the content
		setTimeout(function() {
			$iframe.height($iframe.contents().find('.formBuilder').height()+60);
		},50);
	},
	__insertFormCallback: function(url, data, iframe){
		return $.getJSON(url, data, function(data, textStatus, jqXHR){
			if(data.success){
				iframe.attr('srcdoc',data.form).change();
			}else{
				if(typeof(console) != 'undefined') console.log('AJAX Error: '+data.errorMsg+"\nError Code: "+data.errorCode);
				$formTarget.html('Failed to load form.');
			}
		});
	}
};


if (typeof jQuery == 'undefined') {
	alert("JavaScript Error!\nMissing dependency!");
	if (typeof console != 'undefined') console.log("jQuery dependency not met! (jQuery is required for formEvents.js)");
}else{
	$(function(){ formBuilder.init(); });
}

