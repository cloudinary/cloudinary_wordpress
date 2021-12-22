import CodeMirror from 'codemirror/lib/codemirror';
import 'codemirror/addon/fold/brace-fold';
import 'codemirror/addon/fold/foldgutter';
import 'codemirror/mode/javascript/javascript';

import 'codemirror/lib/codemirror.css';
import 'codemirror/addon/fold/foldgutter.css';
import 'codemirror/theme/material.css';

const SyntaxHighlight = {
	init() {
		document.addEventListener('DOMContentLoaded', function () {
			if (undefined === CLD_METADATA) {
				return;
			}

			const block = document.getElementById('meta-data');

			const instance = CodeMirror(
				block,
				{
					value: JSON.stringify(CLD_METADATA, null, '  '),
					lineNumbers: true,
					theme: 'material',
					readOnly: true,
					mode: {
						name: 'javascript',
						json: true,
					},
					matchBrackets: true,
					foldGutter: true,
					htmlMode: true,
					gutters: ['CodeMirror-linenumbers', 'CodeMirror-foldgutter'],
					viewportMargin: 50,
				}
			);

			instance.setSize( null, 600 );
		});
	}
};

SyntaxHighlight.init();

export default SyntaxHighlight;
