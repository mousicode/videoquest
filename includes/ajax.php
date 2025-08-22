<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// Mark viewed
add_action('wp_ajax_vq_mark_viewed','vq_mark_viewed');
add_action('wp_ajax_nopriv_vq_mark_viewed','vq_mark_viewed');
function vq_mark_viewed(){
  check_ajax_referer('vq_nonce','nonce');
  $vid = intval($_POST['video_id']);
  $uid = get_current_user_id();
  $already = get_user_meta($uid,"vq_viewed_$vid",true);
  if(!$already){
    update_user_meta($uid,"vq_viewed_$vid",true);
    $brand  = get_post_meta($vid,'vq_brand',true);
    $cost   = floatval(get_post_meta($vid,'vq_cost_per_view',true));
    $points = intval(get_post_meta($vid,'vq_reward_points',true));

    if($uid && $points > 0){
      $current_points = intval(get_user_meta($uid,'vq_user_points',true));
      update_user_meta($uid,'vq_user_points',$current_points + $points);
    }

    if($brand && $cost>0){
      $budgets = get_option('vq_sponsor_budgets',array());
      $current = isset($budgets[$brand]) ? floatval($budgets[$brand]) : 0;
      if($current > 0){
        $budgets[$brand] = max(0, $current - $cost);
        update_option('vq_sponsor_budgets',$budgets);
      }
    }
  }
  wp_send_json_success();
}
// Submit quiz
add_action('wp_ajax_vq_submit_quiz','vq_submit_quiz');
add_action('wp_ajax_nopriv_vq_submit_quiz','vq_submit_quiz');
function vq_submit_quiz(){
  check_ajax_referer('vq_nonce','nonce');
  $vid=intval($_POST['video_id']);
  $answers=isset($_POST['answers'])?(array)$_POST['answers']:array();
  $quiz=get_post_meta($vid,'vq_quiz',true);
  if(!is_array($quiz)) $quiz=array();
  $score=0; $total=count($quiz);
  foreach($quiz as $qi=>$q){
    if(isset($answers[$qi]) && intval($answers[$qi])===intval($q['correct'])) $score++;
  }
  $passed=$score==$total; wp_send_json_success(['score'=>$score,'total'=>$total,'passed'=>$passed]);
}
// Survey rate
add_action('wp_ajax_vq_survey_rate','vq_survey_rate');
add_action('wp_ajax_nopriv_vq_survey_rate','vq_survey_rate');
function vq_survey_rate(){
  check_ajax_referer('vq_nonce','nonce');
  $vid=intval($_POST['video_id']); $rate=intval($_POST['rate']);
  add_post_meta($vid,'vq_survey_rating',$rate); wp_send_json_success();
}


/** ذخیره امتیاز ویدیو (۱ تا ۵) برای هر کاربر */
add_action('wp_ajax_vq_rate_video','vq_rate_video');
add_action('wp_ajax_nopriv_vq_rate_video','vq_rate_video');
function vq_rate_video(){ // vq_rate_video_hardened
    check_ajax_referer('vq_nonce','nonce');
    $post_id = intval($_POST['video_id']);
    $rate    = max(1, min(5, intval($_POST['rate'])));
    $user_id = get_current_user_id();

    $ratings = get_post_meta($post_id, 'vq_video_ratings', true);
    if (!is_array($ratings)) $ratings = array();
    $ratings[$user_id] = $rate;
    update_post_meta($post_id, 'vq_video_ratings', $ratings);

    $sum = 0; $cnt = 0;
    foreach($ratings as $uid=>$r){ $sum += intval($r); $cnt++; }
    $avg = $cnt ? round($sum/$cnt, 2) : 0;
    update_post_meta($post_id, 'vq_video_rating_avg', $avg);
    update_post_meta($post_id, 'vq_video_rating_count', $cnt);

    wp_send_json_success(array('avg'=>$avg,'count'=>$cnt));
}


/** میانگین و تعداد رأی را برمی‌گرداند و متاها را به‌روز می‌کند */
add_action('wp_ajax_vq_get_rating','vq_get_rating');
add_action('wp_ajax_nopriv_vq_get_rating','vq_get_rating');
function vq_get_rating(){
    check_ajax_referer('vq_nonce','nonce');
    $post_id = intval($_POST['video_id']);
    $ratings = get_post_meta($post_id, 'vq_video_ratings', true);
    if (!is_array($ratings)) $ratings = array();
    $sum = 0; $cnt = 0;
    foreach($ratings as $r){ $sum += intval($r); $cnt++; }
    $avg = $cnt ? round($sum/$cnt, 2) : 0;
    update_post_meta($post_id, 'vq_video_rating_avg', $avg);
    update_post_meta($post_id, 'vq_video_rating_count', $cnt);
    wp_send_json_success(array('avg'=>$avg,'count'=>$cnt));
    wp_die();
}
