<?php

//overwrites the internal WordPress Editors
class _WP_Editors
{
	public static function editor($content, $editor_id, $settings)
	{
		$wysiwygParser = new WikiLingoWYSIWYG\Parser(null);
		$wysiwygContent = $wysiwygParser->parse($content);

		$siteUrl = get_site_url();

		$scripts = $wysiwygParser->scripts->renderCss() . $wysiwygParser->scripts->renderScript();
		echo <<<HTML
<div id="$editor_id-container" class="wp-editor-container">
	<div>
		<div id="$editor_id-wysiwyg" style="min-height: 300px;" class="poststuff">$wysiwygContent</div>
	</div>
	<div>
		<textarea id="$editor_id" name="content">$content</textarea>
	</div>
	<input type="button" class="button" value="Toggle Editor" id="$editor_id-button"/>
	<script>
		var $ = jQuery;
		(function($, document) {
			$(function() {
				var visual = document.getElementById('$editor_id-wysiwyg'),
					source = document.getElementById('$editor_id'),
					button = document.getElementById('$editor_id-button'),
					reflectUrl = '$siteUrl/wp-content/plugins/wikiLingo/wikiLingoReflect.php',
					folderUrl = '$siteUrl/wp-content/plugins/wikiLingo/vendor/wikilingo/wikilingo/',
					editor = wikiLingoEditor(reflectUrl, folderUrl, visual, source),
					visualParentStyle = visual.parentNode.style,
					sourceParentStyle = source.parentNode.style,
					wikiLingoBubbles = $('nav.wikiLingo-bubble');

				sourceParentStyle.display = 'none';

				button.onclick = function() {
					if (sourceParentStyle.display === 'none') {
						sourceParentStyle.display = '';
						visualParentStyle.display = 'none';
						wikiLingoBubbles.hide();
					} else {
						sourceParentStyle.display = 'none';
						visualParentStyle.display = '';
						wikiLingoBubbles.show();
					}
				};

			});
		})(jQuery, document);
	</script>
	$scripts
</div>
HTML;

	}
}

global $_updated_user_settings;
$_updated_user_settings['editor_expand'] = 'off';