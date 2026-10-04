/**
 * キーボードショートカット。一覧は ? で開くダイアログ（partials/shortcuts.blade.php）。
 *
 * 入力中（input / textarea / select / エディタ）や、Ctrl・⌘・Alt を押しているときは何もしない。
 * 文字を打っているつもりが画面が動く、が一番困るため。
 * 行き先はリンクの href をそのまま読むので、URL を JS に書き写さない。
 */

const SEQUENCE_TIMEOUT = 1200;

/** g のあとに押すキーと、行き先のリンク（data-shortcut-target） */
const GO_TO = { i: 'issues', b: 'board', l: 'backlog', d: 'dashboard', n: 'notifications' };

function isTyping(target) {
    return target instanceof HTMLElement
        && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));
}

function follow(name) {
    const link = document.querySelector(`[data-shortcut-target="${name}"]`);

    if (link?.href) {
        window.location.assign(link.href);
    }
}

export function bootShortcuts() {
    const dialog = document.querySelector('[data-shortcuts-dialog]');
    let pendingG = null;

    document.addEventListener('keydown', (event) => {
        if (event.defaultPrevented || event.ctrlKey || event.metaKey || event.altKey || isTyping(event.target)) {
            return;
        }

        if (dialog?.open) {
            return;
        }

        const key = event.key;

        if (pendingG !== null) {
            clearTimeout(pendingG);
            pendingG = null;

            if (GO_TO[key]) {
                event.preventDefault();
                follow(GO_TO[key]);
            }

            return;
        }

        switch (key) {
            case '?':
                event.preventDefault();
                dialog?.showModal();
                break;
            case '/': {
                const search = document.querySelector('#global-search');

                // 狭い画面では検索窓が隠れている。そのときは一覧の絞り込みへ
                if (search && search.offsetParent !== null) {
                    event.preventDefault();
                    search.focus();
                    search.select();
                }
                break;
            }
            case 'c':
                event.preventDefault();
                follow('create');
                break;
            case 'g':
                pendingG = setTimeout(() => { pendingG = null; }, SEQUENCE_TIMEOUT);
                break;
            case 'w': {
                const watch = document.querySelector('[data-shortcut-watch]');

                if (watch) {
                    event.preventDefault();
                    watch.requestSubmit();
                }
                break;
            }
            case 'm': {
                const comment = document.querySelector('[data-comment-form] .ProseMirror');

                if (comment) {
                    event.preventDefault();
                    comment.scrollIntoView({ block: 'center' });
                    comment.focus();
                }
                break;
            }
            default:
                break;
        }
    });
}
