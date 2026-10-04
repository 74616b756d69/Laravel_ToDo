<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Webhook の送り先として使えない URL（http・内部ネットワーク・名前が引けない など）。
 * メッセージはそのまま画面と配信記録に出す。
 */
class UnsafeWebhookUrlException extends RuntimeException {}
