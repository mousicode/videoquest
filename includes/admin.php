<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * متاباکس‌ها
 */
add_action('add_meta_boxes', function () {
    add_meta_box(
        'vq_video_info',
        'اطلاعات ویدیو',
        'vq_render_video_info_metabox',
        'vq_video',
        'side',
        'default'
    );

    add_meta_box(
        'vq_quiz_metabox',
        'آزمون',
        'vq_render_quiz_metabox',
        'vq_video',
        'normal',
        'default'
    );
});

/**
 * رندر متاباکس اطلاعات ویدیو
 */
function vq_render_video_info_metabox($post){
    wp_nonce_field('vq_save_video_info','vq_video_info_nonce');

    $brand  = get_post_meta($post->ID, 'vq_brand', true);
    $cost   = get_post_meta($post->ID, 'vq_cost_per_view', true);
    $reward = get_post_meta($post->ID, 'vq_reward_points', true);
    // بسته به نسخه‌های قبلی‌ات یکی از این دو کلید استفاده شده؛ هر دو را می‌خوانیم و همان را ذخیره می‌کنیم.
    $video_url = get_post_meta($post->ID, '_vq_video_file', true);
    if (!$video_url) { $video_url = get_post_meta($post->ID, 'vq_video_url', true); }

    ?>
    <style>
      .vq-admin-field{margin-bottom:10px}
      .vq-admin-field label{display:block;font-weight:600;margin-bottom:4px}
      .vq-admin-field input[type="text"]{width:100%}
    </style>

    <div class="vq-admin-field">
        <label for="vq_brand">برند</label>
        <input type="text" id="vq_brand" name="vq_brand" value="<?php echo esc_attr($brand); ?>">
    </div>

    <div class="vq-admin-field">
        <label for="vq_video_url">لینک ویدیو (mp4)</label>
        <input type="text" id="vq_video_url" name="vq_video_url" placeholder="https://..." value="<?php echo esc_url($video_url); ?>">
        <small>می‌توانی لینک فایل را مستقیماً وارد کنی یا از کتابخانه رسانه آدرس بگیری.</small>
    </div>

    <div class="vq-admin-field">
        <label for="vq_cost_per_view">هزینه هر بازدید کامل</label>
        <input type="number" step="0.01" id="vq_cost_per_view" name="vq_cost_per_view" value="<?php echo esc_attr($cost); ?>">
    </div>

    <div class="vq-admin-field">
        <label for="vq_reward_points">امتیاز برای کاربر پس از مشاهده کامل</label>
        <input type="number" id="vq_reward_points" name="vq_reward_points" value="<?php echo esc_attr($reward); ?>">
    </div>
    <?php
}

/**
 * رندر متاباکس آزمون (رابط گرافیکی کامل)
 * ساختار ذخیره: vq_quiz = [
 *   [ 'question' => '...', 'options' => ['','','',''], 'correct' => 0..3 ],
 *   ...
 * ]
 */
function vq_render_quiz_metabox($post){
    wp_nonce_field('vq_save_quiz','vq_quiz_nonce');

    $quiz = get_post_meta($post->ID, 'vq_quiz', true);
    if (!is_array($quiz)) $quiz = [];

    ?>
    <style>
      .vq-quiz-wrapper{margin-top:8px}
      .vq-q-item{border:1px solid #ddd;border-radius:6px;padding:10px;margin-bottom:12px;background:#fafafa}
      .vq-q-row{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px}
      .vq-q-row label{font-weight:600}
      .vq-q-row input[type="text"]{width:100%}
      .vq-opt{display:flex;align-items:center;gap:6px;width:calc(50% - 4px)}
      .vq-opt input[type="text"]{width:100%}
      .vq-remove{color:#c00;cursor:pointer;border:1px solid #c00;border-radius:4px;padding:2px 8px;background:#fff}
      .vq-add{margin-top:10px}
    </style>

    <div id="vq-quiz-wrapper" class="vq-quiz-wrapper">
        <?php if (empty($quiz)) : ?>
            <?php $quiz = []; ?>
        <?php endif; ?>

        <?php foreach ($quiz as $i => $q): 
            $question = isset($q['question']) ? $q['question'] : '';
            $options  = isset($q['options']) && is_array($q['options']) ? $q['options'] : array('','','','');
            $correct  = isset($q['correct']) ? intval($q['correct']) : 0;
        ?>
        <div class="vq-q-item" data-index="<?php echo intval($i); ?>">
            <div class="vq-q-row">
                <label>سوال</label>
                <input type="text" name="vq_quiz[<?php echo $i; ?>][question]" value="<?php echo esc_attr($question); ?>" placeholder="متن سوال را بنویسید">
            </div>

            <div class="vq-q-row">
                <?php for($j=0; $j<4; $j++): ?>
                    <div class="vq-opt">
                        <label>گزینه <?php echo ($j+1); ?>:</label>
                        <input type="text" name="vq_quiz[<?php echo $i; ?>][options][<?php echo $j; ?>]" value="<?php echo esc_attr(isset($options[$j])?$options[$j]:''); ?>" placeholder="متن گزینه">
                        <label style="white-space:nowrap;">
                            <input type="radio" name="vq_quiz[<?php echo $i; ?>][correct]" value="<?php echo $j; ?>" <?php checked($correct,$j); ?>> صحیح
                        </label>
                    </div>
                <?php endfor; ?>
            </div>

            <button type="button" class="button vq-remove"><?php _e('حذف این سوال','vq'); ?></button>
        </div>
        <?php endforeach; ?>
    </div>

    <button type="button" class="button button-primary vq-add" id="vq-add-question">+ افزودن سوال</button>

    <script>
    (function($){
      let qIndex = <?php echo count($quiz); ?>;

      function template(idx){
        return `
        <div class="vq-q-item" data-index="${idx}">
          <div class="vq-q-row">
            <label>سوال</label>
            <input type="text" name="vq_quiz[${idx}][question]" value="" placeholder="متن سوال را بنویسید">
          </div>

          <div class="vq-q-row">
            ${[0,1,2,3].map(function(j){
              return `
              <div class="vq-opt">
                <label>گزینه ${j+1}:</label>
                <input type="text" name="vq_quiz[${idx}][options][${j}]" value="" placeholder="متن گزینه">
                <label style="white-space:nowrap;">
                    <input type="radio" name="vq_quiz[${idx}][correct]" value="${j}" ${j===0?'checked':''}> صحیح
                </label>
              </div>`;
            }).join('')}
          </div>

          <button type="button" class="button vq-remove">حذف این سوال</button>
        </div>`;
      }

      $('#vq-add-question').on('click', function(){
        $('#vq-quiz-wrapper').append(template(qIndex));
        qIndex++;
      });

      $('#vq-quiz-wrapper').on('click', '.vq-remove', function(){
        if(confirm('این سوال حذف شود؟')) $(this).closest('.vq-q-item').remove();
      });
    })(jQuery);
    </script>
    <?php
}

/**
 * ذخیره متاها
 */
add_action('save_post', function($post_id){
    // جلوگیری از ذخیره خودکار/ناخواسته
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( ! current_user_can('edit_post', $post_id) ) return;
    if ( get_post_type($post_id) !== 'vq_video' ) return;

    // اطلاعات ویدیو
    if ( isset($_POST['vq_video_info_nonce']) && wp_verify_nonce($_POST['vq_video_info_nonce'],'vq_save_video_info') ){
        if ( isset($_POST['vq_brand']) ){
            update_post_meta($post_id, 'vq_brand', sanitize_text_field($_POST['vq_brand']));
        }
        if ( isset($_POST['vq_video_url']) && $_POST['vq_video_url'] !== '' ){
            // هم vq_video_url و هم _vq_video_file را برای سازگاری ذخیره می‌کنیم
            $url = esc_url_raw($_POST['vq_video_url']);
            update_post_meta($post_id, 'vq_video_url', $url);
            update_post_meta($post_id, '_vq_video_file', $url);
        }
        if ( isset($_POST['vq_cost_per_view']) ){
            update_post_meta($post_id, 'vq_cost_per_view', floatval($_POST['vq_cost_per_view']));
        }
        if ( isset($_POST['vq_reward_points']) ){
            update_post_meta($post_id, 'vq_reward_points', intval($_POST['vq_reward_points']));
        }
    }

    // آزمون
    if ( isset($_POST['vq_quiz_nonce']) && wp_verify_nonce($_POST['vq_quiz_nonce'],'vq_save_quiz') ){
        $quiz = isset($_POST['vq_quiz']) ? (array) $_POST['vq_quiz'] : array();

        // ضد عفونی
        $clean = array();
        foreach ($quiz as $i => $q){
            if ( empty($q['question']) ) continue;
            $opts = isset($q['options']) && is_array($q['options']) ? $q['options'] : array();
            $opts_clean = array();
            for($j=0;$j<4;$j++){
                $opts_clean[$j] = isset($opts[$j]) ? sanitize_text_field($opts[$j]) : '';
            }
            $correct = isset($q['correct']) ? intval($q['correct']) : 0;
            if ($correct < 0 || $correct > 3) $correct = 0;

            $clean[] = array(
                'question' => sanitize_text_field($q['question']),
                'options'  => $opts_clean,
                'correct'  => $correct,
            );
        }
        update_post_meta($post_id, 'vq_quiz', $clean);
    }
});

/** صفحه مدیریت اسپانسرها و بودجه‌ها */
add_action('admin_menu', function(){
    add_submenu_page(
        'edit.php?post_type=vq_video',
        'اسپانسرها',
        'اسپانسرها',
        'manage_options',
        'vq_sponsors',
        'vq_render_sponsors_page'
    );
});

function vq_render_sponsors_page(){
    if( ! current_user_can('manage_options') ) return;

    if( isset($_POST['vq_sponsor_budgets']) ){
        check_admin_referer('vq_save_sponsors');
        $budgets = array();
        foreach( (array) $_POST['vq_sponsor_budgets'] as $brand=>$budget ){
            $brand = sanitize_text_field($brand);
            $budgets[$brand] = floatval($budget);
        }
        update_option('vq_sponsor_budgets', $budgets);
        echo '<div class="updated"><p>ذخیره شد.</p></div>';
    }

    $budgets = get_option('vq_sponsor_budgets', array());

    $posts = get_posts(array(
        'post_type'      => 'vq_video',
        'posts_per_page' => -1,
        'post_status'    => 'any',
    ));
    $brands = array();
    foreach( $posts as $p ){
        $b = get_post_meta($p->ID, 'vq_brand', true);
        if( $b ) $brands[$b] = true;
    }

    echo '<div class="wrap"><h1>اسپانسرها</h1><form method="post">';
    wp_nonce_field('vq_save_sponsors');
    echo '<table class="widefat"><thead><tr><th>برند</th><th>بودجه</th></tr></thead><tbody>';
    foreach( $brands as $b => $_ ){
        $val = isset($budgets[$b]) ? $budgets[$b] : '';
        echo '<tr><td>'.esc_html($b).'</td><td><input type="number" step="0.01" name="vq_sponsor_budgets['.esc_attr($b).']" value="'.esc_attr($val).'"></td></tr>';
    }
    echo '</tbody></table><p><input type="submit" class="button-primary" value="ذخیره"></p></form></div>';
}

