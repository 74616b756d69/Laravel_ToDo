import Sortable from 'sortablejs';

/**
 * カンバンのドラッグ＆ドロップ。
 * 移動が確定したら、移動先レーンの並び順をまとめてサーバーへ送る。
 */
export function bootBoard() {
    const board = document.querySelector('[data-board]');

    if (!board) {
        return;
    }

    const token = document.querySelector('meta[name="csrf-token"]').content;

    const lanes = [...board.querySelectorAll('[data-lane]')];

    lanes.forEach((lane) => {
        Sortable.create(lane, {
            group: 'board',
            animation: 150,
            ghostClass: 'opacity-40',
            dragClass: 'rotate-1',
            // レーンは中だけスクロールするので、端まで運んだら自動で送る
            scroll: true,
            scrollSensitivity: 60,
            scrollSpeed: 12,
            onEnd: async (event) => {
                const card = event.item;
                const target = event.to;

                try {
                    const response = await fetch(card.dataset.moveUrl, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token,
                            Accept: 'application/json',
                        },
                        body: JSON.stringify({
                            status: target.dataset.lane,
                            ids: [...target.querySelectorAll('[data-task-id]')].map(
                                (el) => Number(el.dataset.taskId),
                            ),
                        }),
                    });

                    if (!response.ok) {
                        throw new Error(response.statusText);
                    }

                    updateCounts(lanes);
                } catch (error) {
                    // 保存に失敗したら元の位置に戻して、画面と DB の食い違いを防ぐ
                    event.from.insertBefore(card, event.from.children[event.oldIndex] ?? null);
                    updateCounts(lanes);
                    window.alert('移動を保存できませんでした。通信状況を確認してください。');
                }
            },
        });
    });

    updateCounts(lanes);

    // 追加フォームを開いたら、そのまま入力できるようにフォーカスを当てる
    board.querySelectorAll('details').forEach((details) => {
        details.addEventListener('toggle', () => {
            if (details.open) {
                details.querySelector('input[name="quick"]')?.focus();
            }
        });
    });
}

function updateCounts(lanes) {
    lanes.forEach((lane) => {
        const counter = document.querySelector(`[data-lane-count="${lane.dataset.lane}"]`);

        if (counter) {
            counter.textContent = lane.querySelectorAll('[data-task-id]').length;
        }

        lane.querySelector('[data-lane-empty]')?.classList.toggle(
            'hidden',
            lane.querySelectorAll('[data-task-id]').length > 0,
        );
    });
}
