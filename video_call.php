<!DOCTYPE html>
<?php
session_start();
include("include/connection.php");

if(!isset($_SESSION['user_email'])){
    header("location: signin.php");
    exit();
}

if(!isset($_GET['user_name'])){
    header("location: home.php");
    exit();
}

$user = $_SESSION['user_email'];
$get_user = "SELECT * FROM users WHERE user_email='" . mysqli_real_escape_string($con, $user) . "' LIMIT 1";
$run_user = mysqli_query($con, $get_user);
$row = mysqli_fetch_array($run_user);
$current_username = $row['user_name'];

$peer_username = $_GET['user_name'];

$auto_answer = isset($_GET['auto_answer']) && $_GET['auto_answer'] === '1';

$peer_safe = mysqli_real_escape_string($con, $peer_username);
$get_peer = "SELECT * FROM users WHERE user_name='$peer_safe' LIMIT 1";
$run_peer = mysqli_query($con, $get_peer);
$peer_row = mysqli_fetch_array($run_peer);

if(!$peer_row){
    header("location: home.php?user_name=" . urlencode($current_username));
    exit();
}
?>
<html>
<head>
    <title>Video Call - ManoChat</title>
    <link rel="icon" href="images/image1.png">
    <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="css/home.css">
    <link rel="stylesheet" type="text/css" href="css/video_call.css">
</head>
<body>
    <div class="container video-page">
        <div class="video-header">
            <h3>Video call with <?php echo htmlspecialchars($peer_username); ?></h3>
            <a class="btn btn-default" href="home.php?user_name=<?php echo urlencode($peer_username); ?>">Back to chat</a>
        </div>

        <div class="video-grid">
            <div class="video-box">
                <video id="localVideo" autoplay muted playsinline></video>
                <p>You</p>
            </div>
            <div class="video-box">
                <video id="remoteVideo" autoplay playsinline></video>
                <p><?php echo htmlspecialchars($peer_username); ?></p>
            </div>
        </div>

        <div class="video-controls">
            <button id="startCall" class="btn btn-success">Start Call</button>
            <button id="answerCall" class="btn btn-primary">Answer Call</button>
            <button id="endCall" class="btn btn-danger">End Call</button>
            <p id="callStatus">Allow camera and microphone access to start.</p>
        </div>
    </div>

<script>
const peerUsername = <?php echo json_encode($peer_username); ?>;
const autoAnswerIncoming = <?php echo $auto_answer ? 'true' : 'false'; ?>;

let localStream = null;
let peerConnection = null;
let lastSignalId = 0;
let pollTimer = null;
let pendingIceCandidates = [];
let callStarted = false;
let callEnded = false;

const localVideo = document.getElementById('localVideo');
const remoteVideo = document.getElementById('remoteVideo');
const callStatus = document.getElementById('callStatus');
const startButton = document.getElementById('startCall');
const answerButton = document.getElementById('answerCall');
const endButton = document.getElementById('endCall');

const config = {
    iceServers: [
        { urls: 'stun:stun.l.google.com:19302' },
        { urls: 'stun:stun1.l.google.com:19302' }
    ]
};

function setStatus(message) {
    callStatus.innerText = message;
}

async function setupMedia() {
    if (localStream) return localStream;

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        throw new Error('Camera access is not supported in this browser.');
    }

    localStream = await navigator.mediaDevices.getUserMedia({
        video: true,
        audio: true
    });

    localVideo.srcObject = localStream;
    return localStream;
}

async function createPeerConnection() {
    if (peerConnection) return peerConnection;

    await setupMedia();

    peerConnection = new RTCPeerConnection(config);

    localStream.getTracks().forEach(track => {
        peerConnection.addTrack(track, localStream);
    });

    peerConnection.ontrack = event => {
        if (event.streams && event.streams[0]) {
            remoteVideo.srcObject = event.streams[0];
            remoteVideo.play().catch(() => {});
        }
    };

    peerConnection.onicecandidate = event => {
        if (event.candidate && !callEnded) {
            sendSignal('candidate', JSON.stringify(event.candidate))
                .catch(error => console.error('Candidate send error:', error));
        }
    };

    peerConnection.onconnectionstatechange = () => {
        const state = peerConnection.connectionState;

        if (state === 'connected') {
            setStatus('Call connected.');
            startButton.disabled = true;
            answerButton.disabled = true;
        } else if (state === 'connecting') {
            setStatus('Connecting...');
        } else if (state === 'disconnected') {
            setStatus('Connection interrupted. Trying to reconnect...');
        } else if (state === 'failed') {
            setStatus('Connection failed. End the call and try again.');
        } else if (state === 'closed') {
            setStatus('Call ended.');
        }
    };

    peerConnection.oniceconnectionstatechange = () => {
        if (peerConnection.iceConnectionState === 'failed') {
            setStatus('Network connection failed. Please try again.');
        }
    };

    return peerConnection;
}

async function sendSignal(type, data) {
    const formData = new FormData();
    formData.append('action', 'send');
    formData.append('peer', peerUsername);
    formData.append('type', type);
    formData.append('data', data);

    const response = await fetch('video_signal.php', {
        method: 'POST',
        body: formData,
        cache: 'no-store'
    });

    const result = await response.json();

    if (!response.ok || result.error) {
        throw new Error(result.error || 'Unable to send video signal.');
    }

    return result;
}

async function cleanupSignals() {
    const formData = new FormData();
    formData.append('action', 'cleanup');
    formData.append('peer', peerUsername);

    const response = await fetch('video_signal.php', {
        method: 'POST',
        body: formData,
        cache: 'no-store'
    });

    const result = await response.json();

    if (!response.ok || result.error) {
        throw new Error(result.error || 'Unable to prepare the call.');
    }

    lastSignalId = 0;
}

async function addOrQueueCandidate(candidateData) {
    const candidate = new RTCIceCandidate(JSON.parse(candidateData));

    if (peerConnection && peerConnection.remoteDescription) {
        await peerConnection.addIceCandidate(candidate);
    } else {
        pendingIceCandidates.push(candidate);
    }
}

async function flushPendingCandidates() {
    if (!peerConnection || !peerConnection.remoteDescription) return;

    while (pendingIceCandidates.length > 0) {
        const candidate = pendingIceCandidates.shift();

        try {
            await peerConnection.addIceCandidate(candidate);
        } catch (error) {
            console.error('Queued ICE candidate error:', error);
        }
    }
}

async function handleSignal(type, data) {
    if (callEnded) return;

    if (type === 'reject') {
        endCall(false);
        setStatus('The other user declined the call.');
        startButton.disabled = false;
        answerButton.disabled = false;
        return;
    }

    if (type === 'end') {
        endCall(false);
        setStatus('The other user ended the call.');
        return;
    }

    const pc = await createPeerConnection();

    if (type === 'offer') {
        if (pc.signalingState !== 'stable') {
            await pc.setLocalDescription({ type: 'rollback' }).catch(() => {});
        }

        await pc.setRemoteDescription(JSON.parse(data));
        await flushPendingCandidates();

        const answer = await pc.createAnswer();
        await pc.setLocalDescription(answer);
        await sendSignal('answer', JSON.stringify(pc.localDescription));

        callStarted = true;
        startButton.disabled = true;
        answerButton.disabled = true;
        setStatus('Incoming call accepted. Connecting...');
        return;
    }

    if (type === 'answer') {
        if (!pc.remoteDescription) {
            await pc.setRemoteDescription(JSON.parse(data));
            await flushPendingCandidates();
        }

        setStatus('Connecting...');
        return;
    }

    if (type === 'candidate') {
        await addOrQueueCandidate(data);
    }
}

async function fetchSignals() {
    if (callEnded) return;

    try {
        const response = await fetch(
            'video_signal.php?action=fetch&peer=' +
            encodeURIComponent(peerUsername) +
            '&last_id=' + lastSignalId +
            '&_=' + Date.now(),
            { cache: 'no-store' }
        );

        const data = await response.json();

        if (!response.ok || data.error) {
            throw new Error(data.error || 'Unable to receive call data.');
        }

        if (!Array.isArray(data.signals)) return;

        for (const signal of data.signals) {
            lastSignalId = Math.max(lastSignalId, parseInt(signal.id, 10));
            await handleSignal(signal.signal_type, signal.signal_data);
        }
    } catch (error) {
        console.error('Signal polling error:', error);
    }
}

function startPolling() {
    if (pollTimer) return;

    fetchSignals();
    pollTimer = setInterval(fetchSignals, 700);
}

async function startOutgoingCall() {
    try {
        callEnded = false;
        pendingIceCandidates = [];
        startButton.disabled = true;
        answerButton.disabled = true;

        setStatus('Preparing camera and microphone...');
        await setupMedia();

        await cleanupSignals();

        const pc = await createPeerConnection();
        const offer = await pc.createOffer({
            offerToReceiveAudio: true,
            offerToReceiveVideo: true
        });

        await pc.setLocalDescription(offer);
        await sendSignal('offer', JSON.stringify(pc.localDescription));

        callStarted = true;
        startPolling();
        setStatus('Calling ' + peerUsername + '... The other user should open the video-call page.');
    } catch (error) {
        console.error(error);
        setStatus('Unable to start call: ' + error.message);
        startButton.disabled = false;
        answerButton.disabled = false;
    }
}

async function prepareToAnswer() {
    try {
        callEnded = false;
        setStatus('Preparing camera and microphone...');
        await setupMedia();
        await createPeerConnection();
        startPolling();
        await fetchSignals();

        answerButton.disabled = true;
        setStatus('Waiting for the incoming call...');
    } catch (error) {
        console.error(error);
        setStatus('Unable to answer call: ' + error.message);
        answerButton.disabled = false;
    }
}

async function endCall(notifyPeer = true) {
    if (callEnded) return;
    callEnded = true;

    if (notifyPeer) {
        await sendSignal('end', JSON.stringify({ ended: true })).catch(() => {});
    }

    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }

    if (peerConnection) {
        peerConnection.ontrack = null;
        peerConnection.onicecandidate = null;
        peerConnection.close();
        peerConnection = null;
    }

    if (localStream) {
        localStream.getTracks().forEach(track => track.stop());
        localStream = null;
    }

    localVideo.srcObject = null;
    remoteVideo.srcObject = null;
    pendingIceCandidates = [];
    callStarted = false;

    startButton.disabled = false;
    answerButton.disabled = false;

    if (notifyPeer) {
        setStatus('Call ended.');
    }
}

startButton.addEventListener('click', startOutgoingCall);
answerButton.addEventListener('click', prepareToAnswer);
endButton.addEventListener('click', () => endCall(true));

window.addEventListener('beforeunload', () => {
    if (callStarted && !callEnded) {
        const formData = new FormData();
        formData.append('action', 'send');
        formData.append('peer', peerUsername);
        formData.append('type', 'end');
        formData.append('data', JSON.stringify({ ended: true }));
        navigator.sendBeacon('video_signal.php', formData);
    }
});

startPolling();

if (autoAnswerIncoming) {
    setStatus('Incoming call accepted. Preparing camera and microphone...');
    setTimeout(function(){
        prepareToAnswer();
    }, 250);
} else {
    setStatus('Allow camera and microphone access. Start a call or wait for an incoming call notification.');
}
</script>
</body>
</html>
