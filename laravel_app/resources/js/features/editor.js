import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import { TaskList } from '@tiptap/extension-task-list';
import { TaskItem } from '@tiptap/extension-task-item';

/**
 * ツールバーのボタン定義。
 * command は Tiptap のチェーン、active は現在の状態判定に使う。
 */
const ACTIONS = {
    bold: { command: (c) => c.toggleBold(), active: 'bold' },
    italic: { command: (c) => c.toggleItalic(), active: 'italic' },
    strike: { command: (c) => c.toggleStrike(), active: 'strike' },
    code: { command: (c) => c.toggleCode(), active: 'code' },
    h2: { command: (c) => c.toggleHeading({ level: 2 }), active: ['heading', { level: 2 }] },
    h3: { command: (c) => c.toggleHeading({ level: 3 }), active: ['heading', { level: 3 }] },
    bulletList: { command: (c) => c.toggleBulletList(), active: 'bulletList' },
    orderedList: { command: (c) => c.toggleOrderedList(), active: 'orderedList' },
    taskList: { command: (c) => c.toggleTaskList(), active: 'taskList' },
    blockquote: { command: (c) => c.toggleBlockquote(), active: 'blockquote' },
    codeBlock: { command: (c) => c.toggleCodeBlock(), active: 'codeBlock' },
    horizontalRule: { command: (c) => c.setHorizontalRule(), active: null },
    undo: { command: (c) => c.undo(), active: null },
    redo: { command: (c) => c.redo(), active: null },
};

function setLink(editor) {
    const previous = editor.getAttributes('link').href ?? '';
    const url = window.prompt('リンク先の URL を入力してください', previous);

    if (url === null) {
        return;
    }

    if (url === '') {
        editor.chain().focus().unsetLink().run();

        return;
    }

    editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
}

export function createEditor(root) {
    const input = root.querySelector('[data-editor-input]');
    const mount = root.querySelector('[data-editor-content]');
    const buttons = [...root.querySelectorAll('[data-editor-action]')];

    const editor = new Editor({
        element: mount,
        extensions: [
            StarterKit.configure({ link: false }),
            TaskList,
            TaskItem.configure({ nested: true }),
            Link.configure({ openOnClick: false, autolink: true }),
            Placeholder.configure({ placeholder: root.dataset.placeholder ?? '' }),
        ],
        content: input.value,
        editorProps: {
            attributes: {
                class: 'prose-editor focus:outline-none',
                'aria-label': '内容',
            },
        },
        // フォーム送信時に最新の HTML が hidden input に入っている状態を保つ
        onUpdate: ({ editor }) => {
            input.value = editor.isEmpty ? '' : editor.getHTML();
        },
        onSelectionUpdate: () => refresh(),
        onTransaction: () => refresh(),
    });

    function refresh() {
        buttons.forEach((button) => {
            const action = ACTIONS[button.dataset.editorAction];

            if (!action?.active) {
                return;
            }

            const isActive = Array.isArray(action.active)
                ? editor.isActive(...action.active)
                : editor.isActive(action.active);

            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-pressed', String(isActive));
        });
    }

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            if (button.dataset.editorAction === 'link') {
                setLink(editor);

                return;
            }

            const action = ACTIONS[button.dataset.editorAction];
            action?.command(editor.chain().focus()).run();
        });
    });

    refresh();

    return editor;
}

export function bootEditors() {
    document.querySelectorAll('[data-editor]').forEach(createEditor);
}
