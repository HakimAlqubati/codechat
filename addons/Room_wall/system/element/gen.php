<?php
$rankClass=((int)$boom['user_rank']===100)?' rw-rank-100':'';
$roomLabel=!empty($boom['room_name'])?'<span class="rw-room-source">'.rwEscHtml($boom['room_name']).'</span>':'';
?>
<div id="boom_gen<?php echo (int)$boom['id']; ?>" data="<?php echo (int)$boom['id']; ?>" class="gen_box blist post_element<?php echo $rankClass; ?>">
 <div class="post_title">
  <div class="post_avatar get_info" data="<?php echo (int)$boom['user_id']; ?>"><img loading="lazy" src="<?php echo myAvatar($boom['user_tumb']); ?>" alt=""></div>
  <div class="bcell_mid hpad5 maxflow post_info">
   <p class="username text_small <?php echo myColor($boom); ?>"><?php echo $boom['user_name']; ?></p>
   <p class="text_xsmall sub_date"><?php echo displayDate($boom['news_date']); ?><?php if(!empty($boom['edited_at'])){ ?> · عُدّل <?php echo displayDate($boom['edited_at']); ?><?php } ?></p>
   <?php if($roomLabel!==''){ ?><p class="text_xsmall rw-room-label">نُشر في <?php echo $roomLabel; ?></p><?php } ?>
  </div>
  <div onclick="openPostOptionssw(this);" class="post_edit bcell_mid_center"><i class="fa fa-ellipsis-h"></i>
   <div class="post_menu back_menu bshadow fmenu">
    <div onclick="viewSxLikes(<?php echo (int)$boom['id']; ?>);" class="submenu_item submenu"><?php echo subMenu('heart',$lang['view_likes'],$lang['like_text']); ?></div>
    <?php if(rwCanEditPost($boom)){ ?><div onclick="rwEditPost(<?php echo (int)$boom['id']; ?>, this);" class="submenu_item submenu"><?php echo subMenu('edit','تعديل المنشور','تعديل منشورك'); ?></div><?php } ?>
    <?php if(rwCanDeletePost($boom)){ ?><div onclick="rwDeletePost(<?php echo (int)$boom['id']; ?>);" class="submenu_item submenu"><?php echo subMenu('trash',$lang['delete'],$lang['delete_text']); ?></div><?php } ?>
    <?php if(canpinpost()){ ?><?php if((int)$boom['static']===0){ ?><div onclick="makeStaticNewsw(<?php echo (int)$boom['id']; ?>,1);" class="submenu_item submenu"><?php echo subMenu('thumb-tack',$lang['static_post'],$lang['static_text']); ?></div><?php }else{ ?><div onclick="makeStaticNewsw(<?php echo (int)$boom['id']; ?>,0);" class="submenu_item submenu"><?php echo subMenu('thumb-tack',$lang['unstatic_post'],$lang['unstatic_text']); ?></div><?php } ?><?php } ?>
   </div>
  </div>
  <?php if((int)$boom['static']===1){ ?><div class="post_edit bcell_mid_center" title="منشور مثبت"><i class="fa fa-thumb-tack" style="color:red"></i></div><?php } ?>
 </div>
 <div class="post_content">
  <?php $rwMaskedLink=false; $rwRenderedPostText=rwRenderPostText($boom,$boom['news_message'],$rwMaskedLink); if($rwMaskedLink){ ?><div class="rw-link-warning"><i class="fa fa-shield-halved"></i> رابط مخفي؛ صلاحيات الروابط في إدارة اليوميات والقصص لا تسمح لصاحب المحتوى بنشره.</div><?php } ?><?php echo $rwRenderedPostText; ?>
  <?php if(!empty($boom['news_file'])) echo boomPostFile($boom['news_file'],$boom['news_file_type']); ?>
  <?php echo rwRenderMedia('post',(int)$boom['id']); ?>
 </div>
 <div class="post_control btauto">
  <?php if(canLikeAdvNews()){ ?><div class="bcell_mid like_container swNewsLike<?php echo (int)$boom['id']; ?>"><?php echo getLikesgen($boom['id'],$boom['liked'],'gen'); ?></div><?php } ?>
  <div data="0" class="bcell_mid comment_count bcauto load_comment <?php if((int)$boom['reply_count']<1)echo 'hidden'; ?>" onclick="loadSxComment(this,<?php echo (int)$boom['id']; ?>);"><span id="nrepcounts<?php echo (int)$boom['id']; ?>"><?php echo (int)$boom['reply_count']; ?></span> <i class="fa fa-comment"></i></div>
 </div>
 <div class="ncmb<?php echo (int)$boom['id']; ?> ncmtboxwrap<?php echo (int)$boom['id']; ?> hidden">
  <div class="ncmtbox<?php echo (int)$boom['id']; ?>"></div><div class="nmorebox<?php echo (int)$boom['id']; ?>"></div>
  <?php if(canReplyAdvNews()){ ?><form class="sx_reply_form" data-id="<?php echo (int)$boom['id']; ?>"><input type="text" class="full_input" maxlength="1000" placeholder="اكتب تعليقاً..."><button type="submit" class="theme_btn"><i class="fa fa-paper-plane"></i></button></form><?php } ?>
 </div>
</div>
