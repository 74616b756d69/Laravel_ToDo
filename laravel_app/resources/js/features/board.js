import Sortable from 'sortablejs';

/**
 * カンバンのドラッグ＆ドロップ。
 *
 * レーンはプロジェクトのワークフロー（statuses）から作られるので本数が可変。
 * どのレーンへ運べるかは data-allowed-transitions の表で判断する。
 *  - 運べない先には、そもそもドロップさせない（SortableJS の put）
 *  - すり抜けてサーバーに 422 で弾かれたら、カードを元の位置へ戻して理由を出す
 */
export function bootBoard() {
    const board = document.querySelector('[data-board]');

    if (!board) {
        return;
    }

    const token = document.querySelector('meta[name="csrf-token"]').content;
    const lanes = [...board.querySelectorAll('[data-lane]')];

    // { 遷移元のステータスID: [運べる先のステータスID, ...] }
    const allowed = JSON.parse(board.dataset.allowedTransitions || '{}');

    const laneIdOf = (lane) => Number(lane.dataset.lane);

    const canMove = (fromLane, toLane) => {
        const destinations = allowed[laneIdOf(fromLane)];

        // 表に無いステータスは判断材料が無いので止めない。サーバー側が最後に弾く
        return !destinations || destinations.includes(laneIdOf(toLane));
    };

    lanes.forEach((lane) => {
        Sortable.create(lane, {
            group: {
                name: 'board',
                // 許可されていない遷移はドロップ自体を受け付けない。
                // 「運べてしまってから戻る」より、運べないと分かるほうが親切
                put: (to, from) => canMove(from.el, to.el),
            },
            animation: 150,
            ghostClass: 'opacity-40',
            dragClass: 'rotate-1',
            // レーンは中だけスクロールするので、端まで運んだら自動で送る
            scroll: true,
            scrollSensitivity: 60,
            scrollSpeed: 12,
            onStart: () => {
                clearError(board);
                highlightDroppableLanes(board, lanes, lane, canMove);
            },
            onEnd: async (event) => {
                clearHighlight(lanes);

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
                            status: laneIdOf(target),
                            ids: [...target.querySelectorAll('[data-task-id]')].map(
                                (el) => Number(el.dataset.taskId),
                            ),
                        }),
                    });

                    if (!response.ok) {
                        throw await toError(response);
                    }

                    updateCounts(lanes);
                } catch (error) {
                    // 保存できなかったら元の位置に戻して、画面と DB の食い違いを防ぐ
                    revert(event);
                    updateCounts(lanes);
                    showError(board, error.message);
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

/**
 * サーバーの応答をエラーに変換する。
 * ワークフロー違反（422）は理由が返ってくるので、それをそのまま見せる。
 */
async function toError(response) {
    if (response.status === 422) {
        const body = await response.json().catch(() => ({}));

        return new Error(body.message ?? 'このステータスへは変更できません。');
    }

    if (response.status === 403) {
        return new Error('この課題を変更する権限がありません。');
    }

    return new Error('移動を保存できませんでした。通信状況を確認してください。');
}

/**
 * ドラッグ前の位置にカードを戻す。
 */
function revert(event) {
    event.from.insertBefore(event.item, event.from.children[event.oldIndex] ?? null);
}

/**
 * 運べないレーンを視覚的に落とす。どこへ運べるかを掴んでいる間に伝える。
 */
function highlightDroppableLanes(board, lanes, fromLane, canMove) {
    lanes.forEach((lane) => {
        const droppable = lane === fromLane || canMove(fromLane, lane);

        lane.closest('section')?.classList.toggle('opacity-40', !droppable);
        lane.classList.toggle('ring-2', droppable && lane !== fromLane);
        lane.classList.toggle('ring-brand-500/30', droppable && lane !== fromLane);
    });
}

function clearHighlight(lanes) {
    lanes.forEach((lane) => {
        lane.closest('section')?.classList.remove('opacity-40');
        lane.classList.remove('ring-2', 'ring-brand-500/30');
    });
}

function showError(board, message) {
    const box = board.parentElement.querySelector('[data-board-error]');

    if (!box) {
        return;
    }

    box.querySelector('[data-board-error-message]').textContent = message;
    box.classList.remove('hidden');
    box.classList.add('flex');
}

function clearError(board) {
    const box = board.parentElement.querySelector('[data-board-error]');

    box?.classList.add('hidden');
    box?.classList.remove('flex');
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
