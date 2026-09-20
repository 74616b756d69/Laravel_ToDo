<?php

/**
 * 画面で使うルールに絞った日本語メッセージ。
 */
return [
    'required' => ':attributeを入力してください。',
    'string' => ':attributeは文字列で入力してください。',
    'email' => ':attributeの形式が正しくありません。',
    'date' => ':attributeは日付で入力してください。',
    'boolean' => ':attributeの値が不正です。',
    'confirmed' => ':attributeが確認用と一致しません。',
    'unique' => 'この:attributeはすでに登録されています。',
    'enum' => '選択された:attributeは無効です。',
    'max' => [
        'string' => ':attributeは:max文字以内で入力してください。',
        'numeric' => ':attributeは:max以下で入力してください。',
    ],
    'min' => [
        'string' => ':attributeは:min文字以上で入力してください。',
        'numeric' => ':attributeは:min以上で入力してください。',
    ],
    'password' => [
        'letters' => ':attributeには英字を含めてください。',
        'numbers' => ':attributeには数字を含めてください。',
        'mixed' => ':attributeには大文字と小文字を含めてください。',
        'symbols' => ':attributeには記号を含めてください。',
        'uncompromised' => 'この:attributeは漏洩の記録があります。別の:attributeを設定してください。',
    ],
    'attributes' => [],
];
