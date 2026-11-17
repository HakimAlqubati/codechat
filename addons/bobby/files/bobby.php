<?php if(boomAllow($addons['addons_access'])){ ?>
<script data-cfasync="false">
var requestType = 'reg';
var bobby = '<?php echo $addons['bot_name']; ?>';
var bobbyLow = '<?php echo strtolower($addons['bot_name']); ?>';
var bobbyId = '<?php echo $addons['bot_id']; ?>';
var checkBobby = '';
var privBobby = '';

bobbyMain = function(){
	$.post('addons/bobby/system/bobby_main.php', { 
		search: checkBobby,
		name: bobby,
		type: requestType,
		}, function(response) {

	});
};
bobbyPrivate = function(){
	$.post('addons/bobby/system/bobby_private.php', { 
		search: privBobby,
		name: bobby,
		bid: currentPrivate,
		}, function(response) {

	});
};

$(document).ready(function(){

	$('#main_input').submit(function(event){
		checkBobby = $('#content').val();
		var checkBobbyLow = $('#content').val().toLowerCase();
		if( checkBobby.match(bobby) || checkBobbyLow.match(bobbyLow) ){
			setTimeout(bobbyMain, 1000);
		}
		else {
			checkBobby = '';
		}
	});
	$('#private_input').submit(function(event){
		privBobby = $('#message_content').val();
		if(privBobby == '' || /^\s+$/.test($('#message_content').val()) ){
			event.preventDefault();
		}
		else {
			if(currentPrivate == bobbyId){
				setTimeout(bobbyPrivate, 1000);
			}
			else {
				privBobby = '';
			}
		}
	});
});
</script>
<?php } ?>