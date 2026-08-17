<?php
if (!defined('ABSPATH')) exit;

/**
 * 期間別ランキング機能 & ウィジェット
 * 
 * 日別PVをメタデータ (_gti_views_daily_YYYY-MM-DD) に記録し、
 * Cronで定期的に期間別PVを集計して保存する。
 */

// --------------------------------------------------
// モジュール登録
// --------------------------------------------------
$GLOBALS['gti_keni_tools']['gti-period-ranking'] = [
    'title'    => 'SYN期間別ランキング設定',
    'menu'     => 'SYN期間別ランキング',
    'callback' => 'gti_period_ranking_settings_page',
    'priority' => 50,
];

// --------------------------------------------------
// 管理画面UI
// --------------------------------------------------
function gti_period_ranking_settings_page() {
    $enable = get_option('gti_period_ranking_enable', false);

    if (isset($_POST['gti_period_ranking_nonce']) && wp_verify_nonce($_POST['gti_period_ranking_nonce'], 'gti_period_ranking_save')) {
        $enable = !empty($_POST['gti_period_ranking_enable']);
        update_option('gti_period_ranking_enable', $enable);
        gti_keni_notice('設定を保存しました。', 'success');
    }
    ?>
    <div class="wrap">
        <h1>SYN 期間別ランキング設定</h1>
        <form method="post">
            <?php wp_nonce_field('gti_period_ranking_save', 'gti_period_ranking_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">機能の有効化</th>
                    <td>
                        <label>
                            <input type="checkbox" name="gti_period_ranking_enable" value="1" <?php checked($enable); ?>>
                            期間別ランキング機能（PV計測・ウィジェット）を有効にする
                        </label>
                        <p class="description">有効にすると、日別PVの計測が開始され、ウィジェット画面に「【GTI】期間別ランキング」が追加されます。</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('保存する'); ?>
        </form>
    </div>
    <?php
}

// 機能が無効ならここで終了
if (!get_option('gti_period_ranking_enable', false)) {
    return;
}

class GTI_Period_Ranking_Logic {

    public function __construct() {
        // PVカウント処理
        add_action('wp_head', [$this, 'count_views']);

        // 定期集計イベントの登録
        add_action('gti_period_ranking_calc_event', [$this, 'calc_period_views']);

        // スケジュールの設定（有効化時）
        if (!wp_next_scheduled('gti_period_ranking_calc_event')) {
            wp_schedule_event(time(), 'hourly', 'gti_period_ranking_calc_event');
        }
    }

    /**
     * PVをカウントする
     */
    public function count_views() {
        if (!is_single()) {
            return;
        }

        global $post;
        if (empty($post)) {
            return;
        }

        // ボット除外（簡易的）
        if (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/bot|crawl|slurp|spider|mediapartners/i', $_SERVER['HTTP_USER_AGENT'])) {
            return;
        }

        // 同一セッション/IPでの重複カウント防止（簡易的: Cookie or Transient）
        // ここではTransientを使用 (IP + PostID)
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $transient_key = 'gti_pv_' . md5($post->ID . $ip . date('Ymd'));
        
        if (get_transient($transient_key)) {
            return;
        }

        // カウントアップ
        $today = date('Y-m-d');
        $meta_key = '_gti_views_daily_' . $today;
        
        // メタデータの更新（存在しなければ作成、あればインクリメント）
        // get_post_meta せずに直接SQLでインクリメントも可能だが、WP標準関数で実装
        $current_views = (int)get_post_meta($post->ID, $meta_key, true);
        update_post_meta($post->ID, $meta_key, $current_views + 1);

        // 1時間有効なTransientをセット
        set_transient($transient_key, 1, HOUR_IN_SECONDS);
    }

    /**
     * 期間別PVを集計する (Cron用)
     * SQL最適化版: 全投稿に対して一括で集計を行う
     */
    public function calc_period_views() {
        global $wpdb;

        // 定義された期間を取得
        $periods = GTI_Period_Ranking_Widget::get_ranking_periods();

        // 期間ごとに集計
        foreach ($periods as $slug => $period) {
            if ($slug === 'total') continue; // 全期間は集計不要（または別ロジック）

            $days = isset($period['days']) ? (int)$period['days'] : 7;
            $meta_key_target = '_gti_views_' . $slug;

            // 集計対象の日付範囲のメタキーを生成するためのSQL条件
            // _gti_views_daily_YYYY-MM-DD 形式
            // 日付計算はPHPで行い、IN句またはBETWEEN的なLIKE検索で絞り込む
            
            // 過去N日分の日付文字列を生成
            $date_keys = [];
            for ($i = 0; $i < $days; $i++) {
                $date_keys[] = '_gti_views_daily_' . date('Y-m-d', strtotime("-{$i} days"));
            }

            if (empty($date_keys)) continue;

            // プレースホルダーの準備
            $placeholders = implode("', '", array_map('esc_sql', $date_keys));
            
            // SQL: post_id ごとに meta_value を合計する
            // postmetaテーブルから、指定した日付キーを持つレコードを抽出し、post_idでグルーピングして合計
            $sql = "
                SELECT post_id, SUM(meta_value) as total_views
                FROM {$wpdb->postmeta}
                WHERE meta_key IN ('{$placeholders}')
                GROUP BY post_id
            ";

            $results = $wpdb->get_results($sql);

            // 結果を保存
            // 既存の値を削除してから新しい値をセットする方が安全だが、
            // update_post_meta は既存があれば更新、なければ追加してくれる。
            // ただし、集計結果が0になった（期間外になった）記事のケアが必要。
            // 今回は「集計結果がある記事」のみ更新する。
            // ※厳密には、以前ランクインしていたが圏外になった記事の値を0にする処理が必要かもしれないが、
            //   ランキング表示時に meta_key が存在しない/0の場合は表示されないので一旦更新のみとする。
            
            if ($results) {
                foreach ($results as $row) {
                    update_post_meta($row->post_id, $meta_key_target, $row->total_views);
                }
            }
        }

        // --- 古い日別データの削除（ガベージコレクション） ---
        // 設定されている期間の中で最大の日数を取得
        $max_days = 0;
        foreach ($periods as $period) {
            if (isset($period['days']) && $period['days'] > $max_days) {
                $max_days = (int)$period['days'];
            }
        }

        // 最大期間 + 1日 以前のデータを削除
        if ($max_days > 0) {
            $cutoff_date = date('Y-m-d', strtotime('-' . ($max_days + 1) . ' days'));
            $cutoff_key = '_gti_views_daily_' . $cutoff_date;
            
            // 文字列比較で削除 (YYYY-MM-DDなので辞書順比較が可能)
            $sql_cleanup = $wpdb->prepare(
                "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s AND meta_key <= %s",
                '_gti_views_daily_%',
                $cutoff_key
            );
            $wpdb->query($sql_cleanup);
        }
    }
}

// ロジック初期化
new GTI_Period_Ranking_Logic();


/**
 * ランキングウィジェット
 * SYN OWND (ranking-widget.php) のデザインを移植
 */
class GTI_Period_Ranking_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'gti_period_ranking_widget',
            '【GTI】期間別ランキング',
            ['description' => '集計期間（週間・月間・全期間）とカテゴリーを指定できるランキングウィジェットです。']
        );
    }

    /**
     * 利用可能なランキング期間を取得
     */
    public static function get_ranking_periods() {
        $periods = [
            'weekly'  => ['label' => '週間 (過去7日間)', 'days' => 7],
            'monthly' => ['label' => '月間 (過去30日間)', 'days' => 30],
            '3months' => ['label' => '3ヶ月 (過去90日間)', 'days' => 90],
            'total'   => ['label' => '全期間', 'days' => 0],
        ];

        // フィルターで拡張可能にする
        return apply_filters('gti_period_ranking_ranges', $periods);
        
        /**
         * 追加例
         * ※期間が長いほど、集計時のSQL検索条件（IN句）が増えるため、
         *   サーバー負荷が高くなる可能性があります。設定時はご注意ください。
         * 
        ```
        add_filter('gti_period_ranking_ranges', function($periods) {
        $periods['6months'] = ['label' => '6ヶ月 (過去180日間)', 'days' => 180];
        return $periods;
        });
        ```
         */
    }

    /**
     * ウィジェットのフロント表示
     */
    public function widget($args, $instance) {
        $instance = wp_parse_args(
            (array) $instance,
            array(
                'title'            => '',
                'number'           => 5,
                'period'           => 'total',
                'category'         => 0,
                'match_current_cat'=> 0,
                'hide_zero_views'  => 0,
                'ttl_count'        => '',
                'disp_design'      => 'separate',
                'disp_view'        => 1,
                'disp_img'         => 1,
                'disp_img_size'    => 0,
                'disp_img_reverse' => 0,
                'disp_author'      => 1,
                'disp_date'        => 1,
                'disp_modified'    => 1,
                'ignore_ids'       => '',
            )
        );

        echo wp_kses_post($args['before_widget']);
        $title = apply_filters('widget_title', $instance['title'], $instance, $this->id_base);
        if (!empty($title)) {
            echo wp_kses_post($args['before_title']) . esc_html($title) . wp_kses_post($args['after_title']);
        }

        // クエリ設定
        $query_args = [
            'post_type'           => 'post',
            'posts_per_page'      => absint($instance['number']),
            'ignore_sticky_posts' => 1,
            'orderby'             => 'meta_value_num',
        ];

        // 除外ID
        if (!empty($instance['ignore_ids'])) {
            $ignore_ids = preg_replace('/[^0-9,]/', '', $instance['ignore_ids']);
            $ignore_ids_array = array_filter(array_map('intval', explode(',', $ignore_ids)));
            if (!empty($ignore_ids_array)) {
                $query_args['post__not_in'] = $ignore_ids_array;
            }
        }

        // 期間設定
        $period_slug = $instance['period'];
        if ($period_slug === 'total') {
            $query_args['meta_key'] = 'post_views_count';
        } else {
            // カスタム期間も _gti_views_{slug} という命名規則に従う前提
            $query_args['meta_key'] = '_gti_views_' . $period_slug;
        }

        // 0 view 除外
        if (!empty($instance['hide_zero_views'])) {
            // meta_value_num で orderby しているので、meta_query で 0 より大きいものを指定
            $query_args['meta_query'] = [
                [
                    'key'     => $query_args['meta_key'],
                    'value'   => 0,
                    'compare' => '>',
                    'type'    => 'NUMERIC',
                ]
            ];
        }

        // カテゴリー絞り込み
        $tax_query = [];
        if (!empty($instance['match_current_cat'])) {
            if (is_category()) {
                $current_cat_id = get_queried_object_id();
                $tax_query[] = [
                    'taxonomy' => 'category',
                    'field' => 'term_id',
                    'terms' => $current_cat_id,
                ];
            } elseif (is_single()) {
                $cats = get_the_category();
                if (!empty($cats)) {
                    $tax_query[] = [
                        'taxonomy' => 'category',
                        'field' => 'term_id',
                        'terms' => $cats[0]->term_id,
                    ];
                }
            }
        } elseif (!empty($instance['category']) && $instance['category'] > 0) {
            $tax_query[] = [
                'taxonomy' => 'category',
                'field' => 'term_id',
                'terms' => $instance['category'],
            ];
        }

        if (!empty($tax_query)) {
            $query_args['tax_query'] = $tax_query;
        }

        $query = new WP_Query($query_args);

        if ($query->have_posts()) {
            echo '<div class="widget-post is-type-' . esc_attr($instance['disp_design']) . '"><ul class="widget-post__list">';
            while ($query->have_posts()) {
                $query->the_post();
                $rank = $query->current_post + 1;
                
                // リンク
                $link_classes = 'widget-post__link';
                if (empty($instance['disp_img'])) $link_classes .= ' is-noimage';
                if ($instance['disp_img_reverse']) $link_classes .= ' is-reverse';
                
                echo '<li class="widget-post__item">';
                echo '<a class="' . esc_attr($link_classes) . '" href="' . esc_url(get_permalink()) . '">';

                // 画像なしの場合のランク表示
                if (empty($instance['disp_img'])) {
                    /* translators: %d: 順位 */
                    $aria_label = sprintf(esc_attr__('%d位', 'synx'), $rank);
                    echo '<span class="widget-post__rank" aria-label="' . esc_html($aria_label) . '">' . esc_html($rank) . '</span>';
                }

                // 画像表示
                if ($instance['disp_img'] || 'thumb' === $instance['disp_design']) {
                    // SYNX\Utils があれば使う、なければ標準関数でフォールバック
                    if (function_exists('\SYNX\Utils\get_thumbnail_image_data')) {
                        $img = \SYNX\Utils\get_thumbnail_image_data(get_the_ID());
                    } else {
                        $thumb_id = get_post_thumbnail_id(get_the_ID());
                        $img_src = wp_get_attachment_image_src($thumb_id, 'medium'); // mediumくらいで
                        $img = [
                            'url'    => $img_src ? $img_src[0] : '', // No image URL fallback needed?
                            'width'  => $img_src ? $img_src[1] : '',
                            'height' => $img_src ? $img_src[2] : '',
                        ];
                    }

                    echo '<div class="widget-post__imgarea is-rank">';
                    /* translators: %d: 順位 */
                    echo '<span class="widget-post__rank" aria-label="' . sprintf(esc_attr__('%d位', 'synx'), esc_html($rank)) . '">' . esc_html($rank) . '</span>';
                    echo '<div class="widget-post__img' . ($instance['disp_img_size'] ? ' is-small' : '') . '">';
                    if (!empty($img['url'])) {
                        echo '<img loading="lazy" src="' . esc_url($img['url']) . '" width="' . esc_attr($img['width']) . '" height="' . esc_attr($img['height']) . '" alt="' . esc_attr(get_the_title()) . '">';
                    } else {
                        // NO IMAGE fallback
                        echo '<span class="no-image">No Image</span>';
                    }
                    echo '</div></div>';
                }

                echo '<div class="widget-post__txtarea">';

                // メタ情報（PV, 日付）
                if ($instance['disp_modified'] || $instance['disp_date'] || $instance['disp_view']) {
                    echo '<div class="widget-post__detail">';
                    if ($instance['disp_view']) {
                        // 期間に応じたPVを表示
                        $views = (int)get_post_meta(get_the_ID(), $query_args['meta_key'], true);
                        echo '<span class="widget-post__view">' . number_format_i18n($views) . ' views</span>';
                    }
                    if ($instance['disp_modified'] || $instance['disp_date']) {
                        echo '<div class="widget-post__timestamp">';
                        if (get_the_date('Ymd') !== get_the_modified_date('Ymd') && $instance['disp_modified']) {
                            echo '<p class="widget-post__timestamp-item"><i class="icon-change"></i>' . esc_html(get_the_modified_date()) . '</p>';
                        }
                        if ($instance['disp_date']) {
                            echo '<p class="widget-post__timestamp-item"><i class="icon-time"></i>' . esc_html(get_the_date()) . '</p>';
                        }
                        echo '</div>';
                    }
                    echo '</div>';
                }

                // タイトル
                echo '<p class="widget-post__ttl">';
                $title_text = get_the_title();
                if (!empty($instance['ttl_count'])) {
                    if (function_exists('\SYNX\Utils\cut_text')) {
                        $title_text = \SYNX\Utils\cut_text('title', $instance['ttl_count']);
                    } elseif (mb_strlen($title_text) > $instance['ttl_count']) {
                        $title_text = mb_substr($title_text, 0, $instance['ttl_count']) . '...';
                    }
                }
                echo esc_html($title_text);
                echo '</p>';

                // 著者
                if ($instance['disp_author']) {
                    echo '<p class="widget-post__author">';
                    echo '<span class="widget-post__author-img">' . get_avatar(get_the_author_meta('ID'), 24, '', esc_attr(get_the_author() . 'のアイコン')) . '</span>';
                    echo '<span class="widget-post__author-name">' . esc_html(get_the_author()) . '</span>';
                    echo '</p>';
                }

                echo '</div></a></li>';
            }
            wp_reset_postdata();
            echo '</ul></div>';
        }
        echo wp_kses_post($args['after_widget']);
    }

    /**
     * 設定フォーム
     */
    public function form($instance) {
        $instance = wp_parse_args(
            (array) $instance,
            array(
                'title'            => '',
                'number'           => 5,
                'period'           => 'total',
                'category'         => 0,
                'match_current_cat'=> 0,
                'hide_zero_views'  => 0,
                'ttl_count'        => '',
                'disp_design'      => 'separate',
                'disp_view'        => 1,
                'disp_img'         => 1,
                'disp_img_size'    => 0,
                'disp_img_reverse' => 0,
                'disp_author'      => 1,
                'disp_date'        => 1,
                'disp_modified'    => 1,
                'ignore_ids'       => '',
            )
        );

        $periods = self::get_ranking_periods();
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">タイトル:</label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($instance['title']); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('number')); ?>">表示件数:</label>
            <input type="number" id="<?php echo esc_attr($this->get_field_id('number')); ?>" name="<?php echo esc_attr($this->get_field_name('number')); ?>" min="1" max="10" value="<?php echo absint($instance['number']); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('period')); ?>">集計期間:</label>
            <select class="widefat" id="<?php echo esc_attr($this->get_field_id('period')); ?>" name="<?php echo esc_attr($this->get_field_name('period')); ?>">
                <?php foreach ($periods as $slug => $data) : ?>
                    <option value="<?php echo esc_attr($slug); ?>" <?php selected($instance['period'], $slug); ?>><?php echo esc_html($data['label']); ?></option>
                <?php endforeach; ?>
            </select>
            <p class="description">
                ※設定されている最大期間（例：月間なら30日）より古い日別データは自動的に削除されます。<br>
                ※期間が長いほど、集計処理の負荷が高くなる可能性があります。
            </p>
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('category')); ?>">カテゴリー指定:</label>
            <?php wp_dropdown_categories([
                'show_option_all' => 'すべてのカテゴリー',
                'name' => $this->get_field_name('category'),
                'selected' => $instance['category'],
                'class' => 'widefat',
                'value_field' => 'term_id',
            ]); ?>
        </p>
        <p>
            <input class="checkbox" type="checkbox" <?php checked($instance['match_current_cat'], 1); ?> id="<?php echo esc_attr($this->get_field_id('match_current_cat')); ?>" name="<?php echo esc_attr($this->get_field_name('match_current_cat')); ?>" value="1">
            <label for="<?php echo esc_attr($this->get_field_id('match_current_cat')); ?>">現在表示中のカテゴリーで絞り込む</label>
            <br><small>※有効時は「カテゴリー指定」の設定は無視されます。</small>
        </p>
        <p>
            <input class="checkbox" type="checkbox" <?php checked($instance['hide_zero_views'], 1); ?> id="<?php echo esc_attr($this->get_field_id('hide_zero_views')); ?>" name="<?php echo esc_attr($this->get_field_name('hide_zero_views')); ?>" value="1">
            <label for="<?php echo esc_attr($this->get_field_id('hide_zero_views')); ?>">閲覧数が0件の記事を表示しない</label>
        </p>
        <hr>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('ttl_count')); ?>">タイトルの文字数（制限する場合記入）:</label>
            <input type="number" id="<?php echo esc_attr($this->get_field_id('ttl_count')); ?>" name="<?php echo esc_attr($this->get_field_name('ttl_count')); ?>" min="0" value="<?php echo esc_attr($instance['ttl_count']); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('disp_design')); ?>">表示デザイン:</label>
            <select id="<?php echo esc_attr($this->get_field_id('disp_design')); ?>" name="<?php echo esc_attr($this->get_field_name('disp_design')); ?>" class="widefat">
                <option value="separate" <?php selected($instance['disp_design'], 'separate'); ?>>個別リスト型</option>
                <option value="list" <?php selected($instance['disp_design'], 'list'); ?>>リスト型</option>
                <option value="card" <?php selected($instance['disp_design'], 'card'); ?>>カード型</option>
                <option value="thumb" <?php selected($instance['disp_design'], 'thumb'); ?>>サムネイル型</option>
            </select>
        </p>
        <p>
            <label>詳細設定:</label><br>
            <label>
                <input id="<?php echo esc_attr($this->get_field_id('disp_view')); ?>" name="<?php echo esc_attr($this->get_field_name('disp_view')); ?>" type="checkbox" value="1" <?php checked((int)$instance['disp_view'], 1); ?>> ビューの件数を表示
            </label><br>
            <label>
                <input id="<?php echo esc_attr($this->get_field_id('disp_img')); ?>" name="<?php echo esc_attr($this->get_field_name('disp_img')); ?>" type="checkbox" value="1" <?php checked((int)$instance['disp_img'], 1); ?>> アイキャッチ画像を表示
            </label><br>
            <label>
                <input id="<?php echo esc_attr($this->get_field_id('disp_img_reverse')); ?>" name="<?php echo esc_attr($this->get_field_name('disp_img_reverse')); ?>" type="checkbox" value="1" <?php checked((int)$instance['disp_img_reverse'], 1); ?>> アイキャッチ画像を右に表示
            </label><br>
            <label>
                <input id="<?php echo esc_attr($this->get_field_id('disp_img_size')); ?>" name="<?php echo esc_attr($this->get_field_name('disp_img_size')); ?>" type="checkbox" value="1" <?php checked((int)$instance['disp_img_size'], 1); ?>> アイキャッチ画像を小さく表示
            </label><br>
            <label>
                <input id="<?php echo esc_attr($this->get_field_id('disp_author')); ?>" name="<?php echo esc_attr($this->get_field_name('disp_author')); ?>" type="checkbox" value="1" <?php checked((int)$instance['disp_author'], 1); ?>> 著者を表示
            </label><br>
            <label>
                <input id="<?php echo esc_attr($this->get_field_id('disp_date')); ?>" name="<?php echo esc_attr($this->get_field_name('disp_date')); ?>" type="checkbox" value="1" <?php checked((int)$instance['disp_date'], 1); ?>> 公開日を表示
            </label><br>
            <label>
                <input id="<?php echo esc_attr($this->get_field_id('disp_modified')); ?>" name="<?php echo esc_attr($this->get_field_name('disp_modified')); ?>" type="checkbox" value="1" <?php checked((int)$instance['disp_modified'], 1); ?>> 更新日を表示
            </label>
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('ignore_ids')); ?>">除外する投稿のID（カンマ区切り）:</label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('ignore_ids')); ?>" name="<?php echo esc_attr($this->get_field_name('ignore_ids')); ?>" type="text" value="<?php echo esc_attr($instance['ignore_ids']); ?>">
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $instance = [];
        $instance['title'] = sanitize_text_field($new_instance['title']);
        $instance['number'] = (int)$new_instance['number'];
        $instance['period'] = sanitize_text_field($new_instance['period']);
        $instance['category'] = (int)$new_instance['category'];
        $instance['match_current_cat'] = !empty($new_instance['match_current_cat']) ? 1 : 0;
        $instance['hide_zero_views'] = !empty($new_instance['hide_zero_views']) ? 1 : 0;
        
        $instance['ttl_count'] = is_numeric($new_instance['ttl_count']) ? absint($new_instance['ttl_count']) : '';
        $instance['disp_design'] = sanitize_text_field($new_instance['disp_design']);
        $instance['ignore_ids'] = sanitize_text_field($new_instance['ignore_ids']);

        foreach (array('disp_view', 'disp_img', 'disp_img_size', 'disp_img_reverse', 'disp_author', 'disp_date', 'disp_modified') as $key) {
            $instance[$key] = !empty($new_instance[$key]) ? 1 : 0;
        }

        return $instance;
    }
}

add_action('widgets_init', function() {
    register_widget('GTI_Period_Ranking_Widget');
});
