import './bootstrap';

/**
 * テーマ切り替え。選択は localStorage に保存し、次回以降も引き継ぐ。
 */
document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-theme-toggle]');

    if (!toggle) {
        return;
    }

    const isDark = document.documentElement.classList.toggle('dark');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
});

/**
 * 削除など、取り消せない操作は送信前に確認する。
 */
document.addEventListener('submit', (event) => {
    const message = event.target.dataset.confirm;

    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});

/**
 * 絞り込みフォームは選択と同時に反映する（JS が無効でも「適用」ボタンで送信できる）。
 */
document.querySelectorAll('form[data-auto-submit]').forEach((form) => {
    form.addEventListener('change', (event) => {
        if (event.target.type !== 'search') {
            form.requestSubmit();
        }
    });

    /*
     * ここまで来たということは、選択だけで送信できる。
     * 送信ボタンは JS 無効時の控えなので、印を付けて CSS 側で隠す
     * （課題画面の担当者・課題タイプは、選ぶだけで変わるのが本来の姿）。
     */
    form.dataset.autoSubmit = 'ready';
});

/**
 * エディタとカンバンは重いライブラリを使うため、
 * その要素があるページでだけ動的に読み込む（初期表示を軽く保つ）。
 */
if (document.querySelector('[data-editor]')) {
    import('./features/editor').then(({ bootEditors }) => bootEditors());
}

if (document.querySelector('[data-board]')) {
    import('./features/board').then(({ bootBoard }) => bootBoard());
}

if (document.querySelector('[data-backlog]')) {
    import('./features/backlog').then(({ bootBacklog }) => bootBacklog());
}

/**
 * その場で編集する欄（x-inline-edit）の増補。
 *
 * 開閉は details なので JS 無しでも動く。ここで足すのは、
 * 紙の上では得られない 4 つだけ:
 *  - マウスでは 1 クリックで開かず、ダブルクリックで開く
 *  - 開いたら入力へフォーカスを移す（押した直後に打ち始められる）
 *  - 入力から離れたら保存する（保存ボタンを置かない代わり）
 *  - Escape で閉じて入力を元に戻す / Ctrl・⌘ + Enter でその場で保存する
 */

/*
 * 開いた時点の中身。離れたときに「本当に変わったのか」を見るために取る。
 * 変わっていないのに送ると、更新日時と履歴だけが増えてしまう。
 */
const inlineEditInitial = new WeakMap();

function inlineEditValues(form) {
    // _token と _method は毎回同じなので、比べる意味がない
    const entries = [...new FormData(form)].filter(([name]) => name !== '_token' && name !== '_method');

    return JSON.stringify(entries);
}

function closeInlineEdit(details) {
    const form = details.querySelector('[data-inline-form]');

    /*
     * 元に戻すのは、素の入力だけ。
     * リッチエディタは Tiptap が中身を持っていて hidden input はその写しなので、
     * ここで input だけ巻き戻すと、次に開いたときに見た目と送信値がずれる。
     */
    if (form && !form.querySelector('[data-editor]')) {
        form.reset();
    }

    details.open = false;
    details.querySelector('summary')?.focus();
}

function saveInlineEdit(details) {
    const form = details.querySelector('[data-inline-form]');

    if (!form) {
        return;
    }

    // 触っただけで閉じたときは、送らずに畳む
    if (inlineEditValues(form) === inlineEditInitial.get(form)) {
        details.open = false;

        return;
    }

    form.requestSubmit();
}

document.addEventListener('toggle', (event) => {
    const details = event.target;

    if (!details.matches?.('[data-inline-edit]') || !details.open) {
        return;
    }

    const form = details.querySelector('[data-inline-form]');

    if (form) {
        inlineEditInitial.set(form, inlineEditValues(form));
    }

    // リッチエディタは自分でフォーカスを受け取るので、素の入力だけを見る
    const field = details.querySelector('[data-inline-form] input:not([type="hidden"]), [data-inline-form] select, [data-inline-form] textarea');

    field?.focus();
    field?.select?.();
}, true);

/*
 * マウスの 1 クリックでは開かない。
 *
 * 表示のままで文字を選んだりリンクを押したりする方が、直すより多い。
 * 1 クリックで入力に変わると、選ぼうとしただけで編集が始まってしまう。
 * ここで既定の開閉を止め、dblclick で開く。開いている間も止めるので、
 * 入力中に表示部分を押しても閉じない（閉じるのは保存と Escape）。
 *
 * event.detail が 0 のクリックはキーボード（Enter / Space）から来たもの。
 * こちらは details の既定どおり開かせる。ダブルクリックの代わりが要る。
 */
document.addEventListener('click', (event) => {
    const summary = event.target.closest('summary');

    if (summary?.parentElement?.matches('[data-inline-edit]') && event.detail > 0) {
        event.preventDefault();
    }
});

document.addEventListener('dblclick', (event) => {
    const summary = event.target.closest('summary');
    const details = summary?.parentElement;

    if (!details?.matches('[data-inline-edit]') || details.open) {
        return;
    }

    /*
     * ダブルクリックで選ばれた文字が入力に残ると、次に打った字で消える。
     * 開く前に選択を外しておく。
     */
    window.getSelection()?.removeAllRanges();
    details.open = true;
});

/*
 * 入力から離れたら保存する。
 *
 * 保存ボタンを置かない代わりの動き。ひとつ直して次の項目へ進むとき、
 * いちいち保存を押しに戻らずに済む。
 *
 * focusout の時点では次のフォーカス先がまだ決まっていないので、
 * 1 周待ってから activeElement を見る。同じ欄の中（別の入力、
 * エディタのツールバー、タグのチップ）へ移っただけなら、まだ編集中。
 */
document.addEventListener('focusout', (event) => {
    const details = event.target.closest?.('[data-inline-edit][open]');

    if (!details) {
        return;
    }

    setTimeout(() => {
        if (!details.open || details.contains(document.activeElement)) {
            return;
        }

        /*
         * 画面そのものから離れた（タブやウィンドウの切り替え）ときは送らない。
         * 戻ってきたら続きを書くつもりかもしれず、書きかけで保存されると困る。
         */
        if (!document.hasFocus()) {
            return;
        }

        saveInlineEdit(details);
    });
});

document.addEventListener('keydown', (event) => {
    const details = event.target.closest?.('[data-inline-edit][open]');

    if (!details) {
        return;
    }

    if (event.key === 'Escape') {
        closeInlineEdit(details);

        return;
    }

    if (event.key === 'Enter' && (event.metaKey || event.ctrlKey)) {
        saveInlineEdit(details);
    }
});

/**
 * ヘッダーのメニュー（details）を、外側のクリックと Escape で閉じる。
 *
 * details だけでも開閉はできるので、これは増補。
 * 開いたまま別のメニューを開くと 2 枚重なるので、開いた側以外は閉じる。
 */
document.addEventListener('click', (event) => {
    const opened = event.target.closest('[data-menu][open]');

    document.querySelectorAll('[data-menu][open]').forEach((menu) => {
        if (menu !== opened) {
            menu.open = false;
        }
    });
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
        return;
    }

    document.querySelectorAll('[data-menu][open]').forEach((menu) => {
        menu.open = false;
        // 閉じたあとの行き先が消えないよう、開いていたつまみへ戻す
        menu.querySelector('summary')?.focus();
    });
});
