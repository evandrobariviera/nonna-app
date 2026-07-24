import Alpine from 'alpinejs';
import { registerRichEditor } from './tiptap-editor.js';
import { registerDossierEditor } from './dossier-editor.js';
import { registerTaskBulk } from './task-bulk.js';
import { registerDropdownPosition, registerCloseOnScroll } from './dropdown-position.js';
import { registerKanbanDnd } from './kanban-dnd.js';
import { registerSidePanel } from './side-panel.js';

window.Alpine = Alpine;

registerRichEditor();
registerDossierEditor();
registerTaskBulk();
registerDropdownPosition();
registerCloseOnScroll(Alpine);
registerKanbanDnd();
registerSidePanel(Alpine);

Alpine.start();
