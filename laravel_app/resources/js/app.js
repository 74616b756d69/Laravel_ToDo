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
