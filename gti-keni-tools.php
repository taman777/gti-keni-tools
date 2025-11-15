<?php

/**
 * Plugin Name: GTI 賢威-SYN 管理ツール
 * Plugin URI: https://github.com/taman777/gti-keni-tools
 * Description: 賢威テーマからSYNテーマへの移行時にPV統合や目次自動挿入、アイキャッチ設定移行を行うGTI専用管理ツール。
 * Version: 1.2.0
 * Author: 株式会社ジーティーアイ
 * Author URI: https://gti.jp/
 */

/**
 * == Changelog ==
 *
 * 1.2.0 - 2025-11-16
 *  - 賢威 → SYN のアイキャッチポリシー移行ツールを追加
 *  - 賢威の個別設定 / 全体設定を読み取り、_synx_eyecatch に正しく変換
 *  - Dry-run（書き込みなし）で変換結果一覧を表示
 *  - 実行モード（書き込みあり）で _synx_eyecatch = 1（非表示）を安全にセット
 *  - _synx_eyecatch 設定済み記事は自動スキップ
 *
 * 1.1.0 - 2025-11-09
 *  - SYNテーマ用 TOC（目次）自動挿入機能を追加
 *  - 「賢威-SYNツール」配下に「SYN目次設定」メニューを追加
 *
 * 1.0.0 - 2025-10-31
 *  - 初回リリース
 */

if (! defined('ABSPATH')) exit;

define('GTI_KENI_DIR', plugin_dir_path(__FILE__));
define('GTI_KENI_URL', plugin_dir_url(__FILE__));

// コア読込
require_once GTI_KENI_DIR . 'inc/keni-tools-core.php';

// 各ツールモジュールを自動ロード
foreach (glob(GTI_KENI_DIR . 'inc/tools/*.php') as $tool_file) {
    require_once $tool_file;
}

// =====================================================
// GitHub連携：plugin-update-checker
// =====================================================
if (file_exists(GTI_KENI_DIR . 'vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php')) {
    require GTI_KENI_DIR . 'vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php';

    $updateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/taman777/gti-keni-tools',
        __FILE__,
        'gti-keni-tools'
    );

    // GitHubリリース（タグ）をバージョン情報として使用
    $updateChecker->getVcsApi()->enableReleaseAssets();
}
