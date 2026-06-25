import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import Underline from '@tiptap/extension-underline';
import TextAlign from '@tiptap/extension-text-align';

function normalizeContent(content) {
    if (!content) return '';
    if (/<[a-z][\s\S]*>/i.test(content)) return content;
    return content
        .split(/\n\n+/)
        .map(p => `<p>${p.replace(/\n/g, '<br>')}</p>`)
        .join('') || `<p>${content}</p>`;
}

export function registerRichEditor() {
    Alpine.data('richEditor', (initialContent = '') => ({
        editor: null,
        tick: 0, // reactive counter — forces Alpine to re-evaluate active() on every editor change

        init() {
            const el    = this.$refs.editor;
            const input = this.$refs.input;
            const self  = this;
            const html  = normalizeContent(initialContent);

            input.value = html;

            this.editor = new Editor({
                element: el,
                extensions: [
                    StarterKit.configure({
                        heading:   { levels: [2, 3] },
                        codeBlock: false,
                        code:      false,
                    }),
                    Underline,
                    Link.configure({
                        openOnClick: false,
                        HTMLAttributes: { target: '_blank', rel: 'noopener noreferrer' },
                    }),
                    Image.configure({ inline: false, allowBase64: true }),
                    TextAlign.configure({ types: ['heading', 'paragraph'] }),
                ],
                content: html,
                editorProps: {
                    attributes: { class: 'rich-prose' },
                    handlePaste(view, event) {
                        const items = Array.from(event.clipboardData?.items ?? []);
                        const img   = items.find(i => i.type.startsWith('image/'));
                        if (!img) return false;
                        event.preventDefault();
                        const reader = new FileReader();
                        reader.onload = e => self.editor.chain().focus().setImage({ src: e.target.result }).run();
                        reader.readAsDataURL(img.getAsFile());
                        return true;
                    },
                    handleDrop(view, event) {
                        const files = Array.from(event.dataTransfer?.files ?? []).filter(f => f.type.startsWith('image/'));
                        if (!files.length) return false;
                        event.preventDefault();
                        files.forEach(file => {
                            const reader = new FileReader();
                            reader.onload = e => self.editor.chain().focus().setImage({ src: e.target.result }).run();
                            reader.readAsDataURL(file);
                        });
                        return true;
                    },
                },
                onUpdate({ editor }) {
                    input.value = editor.getHTML();
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    self.tick++;
                },
                onSelectionUpdate() {
                    // cursor moved — toolbar active states need to refresh
                    self.tick++;
                },
            });

            this.$cleanup(() => this.editor?.destroy());
        },

        // Reading this.tick makes Alpine track it as a dependency, so active()
        // re-evaluates whenever the editor state changes.
        active(type, opts = {}) {
            void this.tick;
            return this.editor?.isActive(type, opts) ?? false;
        },

        activeAlign(align) {
            void this.tick;
            return this.editor?.isActive({ textAlign: align }) ?? false;
        },

        run(cmd) {
            if (!this.editor) return;
            const c = this.editor.chain().focus();
            switch (cmd) {
                case 'bold':       c.toggleBold().run();                break;
                case 'italic':     c.toggleItalic().run();              break;
                case 'underline':  c.toggleUnderline().run();           break;
                case 'strike':     c.toggleStrike().run();              break;
                case 'h2':         c.toggleHeading({ level: 2 }).run(); break;
                case 'h3':         c.toggleHeading({ level: 3 }).run(); break;
                case 'ul':         c.toggleBulletList().run();          break;
                case 'ol':         c.toggleOrderedList().run();         break;
                case 'blockquote': c.toggleBlockquote().run();          break;
                case 'left':       c.setTextAlign('left').run();        break;
                case 'center':     c.setTextAlign('center').run();      break;
                case 'right':      c.setTextAlign('right').run();       break;
                case 'hr':         c.setHorizontalRule().run();         break;
                case 'undo':       c.undo().run();                      break;
                case 'redo':       c.redo().run();                      break;
                case 'link':       this.setLink();                      break;
                case 'image':      this.$refs.fileInput.click();        break;
            }
        },

        setLink() {
            const prev = this.editor.getAttributes('link').href ?? '';
            const url  = window.prompt('URL do link:', prev);
            if (url === null) return;
            url === ''
                ? this.editor.chain().focus().unsetLink().run()
                : this.editor.chain().focus().setLink({ href: url }).run();
        },

        onFileChange(event) {
            const file = event.target.files[0];
            if (!file) return;
            event.target.value = ''; // reset so same file can be picked again
            const reader = new FileReader();
            reader.onload = e => this.editor.chain().focus().setImage({ src: e.target.result }).run();
            reader.readAsDataURL(file);
        },
    }));
}
