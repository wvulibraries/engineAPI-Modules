/**
 * MultiText Plugin
 * -------------------------------------------------------------------------------------
 * Created for Library Use for EngineAPI - https://github.com/wvulibraries/engineAPI
 * Author  : David J. Davis
 * Date    : 4-4-2016
 * Version : 0.0.1
 */

;(function($, window, document, undefined) {
    // default values for plugin
	var multiText = "multiText", defaults = {};
	var self;

    function multiTextPlugin(element, options) {
		this.element        = element;
		self                = this; // fixes event loop this error
		this.settings       = $.extend({}, defaults, options);
		this._name          = multiText;
        this.init();
    }

    $.extend(multiTextPlugin.prototype, {
        // initialization plugin
        init: function() {
        	// Build Iniital HTML
        	this.buildSVG();

        	// If there aren't any initial instances of the multi-select
        	if($('.initial-multiText').length === 0){
        		$(defaults.element).append(this.multiTextHTML('initial'));
        	}

            // check for checked boxes that were manually checked by html
            this.checkActive();
            
        	// addEvents
        	this.addEvents();
        },

        buildSVG: function(){
        	// create svg
        	var svg = '<svg class="defs-only" xmlns="http://www.w3.org/2000/svg" width="0" height="0"><defs>';
        	// define plus sign path
        	svg += '<g id="plus"> <path class="plus" d="M11 5.75v1.5q0 .312-.22.53t-.53.22H7v3.25q0 .312-.22.53t-.53.22h-1.5q-.312 0-.53-.22T4 11.25V8H.75q-.312 0-.53-.22T0 7.25v-1.5q0-.312.22-.53T.75 5H4V1.75q0-.312.22-.53T4.75 1h1.5q.312 0 .53.22t.22.53V5h3.25q.312 0 .53.22t.22.53z" /> </g>';
        	// define check path
        	svg += '<g id="check"><path class="check" d="M13.055 4.422q0 .312-.22.53l-6.718 6.72q-.22.22-.53.22t-.532-.22l-3.89-3.89q-.22-.22-.22-.532t.22-.53l1.06-1.063q.22-.22.532-.22t.53.22l2.298 2.305L10.71 2.83q.22-.22.53-.22t.532.22l1.062 1.06q.22.22.22.532z" /></g>';
        	// define close sign path
			svg += '<g id="close"><path class="close" d="M10.14 10.328q0 .312-.218.53L8.86 11.922q-.22.22-.53.22t-.532-.22L5.5 9.625 3.205 11.92q-.22.22-.53.22t-.532-.22L1.08 10.86q-.22-.22-.22-.532t.22-.53L3.377 7.5 1.08 5.203q-.22-.22-.22-.53t.22-.532l1.062-1.06q.22-.22.53-.22t.532.22L5.5 5.375 7.8 3.08q.22-.22.53-.22t.532.22l1.062 1.06q.22.22.22.532t-.22.53L7.625 7.5l2.297 2.297q.22.22.22.53z" /></g>';
			// close svg definitions
			svg += '</defs></svg>';

			$('body').append(svg);
        },

        multiTextHTML: function(objClass){
            var name = this.settings.name;
            var count = $('.multi-text-container').length;
        	var html = '<div class="multi-text-container '+objClass+'">';
        			// build default button / checkbox
	        		html += '<label class="multi-text-label"><input type="checkbox" class="default-choice-checkbox" name="'+name+'[default]['+count+']" value="'+count+'">';
	      			html += '<span class="default-choice">';
	      			html += '<svg class="icon"><use xlink:href="#check"/></svg>';
	      			html += '</span></label>'
	      			// input text box
	      			html += '<input name="'+name+'[value][]" class="input-element" type="text" data-default="false">';
	      			// add button
	      			html += '<button name="add" class="add-choice" type="button" title="Add a choice."><svg class="icon"><use xlink:href="#plus"/></svg></button>';
	      			// remove button
					html += '<button name="remove" class="remove-choice" type="button" title="Remove this choice."><svg class="icon" viewBox="0 0 20 20"><use xlink:href="#close"/></svg></button>';
			html += '</div>';

			return html;
        },

        checkActive:function(){
            $('.default-choice-checkbox').each(function(){
                if(this.checked){
                	$(this).next('span').addClass('active');
                } else {
                	$(this).next('span').removeClass('active');
                }
            });
        },

        addEvents:function(){
        	$('.default-choice-checkbox').click(this.defaultChoice);
            $('.add-choice').click(this.addNewMultiText);
            $('.remove-choice').click(this.removeThisMultiText);
            return this;
        },

        removeEvents:function(){
        	$('.default-choice-checkbox,  .add-choice, .remove-choice').unbind('click');
        	return this;
        },

        resetEvents:function(){
        	this.removeEvents();
        	this.addEvents();
        },

        defaultChoice: function(){
        	event.stopPropagation();
            if(this.checked){
            	$(this).next('span').addClass('active');
            } else {
            	$(this).next('span').removeClass('active');
            }
        },

        addNewMultiText: function(event){
        	event.stopPropagation();
        	var html = self.multiTextHTML('');
        	$(html).appendTo($(self.element));
        	self.resetEvents();
        },

        removeThisMultiText: function(event){
        	event.stopPropagation();
        	numOfMultiTexts = $('.multi-text-container').length;

        	if(numOfMultiTexts > 1){
        		$(this).parent().remove();
        		self.resetEvents();
        	} else {
        		alert('Warning: We can not remove the last multiText element or future functionality will be broken.');
        	}
        }
    });

    // prevent multiple instantiations
    $.fn[multiText] = function(options) {
        return this.each(function() {
            if (!$.data(this, "plugin_" + multiText)) {
                $.data(this, "plugin_" +
                    multiText, new multiTextPlugin(this, options));
            }
        });
    };

})(jQuery, window, document);
