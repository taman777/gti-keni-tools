<?php
/**
 * 賢威ボックス互換レイヤー（SYN用スタイル）
 * - 既存の .box_style / .box_style_◯◯ を SYN っぽく装飾
 * - ブロックパターンも同じ class で登録
 * - 管理画面から ON/OFF 可能
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/*--------------------------------------------------------------
  モジュール登録
--------------------------------------------------------------*/
$GLOBALS['gti_keni_tools']['gti_keni_box_style_compat'] = [
    'title'    => '賢威ボックス互換レイヤー',
    'menu'     => '賢威BOXスタイル',
    'callback' => 'gti_keni_box_style_compat_page',
];

/*--------------------------------------------------------------
  管理画面ページ（ON/OFF 切り替え）
--------------------------------------------------------------*/
function gti_keni_box_style_compat_page() {

    // 保存処理
    if ( isset( $_POST['gti_keni_box_style_compat_nonce'] )
        && wp_verify_nonce( $_POST['gti_keni_box_style_compat_nonce'], 'gti_keni_box_style_compat' ) ) {

        $enabled = isset( $_POST['gti_keni_box_style_compat_enabled'] ) ? '1' : '0';
        update_option( 'gti_keni_box_style_compat_enabled', $enabled );

        echo '<div class="updated"><p>設定を保存しました。</p></div>';
    }

    $enabled = get_option( 'gti_keni_box_style_compat_enabled', '1' ); // デフォルト有効
    ?>

    <div class="wrap">
        <h1>賢威ボックス互換レイヤー</h1>

        <p>
            賢威8 時代の <code>.box_style .box_style_blue</code> などのボックスを、<br>
            SYN OWND でもそれっぽいデザインで表示するための互換レイヤーです。<br>
            既存 HTML はそのままで、CSS とブロックパターンだけを提供します。
        </p>

        <form method="post">
            <?php wp_nonce_field( 'gti_keni_box_style_compat', 'gti_keni_box_style_compat_nonce' ); ?>

            <label>
                <input type="checkbox" name="gti_keni_box_style_compat_enabled" value="1"
                    <?php checked( $enabled, '1' ); ?>>
                賢威ボックス互換レイヤーを有効にする
            </label>

            <?php submit_button( '設定を保存' ); ?>
        </form>

        <h2>対象となるクラス</h2>
        <ul>
            <li><code>.box_style .box_style_blue</code></li>
            <li><code>.box_style .box_style_green</code></li>
            <li><code>.box_style .box_style_orange</code></li>
            <li><code>.box_style .box_style_red</code></li>
            <li><code>.box_style .box_style_pink</code></li>
            <li><code>.box_style .box_style_yellow</code></li>
            <li><code>.box_style .box_style_gray</code></li>
        </ul>

        <p>※既存の HTML：<br>
            <code>&lt;div class="box_style box_style_blue"&gt;...&lt;/div&gt;</code><br>
            のようなマークアップを変更せずに装飾します。
        </p>
    </div>

    <?php
}

/*--------------------------------------------------------------
  ボックススタイル一覧（フィルタで拡張可能）
--------------------------------------------------------------*/
/**
 * slug => [ 'label' => 表示名, 'class' => そのまま付ける class 属性 ]
 */
function gti_keni_box_styles() {
    $styles = [
        'blue' => [
            'label' => '賢威ボックス（青）',
            'class' => 'box_style box_style_blue',
        ],
        'green' => [
            'label' => '賢威ボックス（緑）',
            'class' => 'box_style box_style_green',
        ],
        'orange' => [
            'label' => '賢威ボックス（オレンジ）',
            'class' => 'box_style box_style_orange',
        ],
        'red' => [
            'label' => '賢威ボックス（赤）',
            'class' => 'box_style box_style_red',
        ],
        'pink' => [
            'label' => '賢威ボックス（ピンク）',
            'class' => 'box_style box_style_pink',
        ],
        'yellow' => [
            'label' => '賢威ボックス（黄）',
            'class' => 'box_style box_style_yellow',
        ],
        'gray' => [
            'label' => '賢威ボックス（グレー）',
            'class' => 'box_style box_style_gray',
        ],
    ];

    /**
     * ボックススタイルをフィルタ
     *
     * 追加例:
     * add_filter( 'gti_keni_tools_box_styles', function( $styles ) {
     *     $styles['black'] = [
     *         'label' => '賢威ボックス（黒）',
     *         'class' => 'box_style box_style_black',
     *     ];
     *     return $styles;
     * } );
     */
    return apply_filters( 'gti_keni_tools_box_styles', $styles );
}

/**
 * 有効かどうか判定するヘルパー
 */
function gti_keni_box_style_compat_is_enabled() {
    return get_option( 'gti_keni_box_style_compat_enabled', '1' ) !== '0';
}

/*--------------------------------------------------------------
  有効時だけフックを登録
--------------------------------------------------------------*/
// パターンカテゴリ & パターン
add_action( 'init', 'gti_keni_register_box_pattern_category' );
add_action( 'init', 'gti_keni_register_box_patterns' );

// CSS 読み込み（フロント＋ブロックエディタ＋サイトエディタ）
add_action( 'enqueue_block_assets', 'gti_keni_box_enqueue_assets', 999 );

/*--------------------------------------------------------------
  ブロックパターンカテゴリ
--------------------------------------------------------------*/
function gti_keni_register_box_pattern_category() {
    if ( ! gti_keni_box_style_compat_is_enabled() ) {
        return;
    }

    if ( ! function_exists( 'register_block_pattern_category' ) ) {
        return;
    }

    register_block_pattern_category(
        'gti-keni-tools',
        [
            'label' => 'GTI 賢威→SYN 互換',
        ]
    );
}

/*--------------------------------------------------------------
  ブロックパターン登録
--------------------------------------------------------------*/
function gti_keni_register_box_patterns() {
    if ( ! gti_keni_box_style_compat_is_enabled() ) {
        return;
    }

    if ( ! function_exists( 'register_block_pattern' ) ) {
        return;
    }

    $styles = gti_keni_box_styles();

    foreach ( $styles as $slug => $style ) {

        register_block_pattern(
            'gti-keni-tools/box-' . $slug,
            [
                'title'       => $style['label'],
                'categories'  => [ 'gti-keni-tools' ],
                'description' => '賢威のボックススタイルを SYN 風デザインで再現したパターンです。',
                // あえて既存と同じ class だけを使う（余計な class は付けない）
                'content'     => sprintf(
                    '<!-- wp:html -->
<div class="%1$s">
  <div class="box_inner">
    <div class="box_style_title">
      <span class="box_style_title_inner">見出しテキストを入力</span>
    </div>
    <p>本文テキストをここに入力してください。</p>
  </div>
</div>
<!-- /wp:html -->',
                    esc_attr( $style['class'] )
                ),
            ]
        );
    }
}

/*--------------------------------------------------------------
  CSS 読み込み（外部ファイル + キャッシュバスター）
--------------------------------------------------------------*/
function gti_keni_box_enqueue_assets() {
    if ( ! gti_keni_box_style_compat_is_enabled() ) {
        return;
    }

    $handle = 'gti-keni-box-style-compat';

    // 1. 子テーマ → 2. 親テーマ → 3. プラグイン
    $child_path  = get_stylesheet_directory() . '/gti-keni-tools/box-style-compat.css';
    $child_url   = get_stylesheet_directory_uri() . '/gti-keni-tools/box-style-compat.css';

    $parent_path = get_template_directory() . '/gti-keni-tools/box-style-compat.css';
    $parent_url  = get_template_directory_uri() . '/gti-keni-tools/box-style-compat.css';

    $plugin_path = GTI_KENI_TOOLS_DIR . 'assets/css/box-style-compat.css';
    $plugin_url  = GTI_KENI_TOOLS_URL . 'assets/css/box-style-compat.css';

    if ( file_exists( $child_path ) ) {
        $path = $child_path;
        $url  = $child_url;
    } elseif ( file_exists( $parent_path ) ) {
        $path = $parent_path;
        $url  = $parent_url;
    } else {
        $path = $plugin_path;
        $url  = $plugin_url;
    }

    // CSS が更新されるたびに version が変わるのでキャッシュ無効化できる
    if ( file_exists( $path ) ) {
        $ver = filemtime( $path );
    } elseif ( defined( 'GTI_KENI_TOOLS_VERSION' ) ) {
        // 念のためプラグインのバージョンも fallback に
        $ver = GTI_KENI_TOOLS_VERSION;
    } else {
        $ver = false; // 何もなければ WP デフォルト
    }

    // フロント & ブロックエディタ（enqueue_block_assets から呼ばれる前提）
    wp_enqueue_style(
        $handle,
        $url,
        [ 'wp-block-library' ],
        $ver
    );
}


/**
 * クラシックエディタ（TinyMCE）にもスタイルを適用
 */
add_filter( 'mce_css', 'gti_keni_add_mce_css' );
function gti_keni_add_mce_css( $mce_css ) {
    if ( ! gti_keni_box_style_compat_is_enabled() ) {
        return $mce_css;
    }

    $plugin_css = GTI_KENI_TOOLS_URL . 'assets/css/box-style-compat.css';

    if ( ! empty( $mce_css ) ) {
        $mce_css .= ',';
    }
    $mce_css .= $plugin_css;

    return $mce_css;
}
