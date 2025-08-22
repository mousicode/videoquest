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
    ?>
    <style>
      .vq-admin-field{margin-bottom:10px}
      .vq-admin-field label{display:block;font-weight:600;margin-bottom:4px}
      .vq-admin-field input[type="text"],
      .vq-admin-field input[type="url"]{width:100%}
    </style>

    <div class="vq-admin-field">
        <label for="vq_brand">برند</label>
        <input type="text" id="vq_brand" name="vq_brand" value="<?php echo esc_attr($brand); ?>">
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
