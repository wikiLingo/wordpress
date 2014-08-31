var wikiLingoInlineEditor = (function($) {
	"use strict";

	return function(articleId, editableArea, siteUrl) {
		var sourceEditorContainer = $('<div>')
				.insertAfter(editableArea),

			editableAreaParent = $('<div>')
				.insertAfter(editableArea)
				.append(editableArea),

			sourceEditor = $('<textarea name="content"></textarea>')
				.appendTo(sourceEditorContainer),

			button = $('<input type="button" class="button" value="Toggle Editor"/>')
				.insertAfter(sourceEditorContainer),

			reflectUrl = siteUrl + '/wp-content/plugins/wikiLingo/wikiLingoReflect.php',
			folderUrl = siteUrl + '/wp-content/plugins/wikiLingo/vendor/wikilingo/wikilingo/',

			editor = wikiLingoEditor(reflectUrl, folderUrl, editableArea, sourceEditor[0]),

			wikiLingoBubbles = $('nav.wikiLingo-bubble');

		button.click(function() {
			if (sourceEditorContainer.is(':visible')) {
				sourceEditorContainer.hide();
				editableAreaParent.show();
				wikiLingoBubbles.hide();
			} else {
				sourceEditorContainer.show();
				editableAreaParent.hide();
				wikiLingoBubbles.show();
			}
		});

		sourceEditorContainer.hide();
	}
})(jQuery);