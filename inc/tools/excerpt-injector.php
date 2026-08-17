<?php
if (! defined('ABSPATH')) exit;

/**
 * カード型記事一覧で抜粋を動的挿入する機能
 *
 * JavaScriptを使って、ページ読み込み後に抜粋を挿入します。
 */

$GLOBALS['gti_keni_tools']['gti-excerpt-injector'] = [
    'title' => '抜粋表示（JS挿入）',
    'menu'  => '抜粋表示（JS挿入）',
    'callback' => 'gti_excerpt_injector_page',
    'priority' => 30,
];

/**
 * 設定画面
 */
function gti_excerpt_injector_page()
{
    if (! current_user_can('manage_options')) {
        wp_die('権限がありません');
    }

    if (
        isset($_POST['gti_excerpt_injector_nonce']) &&
        wp_verify_nonce($_POST['gti_excerpt_injector_nonce'], 'gti_excerpt_injector_save')
    ) {
        $enabled = isset($_POST['gti_excerpt_injector_enabled']) ? '1' : '0';
        $excerpt_length = isset($_POST['gti_excerpt_length']) ? intval($_POST['gti_excerpt_length']) : 120;
        
        update_option('gti_excerpt_injector_enabled', $enabled);
        update_option('gti_excerpt_injector_length', $excerpt_length);
        
        gti_keni_notice('設定を保存しました。', 'success');
    }

    $enabled = get_option('gti_excerpt_injector_enabled', '0');
    $excerpt_length = get_option('gti_excerpt_injector_length', 120);

?>
    <div class="wrap">
        <h1>抜粋表示（JavaScript挿入）設定</h1>
        <p>JavaScriptを使って、記事一覧の各記事に抜粋（本文の一部）を動的に挿入します。</p>
        <p><strong>※ カード型、リスト型、サムネイル型などで抜粋を表示できます。</strong></p>

        <form method="post">
            <?php wp_nonce_field('gti_excerpt_injector_save', 'gti_excerpt_injector_nonce'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">機能を有効化</th>
                    <td>
                        <label>
                            <input type="checkbox"
                                name="gti_excerpt_injector_enabled"
                                value="1"
                                <?php checked($enabled, '1'); ?>>
                            抜粋の動的挿入機能を有効にする
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">抜粋の文字数</th>
                    <td>
                        <input type="number"
                            name="gti_excerpt_length"
                            value="<?php echo esc_attr($excerpt_length); ?>"
                            min="50"
                            max="500"
                            step="10"
                            style="width: 100px;">
                        文字
                        <p class="description">PC表示時の抜粋文字数を指定します（モバイルは80%）</p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" class="button button-primary" value="設定を保存">
            </p>
        </form>
    </div>
<?php
}

/**
 * 機能の初期化
 */
add_action('wp', function () {
    if (get_option('gti_excerpt_injector_enabled', '0') !== '1') {
        return;
    }

    // アーカイブページとホームページでのみ動作
    if (!is_home() && !is_archive() && !is_search()) {
        return;
    }

    // JavaScriptとCSSを読み込み
    add_action('wp_enqueue_scripts', 'gti_excerpt_injector_enqueue_assets');
    
    // フッターに抜粋データを出力
    add_action('wp_footer', 'gti_excerpt_injector_render_data');
});

/**
 * アセット（JS/CSS）を読み込み
 */
function gti_excerpt_injector_enqueue_assets()
{
    // CSSをインラインで出力（シンプルなので）
    add_action('wp_head', function() {
        ?>
        <style>
        .gti-injected-excerpt {
            margin-top: 0.5em;
            font-size: 0.9em;
            line-height: 1.6;
            color: #666;
        }
        .common-list.is-type-card .gti-injected-excerpt,
        .common-list.is-type-list .gti-injected-excerpt,
        .common-list.is-type-thumb .gti-injected-excerpt {
            display: block;
        }
        </style>
        <?php
    });

    // JavaScriptをインラインで出力
    add_action('wp_footer', function() {
        ?>
        <script>
        (function() {
            'use strict';
            
            // データが読み込まれるのを待つ
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof window.gtiExcerptData === 'undefined') {
                    return;
                }
                
                injectExcerpts();
            });
            
            function injectExcerpts() {
                const excerptData = window.gtiExcerptData;
                const listItems = document.querySelectorAll('.common-list__item');
                
                listItems.forEach(function(item) {
                    const link = item.querySelector('.common-list__link');
                    if (!link) return;
                    
                    // 記事のURLから投稿IDを特定
                    const href = link.getAttribute('href');
                    if (!href) return;
                    
                    // URLから投稿IDを抽出（複数の方法を試す）
                    let postId = null;
                    
                    // 方法1: URLのpost_idパラメータ
                    const urlParams = new URLSearchParams(href.split('?')[1] || '');
                    if (urlParams.has('p')) {
                        postId = urlParams.get('p');
                    }
                    
                    // 方法2: パーマリンクから投稿スラッグを取得してマッチング
                    if (!postId) {
                        for (const id in excerptData) {
                            if (href.includes(excerptData[id].slug) || 
                                href === excerptData[id].url) {
                                postId = id;
                                break;
                            }
                        }
                    }
                    
                    if (!postId || !excerptData[postId]) {
                        return;
                    }
                    
                    // すでに抜粋が存在する場合はスキップ
                    if (item.querySelector('.common-list__excerpt, .gti-injected-excerpt')) {
                        return;
                    }
                    
                    // タイトル要素を探す
                    const title = item.querySelector('.common-list__ttl');
                    if (!title) return;
                    
                    // 抜粋要素を作成
                    const excerptEl = document.createElement('p');
                    excerptEl.className = 'common-list__excerpt gti-injected-excerpt';
                    excerptEl.textContent = excerptData[postId].excerpt;
                    
                    // タイトルの後に挿入
                    title.parentNode.insertBefore(excerptEl, title.nextSibling);
                });
            }
        })();
        </script>
        <?php
    }, 100);
}

/**
 * 抜粋データをJavaScriptに出力
 */
function gti_excerpt_injector_render_data()
{
    global $wp_query;
    
    if (!$wp_query || !$wp_query->have_posts()) {
        return;
    }

    $excerpt_length = get_option('gti_excerpt_injector_length', 120);
    $excerpt_data = [];

    // 現在のクエリから投稿を取得
    $posts = $wp_query->posts;
    
    foreach ($posts as $post) {
        $content = $post->post_content;
        
        // ショートコード除去
        $content = strip_shortcodes($content);
        
        // HTMLタグ除去
        $content = wp_strip_all_tags($content);
        
        // 改行除去
        $content = str_replace(["\r\n", "\r", "\n"], ' ', $content);
        
        // 文字数制限
        if (mb_strlen($content) > $excerpt_length) {
            $content = mb_substr($content, 0, $excerpt_length) . '...';
        }
        
        $excerpt_data[$post->ID] = [
            'excerpt' => $content,
            'url' => get_permalink($post->ID),
            'slug' => $post->post_name,
        ];
    }

    // JSONとして出力
    ?>
    <script>
    window.gtiExcerptData = <?php echo json_encode($excerpt_data, JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <?php
}
