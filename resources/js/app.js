import Alpine from 'alpinejs';
import { registerRichEditor } from './tiptap-editor.js';
import { registerDossierEditor } from './dossier-editor.js';

window.Alpine = Alpine;

registerRichEditor();
registerDossierEditor();

Alpine.start();
