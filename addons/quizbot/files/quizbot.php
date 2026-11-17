<?php
include(addonsLang('quizbot'));
require_once BOOM_PATH . '/system/addon_menu_labels.php';
$quizMenuTitle = addonMenuLabelGet('quizbot', 'مسابقة الأعضاء');
?>
<script data-cfasync="false">

quizLeaderboard = function(){
	$.post('addons/quizbot/system/leaderboard.php', { 
		token: utk,
		}, function(response) {
		showModal(response, 420);
	});
}

$(document).ready(function(){
	appLeftMenu('question-circle', <?php echo json_encode($quizMenuTitle, JSON_UNESCAPED_UNICODE); ?>, 'quizLeaderboard();');
	boomAddCss('addons/quizbot/files/quizbot.css');
});
</script>