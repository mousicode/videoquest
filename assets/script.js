jQuery(function($){
  const $pl = $("#vq-playlist");
  const $v  = $("#vq-main-player");
  const $t  = $("#vq-now-title");

  if($pl.length && $v.length){
    $pl.on("click", ".vq-item", function(){
      const $it   = $(this);
      const src   = $it.data("src");
      const vid   = $it.data("vid");
      const title = $it.data("title")||'';
      const sum   = $it.data("sum")||0;
      const count = $it.data("count")||0;

      // 1) سوییچ سورس و شناسه ویدئو
      if(src){
        const wasPaused = $v[0].paused;
        $v.find("source").attr("src", src);
        $v.attr("data-video-id", vid);
        $v[0].load();
        if (!wasPaused) { $v[0].play().catch(()=>{}); } else { $v[0].play().catch(()=>{}); }
      }

      // 2) عنوان
      if($t.length) $t.text(title);
      const $meta = $(".vq-video-meta");
      if($meta.length){
        $meta.find('.vq-sum').text(sum);
        $meta.find('.vq-count').text(count);
        $meta.find('.vq-duration').attr('data-video-id',vid).text('—');
      }

      // 3) اکتیو کردن آیتم
      $pl.find(".vq-item.is-active").removeClass("is-active");
      $it.addClass("is-active");

      // 4) نمایش پنل مرتبط (کوییز/نظرسنجی) و مخفی کردن بقیه
      $("#vq-panels .vq-panel").hide();
      $('#vq-panels .vq-panel[data-panel="'+vid+'"]').show();

      // 5) اسکرول اگر موبایل بود (اختیاری)
      // $('html,body').animate({scrollTop: $('.vq-player-wrap').offset().top - 60}, 300);
    });
  }
});

jQuery(function($){
  function updateProgress(card, percent){ card.find('.vq-progress-bar').css('width', percent+'%'); }
  $(".vq-player").on("ended",function(){
    var card=$(this).closest('.vq-step-card');
    updateProgress(card,33);
    $(this).siblings(".vq-start-quiz").show();
    $.post(vqAjax.ajaxUrl,{action:"vq_mark_viewed",nonce:vqAjax.nonce,video_id:$(this).data("video-id")},function(res){
      if(res&&res.success&&res.data&&res.data.awarded){
        alert('شما '+res.data.awarded+' امتیاز دریافت کردید! امتیاز کل: '+res.data.total);
      }
    });
  });
  $(".vq-start-quiz").on("click",function(){
    $("#"+$(this).data("target")).slideDown(); $(this).hide();
    updateProgress($(this).closest('.vq-step-card'),66);
  });
  $(".vq-quiz-submit").on("click",function(){
    var btn=$(this),vid=btn.data("video"),answers={};
    btn.closest(".vq-quiz-step").find(".vq-q").each(function(i,q){ answers[i]=$(q).find("input:checked").val(); });
    $.post(vqAjax.ajaxUrl,{action:"vq_submit_quiz",nonce:vqAjax.nonce,video_id:vid,answers:answers},function(res){
      if(res.success){
        btn.siblings(".vq-quiz-feedback").show().html(res.data.passed?"✅ ("+res.data.score+"/"+res.data.total+")":"❌ "+res.data.score+"/"+res.data.total);
        btn.hide(); btn.closest(".vq-quiz-step").siblings(".vq-survey-step").slideDown();
        updateProgress(btn.closest('.vq-step-card'),100);
      }
    });
  });
});
jQuery(function($){
  $("#vq-main-player").on("ended",function(){
    var vid=$(this).data("video-id");
    var panel=$("#vq-panels .vq-panel[data-panel='"+vid+"']");
    if(panel.length){ panel.find(".vq-start-quiz").show(); }
  });
});

/* === VQ Accordion toggle (minimal) === */
jQuery(function($){
  $(document).on('click','.vq-step-header',function(e){
    var card=$(this).closest('.vq-step-card');
    var body=card.find('.vq-step-body');
    body.stop(true,true).slideToggle(200);
    card.toggleClass('open');
  });
  // open first card by default
  var first=$('.vq-step-card').first();
  if(first.length){ first.addClass('open'); first.find('.vq-step-body').show(); }
});


/* === Prevent Seek & Show Duration (non-breaking) === */
jQuery(function($){
  function vqFmt(sec){sec=Math.floor(sec||0);var h=Math.floor(sec/3600),m=Math.floor((sec%3600)/60),s=sec%60;return (h>0?(h+":"+(m<10?"0":"")):"")+m+":"+(s<10?"0":"")+s;}
  $(".vq-player.vq-no-seek").each(function(){
    var v=this,$v=$(this),last=0,lock=false,id=$v.data("video-id");
    v.addEventListener("loadedmetadata",function(){ $('.vq-duration[data-video-id="'+id+'"]').text(vqFmt(v.duration)); });
    v.addEventListener("seeking",function(){ if(lock) return; lock=true; v.currentTime=last; lock=false; });
    v.addEventListener("timeupdate",function(){ last=v.currentTime; });
    $v.on("keydown",function(e){var k=e.key.toLowerCase(); if(['arrowleft','arrowright','home','end','j','l'].includes(k)){e.preventDefault();e.stopPropagation();}});
    $v.on("mousedown touchstart",function(){ setTimeout(function(){ v.currentTime=last; },0); });
  });
});




/* === Video rating total update === */
jQuery(function($){
  $(document).on('click','.vq-video-rating .star',function(){
    var $s=$(this), val=$s.data('value'), wrap=$s.closest('.vq-video-rate-wrap'), vid=wrap.find('.vq-video-rating').data('video');
    $s.siblings().removeClass('active'); $s.prevAll().addBack().addClass('active');
    $.post(vqAjax.ajaxUrl,{action:'vq_rate_video',nonce:vqAjax.nonce,video_id:vid,rate:val},function(res){
        if(res && res.success){
          wrap.find('.vq-sum').text(res.data.sum);
          wrap.find('.vq-count').text(res.data.count);
          $('#vq-playlist .vq-item[data-vid="'+vid+'"]').data('sum',res.data.sum).data('count',res.data.count).find('.vq-item-sum').text(res.data.sum);
          if($('#vq-main-player').data('video-id')==vid){ $('.vq-video-meta .vq-sum').text(res.data.sum); $('.vq-video-meta .vq-count').text(res.data.count); }
        }
      });
    });
  });


jQuery(function($){
  $(document).on('click','.vq-video-rating .star',function(){
    var wrap=$(this).closest('.vq-step-card');
    // آپدیت نشان میانگین در هدر کارت اگر وجود داشت
      setTimeout(function(){
        var sumText = wrap.find('.vq-video-rate-wrap .vq-sum').text();
        if(sumText){
          if(wrap.find('.vq-sum-badge').length){ wrap.find('.vq-sum-badge').text(sumText+'★'); }
          else { wrap.find('.vq-step-header').append('<span class="vq-sum-badge">'+sumText+'★</span>'); }
        }
      }, 200);
  });
});


/* Fetch rating on load for every video */
jQuery(function($){
  $(".vq-video-rate-wrap .vq-video-rating").each(function(){
    var vid=$(this).data('video');
    $.post(vqAjax.ajaxUrl,{action:'vq_get_rating',nonce:vqAjax.nonce,video_id:vid},function(res){
        if(res && res.success){
          var wrap=$('.vq-video-rate-wrap').has('[data-video="'+vid+'"]');
          wrap.find('.vq-sum').text(res.data.sum);
          wrap.find('.vq-count').text(res.data.count);
          var card=wrap.closest('.vq-video-item');
          if(card.find('.vq-sum-badge').length){ card.find('.vq-sum-badge').text(res.data.sum+'★'); }
        }
      });
    });
  });


/* Duration writer with fallback */
jQuery(function($){
  $(".vq-player").each(function(){
    var v=this,$v=$(this),id=$v.data("video-id")||$v.data("videoid");
    function writeDur(){ if(!isNaN(v.duration) && isFinite(v.duration) && v.duration>0){ $('.vq-duration[data-video-id="'+id+'"]').text(vqFmt(v.duration)); } }
    v.addEventListener("loadedmetadata", writeDur);
    v.addEventListener("durationchange", writeDur);
    setTimeout(function(){
      if (isNaN(v.duration) || !isFinite(v.duration) || v.duration===0){
        var wasPaused=v.paused; v.muted=true;
        v.play().then(function(){ setTimeout(function(){ v.pause(); if(wasPaused) v.muted=false; writeDur(); },120); }).catch(function(){});
      }
    },300);
  });
});


/* Unlock next video without refresh */
jQuery(function($){
  function unlockNext(card){
    var next=card.next('.vq-step-card');
    if(!next.length) return;
    next.find('.vq-locked').hide();
    next.find('.vq-video-wrap').show();
    $('html,body').animate({scrollTop: next.offset().top-60},400);
  }
  $(document).on('click','.vq-next-video',function(e){
    e.preventDefault();
    unlockNext($(this).closest('.vq-step-card'));
  });
});
