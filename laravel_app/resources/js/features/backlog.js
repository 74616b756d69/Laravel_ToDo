import Sortable from 'sortablejs';

/**
 * バックログのドラッグ＆ドロップ。
 *
 * 各スプリントとバックログが 1 つのリストで、その間を課題が行き来する。
 * ボードと違って遷移の制約は無いが、失敗したら元へ戻す作りは同じ。
 */
export function bootBacklog() {
    const backlog = document.querySelector('[data-backlog]');

    if (!backlog) {
        return;
    }

    const token = document.querySelector('meta[name="csrf-token"]').content;
    const lists = [...backlog.querySelectorAll('[data-sprint]')];

    // data-sprint が空文字ならバックログ（sprint_id = null）
    const sprintIdOf = (list) => list.dataset.sprint || null;

    lists.forEach((list) => {
        Sortable.create(list, {
            group: 'backlog',
            animation: 150,
            ghostClass: 'opacity-40',
            dragClass: 'rotate-1',
            // 空リストのプレースホルダは掴めないようにする
            filter: '[data-lane-empty]',
            onStart: () => clearError(backlog),
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
                            sprint: sprintIdOf(target),
                            ids: [...target.querySelectorAll('[data-issue-id]')].map(
                                (el) => Number(el.dataset.issueId),
                            ),
                        }),
                    });

                    if (!response.ok) {
                        throw await toError(response);
                    }

                    updateCounts(lists);
                } catch (error) {
                    // 保存できなかったら元の位置に戻して、画面と DB の食い違いを防ぐ
                    event.from.insertBefore(card, event.from.children[event.oldIndex] ?? null);
                    updateCounts(lists);
                    showError(backlog, error.message);
                }
            },
        });
    });

    updateCounts(lists);
}

async function toError(response) {
    if (response.status === 422) {
        const body = await response.json().catch(() => ({}));

        return new Error(body.message ?? 'この課題はここへ移せません。');
    }

    if (response.status === 403) {
        return new Error('この課題を変更する権限がありません。');
    }

    return new Error('移動を保存できませんでした。通信状況を確認してください。');
}

function showError(backlog, message) {
    const box = backlog.parentElement.querySelector('[data-backlog-error]');

    if (!box) {
        return;
    }

    box.querySelector('[data-backlog-error-message]').textContent = message;
    box.classList.remove('hidden');
    box.classList.add('flex');
}

function clearError(backlog) {
    const box = backlog.parentElement.querySelector('[data-backlog-error]');

    box?.classList.add('hidden');
    box?.classList.remove('flex');
}

function updateCounts(lists) {
    lists.forEach((list) => {
        const count = list.querySelectorAll('[data-issue-id]').length;
        const counter = document.querySelector(
            `[data-sprint-count="${list.dataset.sprint || 'backlog'}"]`,
        );

        if (counter) {
            counter.textContent = count;
        }

        list.querySelector('[data-lane-empty]')?.classList.toggle('hidden', count > 0);
    });
}
