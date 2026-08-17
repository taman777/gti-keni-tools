<?php

/**
 * Plugin Name: GTI SYN管理ツール
 * Plugin URI: https://github.com/taman777/gti-keni-tools
 * Description: 賢威テーマからSYNテーマへの移行時にPV統合や目次自動挿入、アイキャッチ設定移行を行うGTI専用管理ツール。
 * Version: 1.7.0
 * Author: 株式会社ジーティーアイ
 * Author URI: https://gti.jp/
 */

/**
 * == Changelog ==
 *
 * 1.7.0 - 2026-08-16
 *  - プラグイン表示名称を「GTI SYN管理ツール」に変更
 *  - アップデート配信元を自社サーバーへ移行（APIキー認証に対応）
 *  - 各種カスタマイズ機能（ランダム投稿、読了時間非表示、ログイン画面等）を移植
 *
 * 1.6.0 - 2026-02-04
 *  - Blogcard拡張機能を追加（target属性サポート）
 *  - [blogcard target="_self"] など、リンクの開き方を指定可能に
 *  - 抜粋表示（JavaScript挿入）機能を追加
 *  - カード型・リスト型など、どのレイアウトでも抜粋を動的に表示可能
 *  - 文字数調整機能付き（デフォルト120文字）
 *
 * 1.5.0 - 2025-12-07
 *  - 賢威ボックス互換レイヤー（SYN用スタイル）を追加
 *  - 賢威8時代のボックススタイル（.box_style 等）をSYN風に装飾
 *  - ブロックパターン「GTI 賢威→SYN 互換」を追加
 *  - CSS読み込みのテーマ上書き対応（子テーマ/親テーマでカスタマイズ可能）
 *
 * 1.4.0 - 2025-11-29
 *  - 期間別ランキング機能（週間・月間・全期間）を追加
 *  - ウィジェット「【GTI】期間別ランキング」を追加
 *  - カテゴリー絞り込み、0 view 除外、デザイン設定機能
 *  - 管理画面で機能の ON/OFF 切り替え可能
 *
 * 1.3.0 - 2025-11-22
 *  - 賢威ショートコード互換機能を追加
 *  - [keni-linkcard] → [blogcard] 自動変換
 *  - [cc] 共通コンテンツショートコード対応
 *  - ON/OFF スイッチ付き管理画面
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

define('GTI_KENI_TOOLS_DIR', plugin_dir_path(__FILE__));
define('GTI_KENI_TOOLS_URL', plugin_dir_url(__FILE__));
define('GTI_KENI_TOOLS_VERSION', '1.7.0');

// コア読込
require_once GTI_KENI_TOOLS_DIR . 'inc/keni-tools-core.php';

// 各ツールモジュールを自動ロード
foreach (glob(GTI_KENI_TOOLS_DIR . 'inc/tools/*.php') as $tool_file) {
    require_once $tool_file;
}

// =====================================================
// 自社サーバー連携：plugin-update-checker (APIキー認証対応)
// =====================================================
if (file_exists(GTI_KENI_TOOLS_DIR . 'vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php')) {
    require GTI_KENI_TOOLS_DIR . 'vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php';

    $updateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://gti.co.jp/dev/gti-keni-tools/updates/version.php',
        __FILE__,
        'gti-keni-tools'
    );

    // APIキーが存在する場合はクエリパラメータとして付与
    $apiKey = get_option('gti_syn_tools_api_key');
    if ($apiKey) {
        $updateChecker->addFilter('request_info_query_args', function($queryArgs) use ($apiKey) {
            $queryArgs['api_key'] = $apiKey;
            return $queryArgs;
        });
    }
}
