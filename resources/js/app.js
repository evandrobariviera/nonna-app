import Alpine from 'alpinejs';
import { registerRichEditor } from './tiptap-editor.js';
import { registerDossierEditor } from './dossier-editor.js';
import { registerTaskBulk } from './task-bulk.js';
import { registerDropdownPosition, registerCloseOnScroll } from './dropdown-position.js';
import { registerKanbanDnd } from './kanban-dnd.js';
import { registerSidePanel } from './side-panel.js';
import { registerLiveFilter } from './live-filter.js';
import { registerConfirmDialog } from './confirm-dialog.js';

window.Alpine = Alpine;

registerRichEditor();
registerDossierEditor();
registerTaskBulk();
registerDropdownPosition();
registerCloseOnScroll(Alpine);
registerKanbanDnd();
registerSidePanel(Alpine);
registerLiveFilter();
registerConfirmDialog(Alpine);

Alpine.start();
