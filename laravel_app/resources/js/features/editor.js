import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import { TaskList } from '@tiptap/extension-task-list';
import { TaskItem } from '@tiptap/extension-task-item';
import Mention from '@tiptap/extension-mention';
import Image from '@tiptap/extension-image';
import { attachmentUploadUrl, uploadAttachment } from './attachments';

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

/**
 * @メンションの候補。ページに 1 つだけ置かれた JSON（x-mention-candidates）を読む。
 * 置かれていない画面では、メンションそのものを無効にする。
 */
function mentionCandidates() {
    const source = document.querySelector('[data-mention-candidates]');

    if (!source) {
        return null;
    }

    try {
        return JSON.parse(source.textContent);
    } catch {
        return null;
    }
}

/**
 * 候補の一覧（@ を打つと出るポップアップ）。
 *
 * ライブラリ（tippy など）は足さず、カーソル位置に絶対配置した ul で済ませる。
 * 候補を押してもエディタからフォーカスを外さない（外すとインライン編集の自動保存が走る）。
 */
function mentionSuggestion(candidates) {
    return {
        items: ({ query }) => {
            const needle = query.toLowerCase();

            return candidates.filter((user) => user.label.toLowerCase().includes(needle)).slice(0, 8);
        },
        render: () => {
            let list;
            let props;
            let selected = 0;

            const choose = (index) => {
                const item = props.items[index];

                if (item) {
                    props.command({ id: String(item.id), label: item.label });
                }
            };

            const draw = () => {
                list.replaceChildren();

                if (props.items.length === 0) {
                    const empty = document.createElement('li');
                    empty.className = 'mention-empty';
                    empty.textContent = '該当するメンバーがいません';
                    list.append(empty);
                }

                props.items.forEach((item, index) => {
                    const option = document.createElement('li');
                    option.className = 'mention-option';
                    option.setAttribute('role', 'option');
                    option.setAttribute('aria-selected', String(index === selected));
                    option.textContent = item.label;
                    option.addEventListener('mousedown', (event) => {
                        event.preventDefault();
                        choose(index);
                    });
                    list.append(option);
                });

                const rect = props.clientRect?.();

                if (rect) {
                    list.style.left = `${rect.left + window.scrollX}px`;
                    list.style.top = `${rect.bottom + window.scrollY + 4}px`;
                }
            };

            return {
                onStart: (initial) => {
                    props = initial;
                    selected = 0;
                    list = document.createElement('ul');
                    list.className = 'mention-list';
                    list.setAttribute('role', 'listbox');
                    list.setAttribute('aria-label', 'メンションするメンバー');
                    document.body.append(list);
                    draw();
                },
                onUpdate: (next) => {
                    props = next;
                    selected = 0;
                    draw();
                },
                onKeyDown: ({ event }) => {
                    const count = props.items.length;

                    if (event.key === 'ArrowDown' && count > 0) {
                        selected = (selected + 1) % count;
                        draw();

                        return true;
                    }

                    if (event.key === 'ArrowUp' && count > 0) {
                        selected = (selected + count - 1) % count;
                        draw();

                        return true;
                    }

                    if (event.key === 'Enter' || event.key === 'Tab') {
                        choose(selected);

                        return count > 0;
                    }

                    if (event.key === 'Escape') {
                        // 候補を閉じるだけにする。インライン編集の欄まで閉じないよう、外へ伝えない
                        event.stopPropagation();
                        list.remove();

                        return true;
                    }

                    return false;
                },
                onExit: () => list?.remove(),
            };
        },
    };
}

/**
 * 貼り付け・ドロップされたファイルを添付として送り、本文に差し込む。
 * 画像はその場に表示し、それ以外はファイル名のリンクにする。
 */
async function insertFiles(editor, url, files) {
    for (const file of files) {
        try {
            const attachment = await uploadAttachment(url, file);

            const content = attachment.is_image
                ? { type: 'image', attrs: { src: attachment.url, alt: attachment.name } }
                : { type: 'text', text: attachment.name, marks: [{ type: 'link', attrs: { href: attachment.url } }] };

            editor.chain().focus().insertContent(content).run();
        } catch (error) {
            window.alert(error.message);
        }
    }
}

export function createEditor(root) {
    const input = root.querySelector('[data-editor-input]');
    const mount = root.querySelector('[data-editor-content]');
    const buttons = [...root.querySelectorAll('[data-editor-action]')];
    const candidates = mentionCandidates();
    const uploadUrl = attachmentUploadUrl();

    const editor = new Editor({
        element: mount,
        extensions: [
            StarterKit.configure({ link: false }),
            TaskList,
            TaskItem.configure({ nested: true }),
            Link.configure({ openOnClick: false, autolink: true }),
            Placeholder.configure({ placeholder: root.dataset.placeholder ?? '' }),
            ...(candidates
                ? [Mention.configure({ suggestion: mentionSuggestion(candidates) })]
                : []),
            // 画像は添付を通して上げたもの（自分のサーバーの URL）だけ。外部の画像はサーバー側で落とす
            Image.configure({ inline: true }),
        ],
        content: input.value,
        editorProps: {
            attributes: {
                class: 'prose-editor focus:outline-none',
                'aria-label': '内容',
            },
            // ファイルの貼り付け・ドロップは、添付を上げられる画面でだけ受ける
            handlePaste: (view, event) => {
                const files = [...(event.clipboardData?.files ?? [])];

                if (!uploadUrl || files.length === 0) {
                    return false;
                }

                insertFiles(editor, uploadUrl, files);

                return true;
            },
            handleDrop: (view, event, slice, moved) => {
                const files = [...(event.dataTransfer?.files ?? [])];

                if (!uploadUrl || moved || files.length === 0) {
                    return false;
                }

                event.preventDefault();
                insertFiles(editor, uploadUrl, files);

                return true;
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
        /*
         * ツールバーを押しても本文からフォーカスを奪わない。
         * 奪うと選択範囲が消えるうえ、Safari ではボタンにフォーカスが移らないので
         * 「入力から離れた」と見なされて自動保存が走ってしまう。
         */
        button.addEventListener('mousedown', (event) => event.preventDefault());

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

/**
 * ページ内のエディタを起動する。
 *
 * ただし閉じた <details> の中にあるものは後回しにする。課題詳細では
 * コメントの数だけ編集フォームが並ぶので、全部を最初に起動すると
 * 開きもしないエディタのために Tiptap を何個も抱えることになる。
 */
export function bootEditors() {
    document.querySelectorAll('[data-editor]').forEach((element) => {
        const collapsed = element.closest('details:not([open])');

        if (!collapsed) {
            createEditor(element);
            return;
        }

        // 開いた瞬間に 1 度だけ起動する
        collapsed.addEventListener('toggle', () => {
            if (collapsed.open && !element.dataset.editorBooted) {
                element.dataset.editorBooted = '1';
                createEditor(element);
            }
        });
    });
}
