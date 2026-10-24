<!DOCTYPE html>
<?php
session_start();
include("include/connection.php");
include("include/ai_security_functions.php");

if(!isset($_SESSION['user_email'])){
	
	header("location: signin.php");

}
else{ ?>
<html>
<head>
<head>
    <link rel="icon" href="images/image1.png">
    </head>
<title>Welcome to ManoChat</title>
	<link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
	<link rel="stylesheet" type="text/css" href="css/home.css">
	<link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">

	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
</head>
<body>
	<div class="container main-section">
		<div class="row">
			<div class="col-md-3 col-sm-3 col-xs-12 left-sidebar">
				<div class="input-group searchbox">
					<div class="input-group-btn">
						<center><a href="include/find_friends.php"><button class="btn btn-default search-icon" name="search_user" type="submit">Add new user</button></a></center>
					</div>
				</div>
				<div class="left-chat">
					<ul>
						<?php include("include/get_users_data.php"); ?>
					</ul>
				</div>
			</div>
			<div class="col-md-9 col-sm-9 col-xs-12 right-sidebar">
				<div class="row">
					<!-- getting the user information who is logged in -->
					<?php 
						$user = $_SESSION['user_email'];
						$get_user = "select * from users where user_email='$user'"; 
						$run_user = mysqli_query($con,$get_user);
						$row=mysqli_fetch_array($run_user);
									
						$user_id = $row['user_id']; 
						$user_name = $row['user_name'];
						$safe_presence_user = mysqli_real_escape_string($con, $user_name);
						mysqli_query($con, "UPDATE users SET log_in='Online', last_seen=NOW() WHERE user_name='$safe_presence_user'");
					?>
						<!-- getting the user data on which user click -->
						<?php
							global $con;
							$username = "";
							$user_profile_image = "images/user.jpg";

							if(isset($_GET['user_name']) && $_GET['user_name'] != ''){
								$get_username = mysqli_real_escape_string($con, $_GET['user_name']);
								$get_user = "select * from users where user_name='$get_username' LIMIT 1";
							}else{
								$safe_current_user = mysqli_real_escape_string($con, $user_name);
								$get_user = "select * from users where user_name != '$safe_current_user' LIMIT 1";
							}

							$run_user = mysqli_query($con,$get_user);
							$row_user=mysqli_fetch_array($run_user);
							if($row_user){
								$username = $row_user['user_name'];
								$user_profile_image = $row_user['user_profile'];
							}

							$total_messages = "select * from users_chats where (sender_username='$user_name' AND receiver_username='$username') OR (receiver_username='$user_name' AND sender_username='$username')"; 
						$run_messages = mysqli_query($con,$total_messages); 
						$total = mysqli_num_rows($run_messages);
					?>
					<div class="col-md-12 right-header">
						<div class="right-header-img">
							<img src=<?php echo"$user_profile_image";?>>
						</div>
						<div class="right-header-detail">
							<form method="post">
								<p><?php echo"$username";?></p>
								<span id="messageCountLabel"><?php echo $total; ?> messages</span>
								<span id="peerPresence" class="peer-presence">Checking status...</span>
								<span id="typingIndicator" class="typing-indicator" style="display:none;"><?php echo htmlspecialchars($username); ?> is typing...</span>&nbsp;
								<a href="video_call.php?user_name=<?php echo urlencode($username); ?>" class="btn btn-success">Video Call</a>
								<button type="button" id="themeToggle" class="btn btn-default theme-toggle" title="Toggle dark mode">
									<i class="fa fa-moon-o"></i>
								</button>
								<button name="logout" class="btn btn-danger">Logout</button>
							</form>
							<?php
								if(isset($_POST['logout'])){
									$update_msg = mysqli_query($con, "UPDATE users SET log_in='Offline', last_seen=NOW() WHERE user_name='$user_name'");
									header("Location:logout.php");
									exit();
								}
							?>
						</div>
					</div>
				</div>
				<div class="row">
					<div id="scrolling_to_bottom" class="col-md-12 right-header-contentChat" data-message-count="<?php echo isset($total) ? (int)$total : 0; ?>">
						<?php

						$update_msg = mysqli_query($con, "UPDATE users_chats SET msg_status='read' WHERE sender_username='$username' AND receiver_username='$user_name'");

						
						$mark_read_stmt = mysqli_prepare(
							$con,
							"UPDATE users_chats SET msg_status='read'
							 WHERE sender_username=? AND receiver_username=? AND msg_status!='read'"
						);
						mysqli_stmt_bind_param($mark_read_stmt, "ss", $username, $user_name);
						mysqli_stmt_execute($mark_read_stmt);
						mysqli_stmt_close($mark_read_stmt);

						$sel_msg = "select * from users_chats where (sender_username='$user_name' AND receiver_username='$username') OR (receiver_username='$user_name' AND sender_username='$username') ORDER by 1 ASC"; 
						$run_msg = mysqli_query($con,$sel_msg);		
						$chat_messages = [];
						
						while($row=mysqli_fetch_array($run_msg)){
			
						$sender_username = $row['sender_username'];
						$receiver_username = $row['receiver_username'];
						$msg_content = $row['msg_content'];
						$msg_status = $row['msg_status'];
						$msg_id = $row['msg_id'];
						$msg_date = $row['msg_date'];
						$chat_messages[] = ['sender_username' => $sender_username, 'msg_content' => $msg_content];

						?>
						<ul>
						<?php

						if($user_name == $sender_username AND $username == $receiver_username){

							echo"
								<li>
									<div class='rightside-right-chat'>
										<span> $user_name <small>$msg_date</small> </span><br><br>
										<p>$msg_content</p>
										<small class='message-receipt " . ($msg_status == "read" ? "receipt-read" : "receipt-sent") . "'>" . ($msg_status == "read" ? "&#10003;&#10003; Read" : "&#10003; Sent") . "</small>
									</div>
								</li>
							";
						}

						else if($user_name == $receiver_username AND $username == $sender_username){
							echo"
								<li>
									<div class='rightside-left-chat'>
										<span> $username <small>$msg_date</small> </span><br><br>
										<p>$msg_content</p>
									</div>
								</li>

							";
						}


						?>
						</ul>
						<?php

						}

						?>
					</div>
				</div>

				<?php
					$recent_messages = isset($chat_messages) ? array_slice($chat_messages, -8) : [];
					$smart_replies = manochat_generate_smart_replies($recent_messages);
					$chat_summary = manochat_generate_summary($recent_messages);
					$chat_recommendation = manochat_generate_recommendation($recent_messages);
					$latest_message = !empty($recent_messages) ? end($recent_messages) : null;
					$latest_threats = $latest_message ? manochat_detect_threat($latest_message['msg_content']) : [];
				?>
				<div class="row">
					<div class="col-md-12 right-chat-textbox">
						<form method="post">
						<input autocomplete="off" id="msg_content" type="text" name="msg_content" placeholder="Write your message...">
						<button class="btn" name="submit"><i class="fa fa-telegram" aria-hidden="true"></i></button>
						<button type="button" id="aiAssistantToggle" class="ai-assistant-toggle" title="ManoChat AI Assistant">
							<i class="fa fa-magic"></i>
							<?php if(!empty($latest_threats)){ ?><span class="ai-alert-dot"></span><?php } ?>
						</button>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
<div id="aiAssistantPanel" class="ai-assistant-panel">
			<div class="ai-assistant-header">
				<strong><i class="fa fa-magic"></i> ManoChat AI Assistant</strong>
				<button type="button" id="aiAssistantClose">&times;</button>
			</div>

			<div id="aiLoadingBox" class="ai-loading-box">
				<i class="fa fa-spinner fa-spin"></i> Analysing conversation...
			</div>

			<div class="ai-assistant-body" id="aiAssistantBody">
				<div class="ai-status-card <?php echo !empty($latest_threats) ? 'ai-status-warning' : 'ai-status-safe'; ?>">
					<strong>Security Status:</strong>
					<?php if(!empty($latest_threats)){ ?>
						<span>Warning detected</span>
					<?php } else { ?>
						<span>Safe</span>
					<?php } ?>
				</div>

				<div class="ai-card">
					<h5><i class="fa fa-lightbulb-o"></i> Smart Reply Suggestions</h5>
					<?php foreach($smart_replies as $reply){ ?>
						<button type="button" class="btn btn-xs btn-info smart-reply" data-reply="<?php echo htmlspecialchars($reply, ENT_QUOTES); ?>"><?php echo htmlspecialchars($reply); ?></button>
					<?php } ?>
				</div>

				<div class="ai-card">
					<h5><i class="fa fa-file-text-o"></i> Conversation Summary</h5>
					<p><?php echo htmlspecialchars($chat_summary); ?></p>
				</div>

				<?php if(!empty($chat_recommendation)){ ?>
				<div class="ai-card <?php echo !empty($latest_threats) ? 'security-warning-card' : ''; ?>">
					<h5><i class="fa fa-comments-o"></i> Context-Aware Recommendation</h5>
					<p><?php echo htmlspecialchars($chat_recommendation); ?></p>
				</div>
				<?php } ?>

				<div class="ai-card security-card">
					<h5><i class="fa fa-shield"></i> Security Monitor</h5>
					<?php if(!empty($latest_threats)){ ?>
						<p class="security-alert"><strong>Potential suspicious message detected.</strong></p>
						<ul>
							<?php foreach($latest_threats as $threat){ ?>
								<li><?php echo htmlspecialchars($threat); ?></li>
							<?php } ?>
						</ul>
						<p class="ai-small-note">Recommendation: Do not share passwords, personal details or payment information.</p>
					<?php } else { ?>
						<p>No suspicious activity detected in the latest message.</p>
					<?php } ?>
				</div>

				<div class="ai-card ai-stats-card">
					<h5><i class="fa fa-bar-chart"></i> AI Statistics</h5>
					<p>Messages analysed: <?php echo count($recent_messages); ?></p>
					<p>Smart replies generated: <?php echo count($smart_replies); ?></p>
					<p>Latest threats detected: <?php echo count($latest_threats); ?></p>
				</div>
			</div>
		</div>
	<?php
		if(isset($_POST['submit'])){
			$msg = htmlentities($_POST['msg_content']);
				
			if($msg == ""){
				echo"

				<div class='alert alert-danger'>
				  <strong><center>Please write something to send. Message was unable to send!</center></strong>
				</div>

				";
			}else if(strlen($msg) > 100){
				echo"

				<div class='alert alert-danger'>
				  <strong><center>Message is Too long! Use only 100 characters</center></strong>
				</div>

				";
			}
			else{
			$security_threats = manochat_detect_threat($msg);
			manochat_log_security_event($con, $user_name, $username, $msg, $security_threats);

			if(!empty($security_threats)){
				echo "
				<div class='alert alert-warning'>
				  <strong><center>Security Warning:</strong> This message was sent, but it was flagged for review: " . implode(', ', $security_threats) . "</center>
				</div>
				";
			}

			$stmt = mysqli_prepare($con, "insert into users_chats(sender_username,receiver_username,msg_content,msg_status,msg_date) values (?, ?, ?, 'unread', NOW())");
			mysqli_stmt_bind_param($stmt, "sss", $user_name, $username, $msg);
			$run_insert = mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);

			echo "<script>window.open('home.php?user_name=" . urlencode($username) . "','_self')</script>";

	

			}
		}
	?>

	<script>
		$('#scrolling_to_bottom').animate({
		scrollTop: $('#scrolling_to_bottom').get(0).scrollHeight}, 1000);
	</script>
	<script type="text/javascript">
		$(document).on('click', '.smart-reply', function(){
			$('#msg_content').val($(this).data('reply'));
			$('#msg_content').focus();
		});
	</script>
	<script type="text/javascript">
		$(document).ready(function(){
	    	var height = $(window).height();
	    	$('.left-chat').css('height', (height - 92) + 'px');
	    	$('.right-header-contentChat').css('height', (height - 163) + 'px');
	    });
	</script>
	<script type="text/javascript">
		$(document).on('click', '#aiAssistantToggle', function(){
			var panel = $('#aiAssistantPanel');
			panel.toggleClass('open');

			if(panel.hasClass('open')){
				$('#aiAssistantBody').hide();
				$('#aiLoadingBox').show();

				setTimeout(function(){
					$('#aiLoadingBox').hide();
					$('#aiAssistantBody').fadeIn(200);
				}, 700);
			}
		});

		$(document).on('click', '#aiAssistantClose', function(){
			$('#aiAssistantPanel').removeClass('open');
		});
	</script>


<script id="smart-reply-click-fix" type="text/javascript">
    $(document).on('click', '.smart-reply', function(){
        var reply = $(this).attr('data-reply') || $(this).text();
        $('#msg_content').val(reply);
        $('#msg_content').focus();
    });
</script>


<script id="auto-load-messages-fix" type="text/javascript">
    var lastMessageCount = parseInt($('#scrolling_to_bottom').attr('data-message-count') || '0', 10);
    var lastMessageSignature = '';
    var isChatLoading = false;

    function scrollChatToBottomFast(){
        var chatBox = $('#scrolling_to_bottom');
        if(chatBox.length){
            chatBox.scrollTop(chatBox.get(0).scrollHeight);
        }
    }

    function refreshManoChatMessages(){
        if(isChatLoading) return;
        isChatLoading = true;

        var params = new URLSearchParams(window.location.search);
        var peerName = params.get('user_name') || '';

        $.ajax({
            url: 'include/fetch_chat_data.php',
            method: 'GET',
            dataType: 'json',
            data: { user_name: peerName },
            cache: false,
            success: function(response){
                if(response.error){
                    return;
                }

                var newCount = parseInt(response.message_count || 0, 10);

                var newSignature = response.message_signature || '';
                if(newCount !== lastMessageCount || newSignature !== lastMessageSignature){
                    $('#scrolling_to_bottom').html(response.chat_html);
                    $('#scrolling_to_bottom').attr('data-message-count', newCount);
                    $('#messageCountLabel').text(newCount + ' messages');
                    lastMessageCount = newCount;
                    lastMessageSignature = newSignature;
                    scrollChatToBottomFast();
                }

                if(response.ai_html){
                    $('#aiAssistantBody').html(response.ai_html);
                }

                if(parseInt(response.latest_threat_count || 0, 10) > 0){
                    if($('#aiAssistantToggle .ai-alert-dot').length === 0){
                        $('#aiAssistantToggle').append('<span class="ai-alert-dot"></span>');
                    }
                } else {
                    $('#aiAssistantToggle .ai-alert-dot').remove();
                }
            },
            complete: function(){
                isChatLoading = false;
            }
        });
    }

    
    setInterval(refreshManoChatMessages, 2000);

   
    $(document).ready(function(){
        setTimeout(refreshManoChatMessages, 800);
    });
</script>


<script id="manochat-realtime-ui" type="text/javascript">
(function(){
    var params = new URLSearchParams(window.location.search);
    var peerName = params.get('user_name') || '';
    var typingStopTimer = null;
    var lastTypingState = false;

    function updatePresence(){
        if(!peerName) return;

        $.getJSON('include/presence.php', {user_name: peerName})
            .done(function(response){
                if(response.error) return;
                $('#peerPresence')
                    .text(response.status)
                    .toggleClass('presence-online', !!response.online)
                    .toggleClass('presence-offline', !response.online);
            });
    }

    function sendTypingState(isTyping){
        if(!peerName || lastTypingState === isTyping) return;
        lastTypingState = isTyping;

        $.post('include/typing_status.php', {
            receiver: peerName,
            is_typing: isTyping ? 1 : 0
        });
    }

    $('#msg_content').on('input', function(){
        var hasText = $.trim($(this).val()).length > 0;
        sendTypingState(hasText);

        clearTimeout(typingStopTimer);
        typingStopTimer = setTimeout(function(){
            sendTypingState(false);
        }, 1500);
    });

    $('#msg_content').on('blur', function(){
        clearTimeout(typingStopTimer);
        sendTypingState(false);
    });

    function updateTypingIndicator(){
        if(!peerName) return;

        $.getJSON('include/typing_status.php', {user_name: peerName})
            .done(function(response){
                if(response.typing){
                    $('#typingIndicator').stop(true, true).fadeIn(120);
                } else {
                    $('#typingIndicator').stop(true, true).fadeOut(120);
                }
            });
    }

    function applyTheme(theme){
        var dark = theme === 'dark';
        $('body').toggleClass('dark-mode', dark);
        $('#themeToggle i')
            .toggleClass('fa-moon-o', !dark)
            .toggleClass('fa-sun-o', dark);
    }

    var savedTheme = localStorage.getItem('manochat_theme') || 'light';
    applyTheme(savedTheme);

    $('#themeToggle').on('click', function(){
        var nextTheme = $('body').hasClass('dark-mode') ? 'light' : 'dark';
        localStorage.setItem('manochat_theme', nextTheme);
        applyTheme(nextTheme);
    });

    updatePresence();
    updateTypingIndicator();

    setInterval(updatePresence, 8000);
    setInterval(updateTypingIndicator, 1000);

    window.addEventListener('beforeunload', function(){
        if(!peerName) return;
        navigator.sendBeacon(
            'include/typing_status.php',
            new URLSearchParams({
                receiver: peerName,
                is_typing: '0'
            })
        );
    });
})();
</script>


<div id="incomingCallOverlay" class="incoming-call-overlay" aria-hidden="true">
    <div class="incoming-call-card">
        <div class="incoming-call-avatar">
            <i class="fa fa-video-camera"></i>
        </div>
        <p class="incoming-call-label">Incoming video call</p>
        <h3 id="incomingCallerName">ManoChat user</h3>
        <p class="incoming-call-subtitle">is calling you...</p>

        <div class="incoming-call-actions">
            <button type="button" id="declineIncomingCall" class="incoming-call-button decline-call">
                <i class="fa fa-phone"></i> Decline
            </button>
            <button type="button" id="acceptIncomingCall" class="incoming-call-button accept-call">
                <i class="fa fa-video-camera"></i> Accept
            </button>
        </div>
    </div>
</div>


<script id="incoming-video-call-notification" type="text/javascript">
(function(){
    var activeIncomingCall = null;
    var incomingCallBusy = false;

    function hiddenCallId(){
        return sessionStorage.getItem('manochat_hidden_call_id') || '';
    }

    function hideIncomingCall(rememberCall){
        $('#incomingCallOverlay').removeClass('visible').attr('aria-hidden', 'true');

        if(rememberCall && activeIncomingCall && activeIncomingCall.id){
            sessionStorage.setItem('manochat_hidden_call_id', String(activeIncomingCall.id));
        }

        activeIncomingCall = null;
    }

    function showIncomingCall(call){
        if(!call || !call.id || !call.sender_username) return;
        if(String(call.id) === hiddenCallId()) return;

        activeIncomingCall = call;
        $('#incomingCallerName').text(call.sender_username);
        $('#incomingCallOverlay').addClass('visible').attr('aria-hidden', 'false');

        if('vibrate' in navigator){
            navigator.vibrate([200, 100, 200, 100, 400]);
        }
    }

    function checkIncomingVideoCall(){
        if(incomingCallBusy || window.location.pathname.indexOf('video_call.php') !== -1){
            return;
        }

        incomingCallBusy = true;

        $.ajax({
            url: 'video_signal.php',
            method: 'GET',
            dataType: 'json',
            cache: false,
            data: {
                action: 'incoming',
                _: Date.now()
            },
            success: function(response){
                if(response && response.incoming && response.call){
                    showIncomingCall(response.call);
                }
            },
            complete: function(){
                incomingCallBusy = false;
            }
        });
    }

    $(document).on('click', '#acceptIncomingCall', function(){
        if(!activeIncomingCall) return;

        var caller = activeIncomingCall.sender_username;
        var callId = activeIncomingCall.id;

        sessionStorage.setItem('manochat_hidden_call_id', String(callId));

        window.location.href =
            'video_call.php?user_name=' +
            encodeURIComponent(caller) +
            '&auto_answer=1&offer_id=' +
            encodeURIComponent(callId);
    });

    $(document).on('click', '#declineIncomingCall', function(){
        if(!activeIncomingCall) return;

        var caller = activeIncomingCall.sender_username;
        var callId = activeIncomingCall.id;

        $.post('video_signal.php', {
            action: 'send',
            peer: caller,
            type: 'reject',
            data: JSON.stringify({
                rejected: true,
                offer_id: callId
            })
        });

        hideIncomingCall(true);
    });

    $(document).ready(function(){
        setTimeout(checkIncomingVideoCall, 700);
        setInterval(checkIncomingVideoCall, 1200);
    });
})();
</script>

</body>
</html>
<?php } ?>