<?php

//overwrites the internal WordPress Editors
class _WP_Editors
{
	public static function editor($content, $editor_id, $settings)
	{

		echo <<<HTML
<div id="$editor_id-container" class="wp-editor-container">
	<div>
		<div id="$editor_id-wysiwyg" style="min-height: 300px;" class="poststuff">$content</div>
	</div>
	<div>
		<textarea id="$editor_id"></textarea>
	</div>
	<input type="button" class="button" value="Toggle Editor" id="$editor_id-button"/>
	<script>
		jQuery(function() {
			var visual = document.getElementById('$editor_id-wysiwyg'),
				source = document.getElementById('$editor_id'),
				button = document.getElementById('$editor_id-button'),
				visualParentStyle = visual.parentNode.style,
				sourceParentStyle = source.parentNode.style;

			wikiLingoEditor(visual, source);
			sourceParentStyle.display = 'none';

			button.onclick = function() {
				if (sourceParentStyle.display === 'none') {
					sourceParentStyle.display = '';
					visualParentStyle.display = 'none';
				} else {
					sourceParentStyle.display = 'none';
					visualParentStyle.display = '';
				}
			};

		});
	</script>
</div>
HTML;

	}
}

global $_updated_user_settings;
$_updated_user_settings['editor_expand'] = 'off';