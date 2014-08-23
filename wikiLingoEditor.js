var wikiLingoEditor = (function($) {
	"use strict";

	var
		WLPlugin = function(el, url) {
			if (el.getAttribute('data-draggable') == 'true') {
				new WLPluginAssistant(el, url);
			}
		},
		color = function(element) {
			var newColor = prompt('What color?', element.style['color']);
			if (newColor) {
				element.style['color'] = newColor
			}
		},
		table = function(element) {

		};

	return function(reflectUrl, folderUrl,  editable, editableSource) {


		//bubble is the contenteditable toolbar, it is very simple and instantiated here
		var
		//medium makes contenteditable behave
			medium = editable.medium = new Medium({
				element: editable,
				mode: 'rich',
				placeholder: 'Content',
				autoHR: false,
				attributes: {
					remove: []
				},
				tags: {
					paragraph: 'p',
					outerLevel: null,
					innerLevel: null
				},
				modifiers: [],
				beforeInvokeElement: function() {
					console.log(this);
				},
				beforeInsertHtml: function() {
					console.log(this);
				},
				beforeAddTag: function(tag, shouldFocus, isEditable, afterElement) {
					var newEl;
					switch (tag) {
						case 'br':
						case 'p':
							newEl = document.createElement('br');
							newEl.setAttribute('class', 'element');
							newEl.setAttribute('data-element', 'true');
							newEl.setAttribute('data-type', 'WikiLingo\\\\Expression\\\\Line');

							this.insertHtml(newEl);
							return true;
					}

					return newEl;
				}
			}),
			bubble = new WLBubble(window.expressionSyntaxes, editable),
			codemirror = CodeMirror.fromTextArea(editableSource, {
				mode: 'wikiLingo',
				lineNumbers: false,
				readOnly: false,
				lineWrapping: true,
				scroll: false
			}),
			settingSource = false,
			updateSource = function() {
				$.ajax({
					type: 'POST',
					dataType: 'json',
					url: reflectUrl,
					data: {w: editable.innerHTML, reflect:'WYSIWYGWikiLingo'},
					success: function(result) {
						settingSource = true;
						codemirror.setValue(result.output);
						editableSource.value = result.output;
						settingSource = false;
					}
				});
			},
			updateWYSIWYG = function() {
				$.ajax({
					type: 'POST',
					dataType: 'json',
					url: reflectUrl,
					data: {w: codemirror.getValue(), reflect:'wikiLingoWYSIWYG'},
					success: function(result) {
						editable.innerHTML = result.output;
						window.wLPlugins = result.plugins;

						$('body')
							.append(result.css)
							.append(result.script)
							.trigger('resetWLPlugins');
					}
				});
			};

		codemirror.on('change', function() {
			if (!settingSource) {
				updateWYSIWYG();
			}
		});

		$(editable)
			.on('mouseup', function(event) {
				if (document.activeElement === this) {
					bubble.goToSelection();
				}
			})
			.on('focus', function() {
				this.before = this.innerHTML;
				return this;
			})
			.on('blur keyup paste input', function() {
				var me = $(this);
				if (this.before !== this.innerHTML) {
					this.before = this.innerHTML;
					setTimeout(function() {
						me.trigger('change');
					}, 10);
				}
				return this;
			})
			.on('change', updateSource);

		$(editableSource)
			.on('change', updateWYSIWYG);


		$('body')
			.on('resetWLPlugins', function() {
				if (window.wLPlugins !== undefined) {
					for(var i = 0; i < wLPlugins.length; i++) {
						new WLPlugin(document.getElementById(wLPlugins[i]), folderUrl);
					}
				}
			})
			.trigger('resetWLPlugins');


		bubble.staticToTop();

		console.log(window.expressionSyntaxes);
	}
})(jQuery);