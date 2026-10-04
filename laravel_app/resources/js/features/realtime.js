import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { bootBoard } from './board';

/**
 * リアルタイム更新（Laravel Reverb）。
 *
 * サーバーは「どの課題が誰に変えられたか」だけを送ってくる（App\Events\IssueChanged）。
 * 中身は受けた画面が自分の権限で読み直す。
 *  - ボード … 掴んでいる最中でなければ、ボードだけを差し替える
 *  - 課題の画面 … その課題なら「更新されました」を出す（書きかけを消さないよう、読み直しは押してもらう）
 * 自分の操作は自分の画面ですでに反映済みなので無視する。
 */

const BOARD_REFRESH_DELAY = 400;

export function bootRealtime() {
    const meta = document.querySelector('meta[name="realtime"]');

    if (!meta) {
        return;
    }

    const config = JSON.parse(meta.content);

    window.Pusher = Pusher;

    const echo = new Echo({
        broadcaster: 'reverb',
        key: config.key,
        wsHost: config.host,
        wsPort: config.port,
        wssPort: config.port,
        forceTLS: config.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
        },
    });

    const projects = new Set(
        [...document.querySelectorAll('[data-realtime-project]')].map((el) => el.dataset.realtimeProject),
    );

    projects.forEach((id) => {
        echo.private(`projects.${id}`).listen('.issue.changed', (event) => {
            if (Number(event.actor_id) === Number(config.user)) {
                return;
            }

            onIssueChanged(event);
        });
    });
}

let boardTimer = null;

function onIssueChanged(event) {
    if (document.querySelector('[data-board]')) {
        clearTimeout(boardTimer);
        boardTimer = setTimeout(() => refreshBoard(event), BOARD_REFRESH_DELAY);
    }

    const banner = document.querySelector(`[data-realtime-issue="${event.issue_id}"]`);

    if (banner) {
        banner.querySelector('[data-realtime-message]').textContent = event.action === 'deleted'
            ? `${event.actor_name}さんがこの課題を削除しました。`
            : `${event.actor_name}さんがこの課題を更新しました。`;
        banner.classList.remove('hidden');
        banner.classList.add('flex');
    }
}

/**
 * ボードを読み直して差し替える。カードを掴んでいる最中は、離すまで待つ。
 */
async function refreshBoard(event) {
    if (document.querySelector('.sortable-chosen')) {
        boardTimer = setTimeout(() => refreshBoard(event), BOARD_REFRESH_DELAY);

        return;
    }

    const response = await fetch(window.location.href, { headers: { Accept: 'text/html' } });

    if (!response.ok) {
        return;
    }

    const html = new DOMParser().parseFromString(await response.text(), 'text/html');
    const fresh = html.querySelector('[data-board]');
    const current = document.querySelector('[data-board]');

    if (!fresh || !current) {
        return;
    }

    current.replaceWith(document.importNode(fresh, true));
    bootBoard();
    toast(`${event.actor_name}さんが ${event.issue_key} を${event.action === 'created' ? '追加' : event.action === 'deleted' ? '削除' : '更新'}しました`);
}

function toast(message) {
    const element = document.createElement('div');
    element.setAttribute('role', 'status');
    element.className = 'fixed right-4 bottom-4 z-50 rounded-xl bg-slate-900 px-4 py-2.5 text-sm text-white shadow-lg dark:bg-white dark:text-slate-900';
    element.textContent = message;
    document.body.append(element);
    setTimeout(() => element.remove(), 4000);
}
