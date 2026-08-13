import Alpine from 'alpinejs';
import { registerRichEditor } from './tiptap-editor.js';
import { registerDossierEditor } from './dossier-editor.js';
import { registerTaskBulk } from './task-bulk.js';
import { registerDropdownPosition, registerCloseOnScroll } from './dropdown-position.js';
import { registerKanbanDnd } from './kanban-dnd.js';
import { registerSidePanel } from './side-panel.js';
import { registerLiveFilter } from './live-filter.js';
import { registerConfirmDialog } from './confirm-dialog.js';
import { registerInlinePatch } from './inline-patch.js';
import { registerMondayFill } from './monday-fill.js';
import { registerGroupCollapse } from './group-collapse.js';
import { registerBrowserNotify } from './browser-notify.js';

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
registerInlinePatch();
registerMondayFill(Alpine);
registerGroupCollapse(Alpine);
registerBrowserNotify(Alpine);

Alpine.start();
