jQuery(function($){
  const $playlist = $('#vq-playlist');
  const $player   = $('#vq-main-player');
  const $title    = $('#vq-now-title');

  // === Playlist layout ===
  if($playlist.length && $player.length){
    $playlist.on('click','.vq-item', function(){
      const $it = $(this);
      const src = $it.data('src');
      const vid = $it.data('vid');
      const title = $it.data('title') || '';
      const wasPaused = $player[0].paused;
      $player.find('source').attr('src', src);
      $player.attr('data-video-id', vid)[0].load();
      if(!wasPaused){ $player[0].play().catch(()=>{}); }
      $title.text(title);
      $playlist.find('.vq-item.is-active').removeClass('is-active');
      $it.addClass('is-active');
      $('#vq-panels .vq-panel').hide();
      const $panel = $('#vq-panels .vq-panel[data-panel="'+vid+'"]').show();
      $panel.find('.vq-quiz-step, .vq-survey-step').hide();
    });

    $player.on('ended', function(){
      const vid = $(this).data('video-id');
      $.post(vqAjax.ajaxUrl,{action:'vq_mark_viewed',nonce:vqAjax.nonce,video_id:vid});
      $('#vq-panels .vq-panel[data-panel="'+vid+'"]').find('.vq-quiz-step').slideDown();
    });
  }

  function updateProgress(card, percent){ card.find('.vq-progress-bar').css('width', percent+'%'); }

  // === Accordion layout ===
  $(document).on('ended','.vq-player', function(){
    const $v = $(this), card = $v.closest('.vq-step-card');
    updateProgress(card,33);
    card.find('.vq-start-quiz').show();
    $.post(vqAjax.ajaxUrl,{action:'vq_mark_viewed',nonce:vqAjax.nonce,video_id:$v.data('video-id')});
  });

  $(document).on('click','.vq-start-quiz', function(){
    const card = $(this).closest('.vq-step-card');
    $('#'+$(this).data('target')).slideDown();
    $(this).hide();
    updateProgress(card,66);
  });

  // === Quiz submit (shared) ===
  $(document).on('click','.vq-quiz-submit', function(){
    const btn = $(this);
    const vid = btn.data('video');
    const answers = {};
    btn.closest('.vq-quiz-step').find('.vq-q').each(function(i,q){
      answers[i] = $(q).find('input:checked').val();
    });
    $.post(vqAjax.ajaxUrl,{action:'vq_submit_quiz',nonce:vqAjax.nonce,video_id:vid,answers:answers}, function(res){
      if(res.success){
        const fb = btn.siblings('.vq-quiz-feedback').show()
          .toggleClass('success',res.data.passed)
          .toggleClass('fail',!res.data.passed)
          .text(res.data.passed? 'قبول شدید ('+res.data.score+'/'+res.data.total+')' : 'رد شدید ('+res.data.score+'/'+res.data.total+')');
        if(res.data.passed){
          btn.hide();
          btn.closest('.vq-quiz-step').slideUp().siblings('.vq-survey-step').slideDown();
          const card = btn.closest('.vq-step-card');
          if(card.length){ updateProgress(card,100); }
        }
      }
    });
  });

  // === Rating ===
  $(document).on('click','.vq-video-rating .star', function(){
    const $s = $(this);
    const wrap = $s.closest('.vq-video-rate-wrap');
    const vid  = wrap.find('.vq-video-rating').data('video');
    const val  = $s.data('value');
    $.post(vqAjax.ajaxUrl,{action:'vq_rate_video',nonce:vqAjax.nonce,video_id:vid,rate:val}, function(res){
      if(res && res.success){
        $s.prevAll().addBack().addClass('active');
        $s.nextAll().removeClass('active');
        wrap.find('.vq-avg').text(res.data.avg);
        wrap.find('.vq-count').text(res.data.count);
      }else if(res && res.data && res.data.message){
        alert(res.data.message);
      }
    });
  });

  // === Accordion toggle ===
  $(document).on('click','.vq-step-header', function(){
    const card = $(this).closest('.vq-step-card');
    card.toggleClass('open');
    card.find('.vq-step-body').stop(true,true).slideToggle(200);
  });
  const first = $('.vq-step-card').first();
  if(first.length){ first.addClass('open').find('.vq-step-body').show(); }

  // === Next video unlock ===
  $(document).on('click','.vq-next-video', function(e){
    e.preventDefault();
    const card = $(this).closest('.vq-step-card');
    const next = card.next('.vq-step-card');
    if(!next.length) return;
    next.find('.vq-locked').hide();
    next.find('.vq-video-wrap').show();
    $('html,body').animate({scrollTop:next.offset().top-60},400);
  });

  // === Prevent seek & duration ===
  function vqFmt(sec){sec=Math.floor(sec||0);var h=Math.floor(sec/3600),m=Math.floor((sec%3600)/60),s=sec%60;return (h>0?(h+":"+(m<10?"0":"")):"")+m+":"+(s<10?"0":"")+s;}
  $('.vq-player.vq-no-seek').each(function(){
    const v=this,$v=$(this); let last=0,lock=false,id=$v.data('video-id');
    v.addEventListener('loadedmetadata',function(){ $('.vq-duration[data-video-id="'+id+'"]').text(vqFmt(v.duration)); });
    v.addEventListener('seeking',function(){ if(lock) return; lock=true; v.currentTime=last; lock=false; });
    v.addEventListener('timeupdate',function(){ last=v.currentTime; });
    $v.on('keydown',function(e){var k=e.key.toLowerCase(); if(['arrowleft','arrowright','home','end','j','l'].includes(k)){e.preventDefault();e.stopPropagation();}});
    $v.on('mousedown touchstart',function(){ setTimeout(function(){ v.currentTime=last; },0); });
  });

  // === Fetch rating on load ===
  $('.vq-video-rate-wrap .vq-video-rating').each(function(){
    const vid = $(this).data('video');
    const wrap = $(this).closest('.vq-video-rate-wrap');
    $.post(vqAjax.ajaxUrl,{action:'vq_get_rating',nonce:vqAjax.nonce,video_id:vid}, function(res){
      if(res && res.success){
        wrap.find('.vq-avg').text(res.data.avg);
        wrap.find('.vq-count').text(res.data.count);
      }
    });
  });
});

