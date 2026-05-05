import { json } from "@codemirror/lang-json";
import { EditorState } from '@codemirror/state';
import { oneDark } from '@codemirror/theme-one-dark';
import { EditorView, basicSetup } from 'codemirror';

const SyntaxHighlight = {
	init() {
		// Configure the editor for read-only JSON display.
		const extensions = [
			basicSetup, // Basic CodeMirror editor setup.
			json(), // Enable JSON language syntax highlighting.
			EditorState.readOnly.of( true ), // Make the editor read-only.
			oneDark, // Apply dark theme.

			EditorView.theme({
				'&': {
					height: '600px', // Define the height of the editor.
				},
			}),
		];

		// Initialize the editor when the DOM is fully loaded.
		document.addEventListener('DOMContentLoaded', function () {
			if (undefined === CLD_METADATA) {
				return;
			}

			const parent = document.getElementById('meta-data');
			const doc = JSON.stringify(CLD_METADATA, null, '  ');

			new EditorView({ parent, doc, extensions });
		} );
	}
};

SyntaxHighlight.init();

export default SyntaxHighlight;
