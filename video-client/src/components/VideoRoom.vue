<script setup>
import { onMounted, onUnmounted, ref, defineProps, defineEmits } from 'vue';

const props = defineProps({
  roomId: {
    type: String,
    required: true
  }
});

const emit = defineEmits(['leave-room']);

const socket = ref(null);
const localVideo = ref(null)
const remoteVideo = ref(null)
const audioCanvas = ref(null)

const isCameraOn = ref(true)
const isMicOn = ref(true)

// Audio Visualizer Variables
let audioContext = null;
let analyser = null;
let dataArray = null;
let animationId = null;
let audioSource = null;

const toggleCamera = () => {
  const stream = localVideo.value.srcObject
  if (stream) {
    const videoTrack = stream.getVideoTracks()[0]
    if (videoTrack) {
      videoTrack.enabled = !videoTrack.enabled
      isCameraOn.value = videoTrack.enabled
    }
  }
}

const toggleMic = () => {
  const stream = localVideo.value.srcObject
  if (stream) {
    const audioTrack = stream.getAudioTracks()[0]
    if (audioTrack) {
      audioTrack.enabled = !audioTrack.enabled
      isMicOn.value = audioTrack.enabled
    }
  }
}

const leaveRoom = () => {
  if (socket.value) {
    socket.value.close();
  }

  if (peerConnection) {
    peerConnection.close();
    peerConnection = null;
  }

  if (localVideo.value && localVideo.value.srcObject) {
    localVideo.value.srcObject.getTracks().forEach(track => track.stop());
  }

  window.history.pushState({}, '', window.location.pathname);
  emit('leave-room');
}

const setupAudioVisualizer = (stream) => {
  if (!audioCanvas.value) return;

  audioContext = new (window.AudioContext || window.webkitAudioContext)();
  analyser = audioContext.createAnalyser();
  analyser.fftSize = 32;
  
  const bufferLength = analyser.frequencyBinCount; // 16 with fftSize 32
  dataArray = new Uint8Array(bufferLength);

  audioSource = audioContext.createMediaStreamSource(stream);
  audioSource.connect(analyser);

  drawVisualizer();
};

const drawVisualizer = () => {
  if (!audioCanvas.value || !analyser || !dataArray) return;
  
  const canvas = audioCanvas.value;
  const ctx = canvas.getContext('2d');
  const width = canvas.width;
  const height = canvas.height;

  animationId = requestAnimationFrame(drawVisualizer);

  ctx.clearRect(0, 0, width, height);

  if (!isMicOn.value) {
    ctx.fillStyle = 'rgba(255, 85, 0, 0.3)';
    ctx.fillRect(0, height / 2 - 1, width, 2);
    return;
  }

  analyser.getByteFrequencyData(dataArray);

  const numberOfBars = 5;
  const barGap = 2;
  const totalBarWidth = (width - (numberOfBars - 1) * barGap);
  const singleBarWidth = totalBarWidth / numberOfBars;
  
  let x = 0;

  for (let i = 0; i < numberOfBars; i++) {
    const dataIndex = Math.floor(i * (dataArray.length / numberOfBars));
    let barValue = dataArray[dataIndex] || 0;
    const barHeight = (barValue / 255) * height * 0.8;

    ctx.fillStyle = `#FF5500`; 
    ctx.fillRect(x, height - barHeight, singleBarWidth, barHeight);

    x += singleBarWidth + barGap;
  }
};

let peerConnection = null;

const configuration = {
    iceServers: [
        { urls: 'stun:stun.l.google.com:19302' }
    ]
}

const createPeerConnection = () => {
  peerConnection = new RTCPeerConnection(configuration);

  peerConnection.onicecandidate = (event) => {
      if (event.candidate) {
          socket.value.send(JSON.stringify({ type: 'ice-candidate', candidate: event.candidate }));
      }
  };

  peerConnection.ontrack = (event) => {
      if (remoteVideo.value) {
          remoteVideo.value.srcObject = event.streams[0];
      }
  };

  const stream = localVideo.value.srcObject
  stream.getTracks().forEach(track => {
      peerConnection.addTrack(track, stream)
  })
}

const connectWebSocket = (currentRoomId) => {
    socket.value = new WebSocket(`ws://localhost:8080?room=${currentRoomId}`);

    socket.value.onopen = () => {
        console.log(`WebSocket connection established for room: ${currentRoomId}`);
        socket.value.send(JSON.stringify({ type: 'join-room', roomId: currentRoomId }));
    };

    socket.value.onmessage = (event) => {
        handleMessage(event)
    };

    socket.value.onerror = (error)=> {
        console.error("WebSocket error: ", error);
    };

    socket.value.onclose = () => {
        console.log("WebSocket connection closed");
    };
}

const sendMessage = (message) => {
  socket.value.send(JSON.stringify(message))
}

const startCall = async () => {
  createPeerConnection()
  
  const offer = await peerConnection.createOffer()
  await peerConnection.setLocalDescription(offer)
  
  sendMessage({
    type: 'offer',
    offer: offer
  })
}

const handleMessage = async(event) => {
  const message = JSON.parse(event.data)

  console.log("Handling message: ", message)
  if (message.type === 'offer') {
    await createPeerConnection()

    await peerConnection.setRemoteDescription(new RTCSessionDescription(message.offer))

    const answer = await peerConnection.createAnswer()
    await peerConnection.setLocalDescription(answer)
    sendMessage({
      type: 'answer',
      answer: answer
    })
  } else if (message.type === 'answer') {
    await peerConnection.setRemoteDescription(new RTCSessionDescription(message.answer))
  } else if (message.type === 'ice-candidate') {
        if (peerConnection) {
            await peerConnection.addIceCandidate(new RTCIceCandidate(message.candidate))
        }
    }
}

onMounted(async () => {
  try {
    const stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
    if (localVideo.value) {
      localVideo.value.srcObject = stream;
      setupAudioVisualizer(stream);
    }
  } catch (error){
    console.error('Error getting user media', error)
  }

  if (props.roomId) {
    connectWebSocket(props.roomId);
  }
});

onUnmounted(() => {
  if (animationId) cancelAnimationFrame(animationId);
  if (audioContext) audioContext.close();
});
</script>

<template>
  <div class="video-room">
    <div class="room-header">
      <button @click="leaveRoom" class="back-button">← Leave Room</button>
      <h2>Room: {{ roomId }}</h2>
      <div class="spacer"></div>
    </div>

    <div class="video-grid">
        <div class="video-wrapper">
            <h3>You</h3>
            <div class="video-container">
                <video ref="localVideo" autoplay playsinline muted class="local-video"></video>
            </div>
        </div>

        <div class="video-wrapper">
            <h3>Remote</h3>
            <div class="video-container">
                <video ref="remoteVideo" autoplay playsinline class="remote-video"></video>
            </div>
        </div>
    </div>

    <div class="controls">
        <button @click="startCall">
            📞 Start Call
        </button>
        <button @click="toggleCamera">
            {{ isCameraOn ? '📷 Turn Camera Off' : '📷 Turn Camera On' }}
        </button>

        <button @click="toggleMic" :class="{'mic-toggle-on': isMicOn, 'mic-toggle-off': !isMicOn}" class="mic-button-with-viz">
            <span class="mic-label">{{ isMicOn ? '🎤 Mic On' : '🎤 Mic Off' }}</span>
            <canvas ref="audioCanvas" width="40" height="20" class="mic-visualizer-inline"></canvas>
        </button>
    </div>
  </div>
</template>

<style scoped>
.video-room {
    display: flex;
    flex-direction: column;
    align-items: center;
    min-height: 100vh;
    color: white;
    background: #000;
}

.room-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 1rem 2rem;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(10px);
}

.room-header h2 {
    margin: 0;
    flex: 1;
    text-align: center;
}

.spacer {
    width: 140px;
}

.back-button {
    background: transparent;
    border: 2px solid #FF5500;
    color: #FF5500;
    padding: 8px 16px;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s;
}

.back-button:hover {
    background: #FF5500;
    color: white;
    transform: translateX(-2px);
}

.video-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 10px;
    width: 100%;
    flex: 1;
    padding: 10px;
    align-content: center;
}

.video-grid:has(.video-wrapper:only-child) {
    grid-template-columns: 1fr;
    max-width: 1200px;
    margin: 0 auto;
}

/* When there are 2 videos, split 50/50 */
.video-grid:has(.video-wrapper:nth-child(2):last-child) {
    grid-template-columns: 1fr 1fr;
}

.video-wrapper {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
}

.video-wrapper h3 {
    position: absolute;
    top: 10px;
    left: 10px;
    background: rgba(0, 0, 0, 0.7);
    padding: 5px 12px;
    border-radius: 6px;
    font-size: 14px;
    z-index: 10;
    margin: 0;
}

.video-container {
    position: relative;
    width: 100%;
    padding-bottom: 56.25%;
    background: #000;
    border-radius: 12px;
    overflow: hidden;
}

.local-video, .remote-video {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.local-video {
    border: 3px solid #FF5500;
    transform: scaleX(-1);
    border-radius: 12px;
    background: #111;
}

.remote-video {
    border: 3px solid #666;
    border-radius: 12px;
    background: #111;
}

.controls {
    display: flex;
    gap: 15px;
    padding: 20px;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(10px);
    border-top: 1px solid rgba(255, 85, 0, 0.2);
    width: 100%;
    justify-content: center;
    flex-wrap: wrap;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .video-grid {
        grid-template-columns: 1fr !important;
        gap: 10px;
    }

    .controls {
        gap: 10px;
        padding: 15px 10px;
    }

    button {
        padding: 8px 12px;
        font-size: 14px;
    }

    .room-header {
        padding: 0.75rem 1rem;
    }

    .back-button {
        padding: 6px 12px;
        font-size: 13px;
    }
}

/* Base button styles */
button {
    padding: 10px 20px;
    font-size: 16px;
    color: white;
    border-radius: 8px;
    cursor: pointer;
    transition: background-color 0.3s, border-color 0.3s, color 0.3s;
    background-color: #FF5500;
    border: none;
}

button:hover {
    background-color: #FF7700;
}

.mic-button-with-viz {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
}

.mic-label {
    white-space: nowrap;
}

.mic-toggle-on {
    background-color: #000;
    border: 2px solid #FF5500;
    color: #FF5500;
}

.mic-toggle-on:hover {
    background-color: #1a0a00;
    border-color: #FF7700;
    color: #FF7700;
}

.mic-toggle-off {
    background-color: #000;
    border: 2px solid #888;
    color: #888;
}

.mic-toggle-off:hover {
    background-color: #0a0a0a;
    border-color: #aaa;
    color: #aaa;
}

.mic-visualizer-inline {
    width: 40px;
    height: 20px;
    background: rgba(0, 0, 0, 0.3);
    border-radius: 4px;
    border: 1px solid rgba(255, 85, 0, 0.2);
}
</style>
