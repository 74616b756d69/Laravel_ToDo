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
