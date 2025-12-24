<script setup>
import { onMounted, ref } from 'vue';

const socket = ref(null);
const localVideo = ref(null)
const remoteVideo = ref(null)

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

onMounted(async () => {
  socket.value = new WebSocket("ws://localhost:8080")

  socket.value.onopen = () => {
    console.log("WebSocket connection established");
  };

  socket.value.onmessage = (event) => {
    console.log("Message received from server", event.data);
  }

  socket.value.onerror = (error)=> {
    console.error("WebSocket error: ", error);
  }

  socket.value.onclose = () => {
    console.log("WebSocket connection closed");
  }

  try {
    const stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
    if (localVideo.value) {
      localVideo.value.srcObject = stream;
    }
  } catch (error){
    console.error('Error here', error)
  }
});

const sendMessage = (message) => {
  socket.value.send(JSON.stringify(message))
}

const startCall = async () => {
  await createPeerConnection()
  
  const offer = await peerConnection.createOffer()
  await peerConnection.setLocalDescription(offer)
  
  sendMessage({
    type: 'offer',
    offer: offer
  })
}
</script>

<template>
  <h1>Video Call App</h1>

 <div class="video-grid" style="display: flex; gap: 20px;">
    <div class="video-wrapper">
      <h3>You</h3>
      <video ref="localVideo" autoplay playsinline muted style="width: 300px; border: 2px solid blue; transform: scaleX(-1);"></video>
    </div>

    <div class="video-wrapper">
      <h3>Remote</h3>
      <video ref="remoteVideo" autoplay playsinline style="width: 300px; border: 2px solid red;"></video>
    </div>
  </div>

  <button @click="startCall" style="margin-top: 20px; padding: 10px 20px; font-size: 16px;">
    📞 Call Everyone
  </button>
</template>
