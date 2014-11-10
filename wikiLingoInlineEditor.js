var wikiLingoInlineEditor;
wikiLingoInlineEditor = (function ($) {
    "use strict";

    return function (editButton, menu, articleId, editable, siteUrl) {

        var saveButton = $('<span class="save-link"><a href="#">Save</a></span>')
                .appendTo(menu),

            cancelButton = $('<span class="cancel-link"><a href="#">Cancel</a></span>')
                .appendTo(menu),

            sourceEditorContainer = $('<div id="editableSource">')
                .insertAfter(editable),

            editableAreaParent = $('<div id="editable">')
                .insertAfter(editable)
                .append(editable),

            editableSource = $('<textarea name="content"></textarea>')
                .appendTo(sourceEditorContainer),

            toggleEditorButton = $('<input type="button" class="button" value="Toggle Editor"/>')
                .insertAfter(sourceEditorContainer),

            reflectUrl = siteUrl + '/wp-content/plugins/wikiLingo/wikiLingoReflect.php',
            folderUrl = siteUrl + '/wp-content/plugins/wikiLingo/vendor/wikilingo/wikilingo/',

            editor = wikiLingoEditor(reflectUrl, folderUrl, editable, editableSource[0]),

            wikiLingoBubbles = $('nav.wikiLingo-bubble');


        editButton.hide();

        saveButton.click(function () {
            saveButton.hide();
            cancelButton.hide();
            editButton.show();
        });

        cancelButton.click(function () {
            cancelButton.hide();
            saveButton.hide();
            editButton.show();
        });


        toggleEditorButton.click(function () {
            if (sourceEditorContainer.is(':visible')) {
                $.ajax({
                    type: 'POST',
                    dataType: 'json',
                    url: siteUrl + '/wp-content/plugins/wikiLingo/wikiLingoReflect.php',
                    data: {w: editableSource.innerHTML, reflect: 'WYSIWYGWikiLingo'},
                    success: function (result) {
                        editable.html = result.output;
                        window.wLPlugins = result.plugins;
                        sourceEditorContainer.hide();
                        editableAreaParent.show();
                        wikiLingoBubbles.hide();
                    }
                });
            } else {
                $.ajax({
                    type: 'POST',
                    dataType: 'json',
                    url: siteUrl + '/wp-content/plugins/wikiLingo/wikiLingoReflect.php',
                    data: {w: editable.innerHTML, reflect: 'wikiLingoWYSIWYG'},
                    success: function (result) {
                        editableSource.html = result.output;
                        window.wLPlugins = result.plugins;
                        sourceEditorContainer.show();
                        editableAreaParent.hide();
                        wikiLingoBubbles.show();
                    }
                });

            }

            sourceEditorContainer.hide();

        });
    }
})(jQuery);