import Alpine from 'alpinejs';
import { registerRichEditor } from './tiptap-editor.js';

window.Alpine = Alpine;

registerRichEditor();

Alpine.start();
