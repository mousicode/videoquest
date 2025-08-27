<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * لیست ویدیوها به صورت آکاردئون
 * [vq_video_list category="all"]
 */
function vq_video_list_shortcode($atts){
  $atts = shortcode_atts([
    'category' => 'all',
  ], $atts);

  // ✅ از چند پست‌تایپ پشتیبانی + فقط منتشرشده‌ها
  $args = [
    'post_type'      => ['vq_video','videoquest'],
    'posts_per_page' => -1,
    'post_status'    => 'publish',
    'orderby'        => 'ID',
    'order'          => 'ASC',
  ];

  // ✅ فیلتر دسته روی هر دو taxonomy: vq_category و category
  if ( !empty($atts['category']) && $atts['category'] !== 'all' ) {
    $slug = sanitize_title($atts['category']);
    $args['tax_query'] = [
      'relation' => 'OR',
      [
        'taxonomy' => 'vq_category',
        'field'    => 'slug',
        'terms'    => $slug,
      ],
      [
        'taxonomy' => 'category',
        'field'    => 'slug',
        'terms'    => $slug,
      ],
    ];
  }

  $q = new WP_Query($args);

  ob_start();
  if( ! $q->have_posts() ){
    echo '<div class="vq-empty">ویدیویی یافت نشد.</div>';
    return ob_get_clean();
  }

  echo '<div class="vq-video-list">';
  $can_view = true;
  $index    = 0;

  while( $q->have_posts() ){
    $q->the_post();
    $video_id = get_the_ID();
    $user_id  = get_current_user_id();

    $viewed = get_user_meta($user_id, "vq_viewed_{$video_id}", true);
    $brand  = get_post_meta($video_id, 'vq_brand', true);
    $points = get_post_meta($video_id, 'vq_reward_points', true);
    if ($points === '') $points = 100;

    // فایل ویدیو (MP4)
    $url = get_post_meta($video_id, '_vq_video_file', true);

    // امتیاز فعلی برای نمایش اولیه
    $avg = get_post_meta($video_id, 'vq_video_rating_avg', true);
    $cnt = get_post_meta($video_id, 'vq_video_rating_count', true);
    if ($avg === '' ) $avg = 0;
    if ($cnt === '' ) $cnt = 0;

    echo '<div class="vq-step-card" data-step-index="'.esc_attr($index).'">';

      echo '<div class="vq-progress"><div class="vq-progress-bar"></div></div>';

      echo '<div class="vq-step-header"><h3>'.esc_html(get_the_title()).'</h3>';
      if( $brand ){
        echo '<span class="vq-brand">'.esc_html($brand).'</span>';
      }
      echo '</div>'; // .vq-step-header

      echo '<div class="vq-step-body">';

      if( ! $can_view ){
        echo '<div class="vq-locked">🔒 باز می‌شود پس از مشاهده قبلی</div>';
      }

      echo '<div class="vq-video-wrap"'.( $can_view ? '' : ' style="display:none"' ).'>';
        echo '<video class="vq-player vq-no-seek" controls preload="metadata" controlsList="nodownload noplaybackrate noremoteplayback" disablePictureInPicture oncontextmenu="return false" data-video-id="'.esc_attr($video_id).'">';
        if( $url ){
          echo '<source src="'.esc_url($url).'" type="video/mp4">';
        }
        echo '</video>';

        echo '<div class="vq-video-meta">مدت: <span class="vq-duration" data-video-id="'.esc_attr($video_id).'">--:--</span>';
        echo ' · امتیاز: '.intval($points).'</div>';

        echo '<button class="vq-next-step vq-start-quiz" style="display:none" data-target="quiz-'.esc_attr($index).'">شروع آزمون</button>';
      echo '</div>'; // .vq-video-wrap

      // آزمون
      $quiz = get_post_meta($video_id, 'vq_quiz', true);
      if ( is_array($quiz) && !empty($quiz) ){
        echo '<div id="quiz-'.esc_attr($index).'" class="vq-quiz-step" style="display:none">';
        foreach($quiz as $qi=>$qrow){
          $question = isset($qrow['question']) ? $qrow['question'] : '';
          $options  = isset($qrow['options']) && is_array($qrow['options']) ? $qrow['options'] : [];
          echo '<div class="vq-q"><p><b>'.esc_html($question).'</b></p>';
          foreach($options as $oi=>$opt){
            echo '<label><input type="radio" name="quiz_'.esc_attr($video_id).'_'.esc_attr($qi).'" value="'.esc_attr($oi).'"> '.esc_html($opt).'</label><br>';
          }
          echo '</div>';
        }
        echo '<button class="vq-quiz-submit" data-video="'.esc_attr($video_id).'">ارسال آزمون</button>';
        echo '<div class="vq-quiz-feedback" style="display:none"></div>';
        echo '</div>';
      }

      // امتیازدهی + خلاصه امتیاز
      echo '<div class="vq-survey-step" style="display:none" id="survey-'.esc_attr($index).'">';
        echo '<p>کیفیت آزمون را ارزیابی کنید:</p>';
        echo '<div class="vq-video-rate-wrap">';
          echo '<div class="vq-video-rating" data-video="'.esc_attr($video_id).'">';
            for($i=1;$i<=5;$i++){
              echo '<span class="star" data-value="'.esc_attr($i).'">★</span>';
            }
          echo '</div>';

          // ✅ نمایش میانگین و تعداد رأی (JS آن را زنده آپدیت می‌کند)
          echo '<div class="vq-rating-summary">میانگین: <b class="vq-avg">'.esc_html($avg).'</b> از 5 · <span class="vq-count">'.intval($cnt).'</span> رای</div>';
        echo '</div>'; // .vq-video-rate-wrap

        echo '<button class="vq-next-video" data-index="'.esc_attr($index).'">رفتن به ویدیو بعدی</button>';
      echo '</div>'; // .vq-survey-step

      echo '</div>'; // .vq-step-body
    echo '</div>'; // .vq-step-card

    if( ! $viewed ){
      $can_view = false; // قفل کردن کارت‌های بعدی تا تکمیل قبلی
    }
    $index++;
  }

  echo '</div>'; // .vq-video-list
  wp_reset_postdata();

  return ob_get_clean();
}
add_shortcode('vq_video_list','vq_video_list_shortcode');

/**
 * بهترین ویدیوها بر اساس میانگین امتیاز
 * [vq_top_videos count="10"]
 */
function vq_top_videos_shortcode($atts){
  $atts = shortcode_atts(['count'=>10], $atts);

  $q = new WP_Query([
    'post_type'      => ['vq_video','videoquest'],
    'post_status'    => 'publish',
    'posts_per_page' => intval($atts['count']),
    'meta_key'       => 'vq_video_rating_avg',
    'orderby'        => 'meta_value_num',
    'order'          => 'DESC',
  ]);

  ob_start();
  echo '<div class="vq-top-videos">';
  if( $q->have_posts() ){
    while( $q->have_posts() ){ $q->the_post();
      $vid = get_the_ID();
      $avg = get_post_meta($vid,'vq_video_rating_avg',true);
      $cnt = get_post_meta($vid,'vq_video_rating_count',true);
      if ($avg === '' ) $avg = 0;
      if ($cnt === '' ) $cnt = 0;
      echo '<div class="vq-top-item"><span class="vq-top-title">'.esc_html(get_the_title()).'</span> <span class="vq-top-meta">— میانگین: '.esc_html($avg).' از 5 · '.intval($cnt).' رای</span></div>';
    }
    wp_reset_postdata();
  } else {
    echo '<div class="vq-top-item">فعلاً ویدیوی امتیازدار نداریم.</div>';
  }
  echo '</div>';
  return ob_get_clean();
}
add_shortcode('vq_top_videos','vq_top_videos_shortcode');

/**
 * نمایش گالری ویدیوها به صورت لیست و پخش در یک پلیر
 * [vq_video_gallery category="all"]
 */
function vq_video_gallery_shortcode($atts){
  $atts = shortcode_atts([
    'category' => 'all',
  ], $atts);

  $args = [
    'post_type'      => ['vq_video','videoquest'],
    'posts_per_page' => -1,
    'post_status'    => 'publish',
    'orderby'        => 'ID',
    'order'          => 'ASC',
  ];

  if ( !empty($atts['category']) && $atts['category'] !== 'all' ) {
    $slug = sanitize_title($atts['category']);
    $args['tax_query'] = [
      'relation' => 'OR',
      [
        'taxonomy' => 'vq_category',
        'field'    => 'slug',
        'terms'    => $slug,
      ],
      [
        'taxonomy' => 'category',
        'field'    => 'slug',
        'terms'    => $slug,
      ],
    ];
  }

  $q = new WP_Query($args);
  if( ! $q->have_posts() ){
    return '<div class="vq-empty">ویدیویی یافت نشد.</div>';
  }

  $video_ids = [];
  while( $q->have_posts() ){ $q->the_post();
    $video_ids[] = get_the_ID();
  }
  wp_reset_postdata();

  if( empty($video_ids) ){
    return '<div class="vq-empty">ویدیویی یافت نشد.</div>';
  }

  // Helper برای ساختن محتوای ویدیو با همه امکانات
  $render_video = function($vid,$index){
    $user_id = get_current_user_id();
    $viewed  = get_user_meta($user_id, "vq_viewed_{$vid}", true);
    $brand   = get_post_meta($vid,'vq_brand',true);
    $points  = get_post_meta($vid,'vq_reward_points',true);
    if($points==='') $points = 100;
    $url     = get_post_meta($vid,'_vq_video_file',true);
    $avg     = get_post_meta($vid,'vq_video_rating_avg',true);
    $cnt     = get_post_meta($vid,'vq_video_rating_count',true);
    if($avg==='') $avg = 0; if($cnt==='') $cnt = 0;
    $quiz    = get_post_meta($vid,'vq_quiz',true);

    ob_start();
    echo '<div class="vq-step-card" data-step-index="'.esc_attr($index).'">';
      echo '<div class="vq-progress"><div class="vq-progress-bar"></div></div>';
      echo '<div class="vq-step-header"><h3>'.esc_html(get_the_title($vid)).'</h3>';
      if($brand){ echo '<span class="vq-brand">'.esc_html($brand).'</span>'; }
      echo '</div>';
      echo '<div class="vq-step-body" style="display:block">';
        echo '<div class="vq-video-wrap">';
          echo '<video class="vq-player vq-no-seek" controls preload="metadata" controlsList="nodownload noplaybackrate noremoteplayback" disablePictureInPicture oncontextmenu="return false" data-video-id="'.esc_attr($vid).'">';
            if($url){ echo '<source src="'.esc_url($url).'" type="video/mp4">'; }
          echo '</video>';
          echo '<div class="vq-video-meta">مدت: <span class="vq-duration" data-video-id="'.esc_attr($vid).'">--:--</span> · امتیاز: '.intval($points).'</div>';
          echo '<button class="vq-next-step vq-start-quiz" style="display:none" data-target="quiz-'.esc_attr($vid).'">شروع آزمون</button>';
        echo '</div>'; // .vq-video-wrap

        if( is_array($quiz) && !empty($quiz) ){
          echo '<div id="quiz-'.esc_attr($vid).'" class="vq-quiz-step" style="display:none">';
          foreach($quiz as $qi=>$qrow){
            $question = isset($qrow['question']) ? $qrow['question'] : '';
            $options  = isset($qrow['options']) && is_array($qrow['options']) ? $qrow['options'] : [];
            echo '<div class="vq-q"><p><b>'.esc_html($question).'</b></p>';
            foreach($options as $oi=>$opt){
              echo '<label><input type="radio" name="quiz_'.esc_attr($vid).'_'.esc_attr($qi).'" value="'.esc_attr($oi).'"> '.esc_html($opt).'</label><br>';
            }
            echo '</div>';
          }
          echo '<button class="vq-quiz-submit" data-video="'.esc_attr($vid).'">ارسال آزمون</button>';
          echo '<div class="vq-quiz-feedback" style="display:none"></div>';
          echo '</div>';
        }

        echo '<div class="vq-survey-step" style="display:none" id="survey-'.esc_attr($vid).'">';
          echo '<p>کیفیت آزمون را ارزیابی کنید:</p>';
          echo '<div class="vq-video-rate-wrap">';
            echo '<div class="vq-video-rating" data-video="'.esc_attr($vid).'">';
              for($i=1;$i<=5;$i++){ echo '<span class="star" data-value="'.esc_attr($i).'">★</span>'; }
            echo '</div>';
            echo '<div class="vq-rating-summary">میانگین: <b class="vq-avg">'.esc_html($avg).'</b> از 5 · <span class="vq-count">'.intval($cnt).'</span> رای</div>';
          echo '</div>';
          echo '<button class="vq-next-video" data-index="'.esc_attr($index).'">رفتن به ویدیو بعدی</button>';
        echo '</div>';
      echo '</div>'; // .vq-step-body
    echo '</div>'; // .vq-step-card
    return ob_get_clean();
  };

  ob_start();
  echo '<div class="vq-gallery">';
    // Player area
    echo '<div class="vq-gallery-player" id="vq-gallery-main">';
      echo $render_video($video_ids[0],0);
    echo '</div>';

    // List of videos
    echo '<div class="vq-gallery-list">';
    foreach($video_ids as $i=>$vid){
      $thumb  = get_the_post_thumbnail_url($vid,'thumbnail');
      $active = $i===0 ? ' active' : '';
      echo '<div class="vq-gallery-item'.esc_attr($active).'" data-video-id="'.esc_attr($vid).'" data-index="'.esc_attr($i).'">';
        echo '<span class="vq-gallery-index">'.intval($i+1).'</span>';
        if($thumb){ echo '<img src="'.esc_url($thumb).'" alt="">'; }
        echo '<span class="vq-gallery-title">'.esc_html(get_the_title($vid)).'</span>';
      echo '</div>';
    }
    echo '</div>'; // .vq-gallery-list

    // Hidden templates for other videos
    foreach($video_ids as $i=>$vid){
      if($i===0) continue;
      echo '<div id="vq-gallery-content-'.esc_attr($vid).'" class="vq-gallery-template" style="display:none">';
        echo $render_video($vid,$i);
      echo '</div>';
    }
  echo '</div>';

  return ob_get_clean();
}
add_shortcode('vq_video_gallery','vq_video_gallery_shortcode');
