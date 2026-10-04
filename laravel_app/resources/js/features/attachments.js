/**
 * 添付ファイルのアップロード。
 *
 * 課題画面の添付欄（ドロップ・ファイル選択）と、エディタへの貼り付けの両方から使う。
 * 送り先は添付欄の data-attachment-dropzone。書き込めない人の画面には欄ごと無いので、
 * そのときはどちらの経路も動かない。
 */

export function attachmentUploadUrl() {
    return document.querySelector('[data-attachment-dropzone]')?.dataset.attachmentDropzone ?? null;
}

/**
 * 1 ファイルを送って、保存された添付（id / name / url / is_image）を返す。
 * 検証で弾かれたら、そのメッセージを持った Error を投げる。
 */
export async function uploadAttachment(url, file) {
    const body = new FormData();
    body.append('file', file);

    const response = await fetch(url, {
        method: 'POST',
        body,
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
    });

    if (response.ok) {
        return response.json();
    }

    let message = `「${file.name}」を添付できませんでした。`;

    if (response.status === 413) {
        message = `「${file.name}」は大きすぎます。`;
    } else if (response.status === 429) {
        message = '短時間に送りすぎています。少し待ってからやり直してください。';
    } else {
        try {
            const json = await response.json();
            message = json.errors?.file?.[0] ?? json.message ?? message;
        } catch {
            // 本文が JSON でなければ既定の文を使う
        }
    }

    throw new Error(message);
}

/**
 * 添付欄。ファイルを選んだら、またはドロップしたら、その場で送って画面を読み直す。
 */
export function bootAttachments() {
    const zone = document.querySelector('[data-attachment-dropzone]');

    if (!zone) {
        return;
    }

    const url = zone.dataset.attachmentDropzone;
    const form = zone.querySelector('[data-attachment-form]');
    const input = zone.querySelector('[data-attachment-input]');
    const progress = zone.querySelector('[data-attachment-progress]');

    const report = (text) => {
        progress.textContent = text;
        progress.classList.toggle('hidden', text === '');
    };

    async function send(files) {
        const list = [...files];

        if (list.length === 0) {
            return;
        }

        const failures = [];

        for (const [index, file] of list.entries()) {
            report(`アップロード中… (${index + 1}/${list.length}) ${file.name}`);

            try {
                await uploadAttachment(url, file);
            } catch (error) {
                failures.push(error.message);
            }
        }

        if (failures.length === 0 || failures.length < list.length) {
            // 1 つでも上がったなら、一覧と履歴を出し直す
            window.location.reload();

            return;
        }

        report(failures.join(' '));
    }

    form.dataset.attachmentForm = 'ready';
    input.multiple = true;
    input.required = false;
    input.addEventListener('change', () => send(input.files));

    /*
     * dragenter / dragleave は子要素に出入りするたびに飛ぶので、
     * 数を数えて「本当に欄から出た」ときだけ強調を外す。
     */
    let depth = 0;

    const carriesFiles = (event) => event.dataTransfer?.types.includes('Files');

    zone.addEventListener('dragenter', (event) => {
        if (!carriesFiles(event)) {
            return;
        }

        depth += 1;
        zone.classList.add('is-dragover');
    });

    zone.addEventListener('dragleave', () => {
        depth = Math.max(0, depth - 1);

        if (depth === 0) {
            zone.classList.remove('is-dragover');
        }
    });

    zone.addEventListener('dragover', (event) => {
        if (carriesFiles(event)) {
            event.preventDefault();
        }
    });

    zone.addEventListener('drop', (event) => {
        if (!carriesFiles(event)) {
            return;
        }

        event.preventDefault();
        depth = 0;
        zone.classList.remove('is-dragover');
        send(event.dataTransfer.files);
    });
}
