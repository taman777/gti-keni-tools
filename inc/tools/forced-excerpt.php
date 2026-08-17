<?php
if (! defined('ABSPATH')) exit;

/**
 * 記事一覧抜粋強制表示機能
 *
 * ※ この機能は技術的な制限により動作しませんでした。
 * 代わりに「抜粋表示（JS挿入）」機能をご利用ください。
 */

$GLOBALS['gti_keni_tools']['gti-forced-excerpt'] = [
    'title' => '強制抜粋表示（非推奨）',
    'menu'  => '強制抜粋表示（非推奨）',
    'callback' => 'gti_forced_excerpt_page',
    'priority' => 100,
];

/**
 * 設定画面
 */
function gti_forced_excerpt_page()
{
    if (! current_user_can('manage_options')) {
        wp_die('権限がありません');
    }

?>
    <div class="wrap">
        <h1>強制抜粋表示設定（非推奨）</h1>
        
        <div class="notice notice-warning">
            <p><strong>⚠️ この機能は技術的な制限により正常に動作しません。</strong></p>
            <p>代わりに <strong>「抜粋表示（JS挿入）」</strong> 機能をご利用ください。</p>
            <p>そちらの機能なら、カード型やリスト型でも確実に抜粋が表示されます。</p>
        </div>

        <h2>非推奨理由</h2>
        <p>このテーマは独自の方法でテンプレートを読み込んでおり、WordPressの標準的なフィルターフックが効かないため、プラグインからのテンプレート差し替えができませんでした。</p>
        
        <h2>推奨する代替方法</h2>
        <ol>
            <li><strong>抜粋表示（JS挿入）</strong> - JavaScriptで動的に抜粋を挿入（推奨）</li>
            <li><strong>Child Theme</strong> - 子テーマを作成してテンプレートファイルを直接編集</li>
        </ol>
    </div>
<?php
}
