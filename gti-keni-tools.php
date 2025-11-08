<?php
/**
 * Plugin Name: GTI 賢威-SYN 管理ツール
 * Plugin URI: https://github.com/taman777/gti-keni-tools
 * Description: 賢威テーマからSYNテーマへの移行時にPV統合などを行うGTI専用管理ツール。
 * Version: 1.0.0
 * Author: 株式会社ジーティーアイ
 * Author URI: https://gti.jp/
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'GTI_KENI_DIR', plugin_dir_path( __FILE__ ) );
define( 'GTI_KENI_URL', plugin_dir_url( __FILE__ ) );

// コア読込
require_once GTI_KENI_DIR . 'inc/keni-tools-core.php';

// 各ツールモジュールを自動ロード
foreach ( glob( GTI_KENI_DIR . 'inc/tools/*.php' ) as $tool_file ) {
	require_once $tool_file;
}

// =====================================================
// GitHub連携：plugin-update-checker
// =====================================================
if ( file_exists( GTI_KENI_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php' ) ) {
	require GTI_KENI_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';

	use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

	$updateChecker = PucFactory::buildUpdateChecker(
		'https://github.com/taman777/gti-keni-tools', // ← 修正版
		__FILE__,
		'gti-keni-tools'
	);

	// GitHubリリース（タグ）をバージョン情報として使用
	$updateChecker->getVcsApi()->enableReleaseAssets();
}
