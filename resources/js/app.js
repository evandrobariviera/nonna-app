import Alpine from 'alpinejs';
import { registerRichEditor } from './tiptap-editor.js';
import { registerDossierEditor } from './dossier-editor.js';
import { registerTaskBulk } from './task-bulk.js';
import { registerDropdownPosition } from './dropdown-position.js';

window.Alpine = Alpine;

registerRichEditor();
registerDossierEditor();
registerTaskBulk();
registerDropdownPosition();

Alpine.start();
