<script setup>
import { onMounted, ref } from 'vue';
import LandingPage from './components/LandingPage.vue';
import VideoRoom from './components/VideoRoom.vue';

const roomId = ref(null);

const createRoom = async (existingRoomId = null) => {
  try {
    if (existingRoomId && typeof existingRoomId === 'string') {
      roomId.value = existingRoomId;
    } else {
      const apiUrl = import.meta.env.VITE_API_URL;
      if (!apiUrl) {
        console.error('VITE_API_URL is not defined');
        return;
      }
      const response = await fetch(`${apiUrl}/create-room.php`);
      const data = await response.json();
      roomId.value = data.roomId;
    }

    const newUrl = `${window.location.pathname}?room=${roomId.value}`;
    window.history.pushState({ roomId: roomId.value }, '', newUrl);
  } catch (error) {
    console.error('Error creating/joining room:', error);
    alert("Failed to create or join room. Is the backend server running?");
  }
};

const leaveRoom = () => {
  roomId.value = null;
};

onMounted(() => {
  const urlParams = new URLSearchParams(window.location.search);
  const existingRoomId = urlParams.get('room');

  if (existingRoomId) {
    roomId.value = existingRoomId;
  }
});
</script>

<template>
  <main>
    <VideoRoom v-if="roomId" :room-id="roomId" @leave-room="leaveRoom" />
    <LandingPage v-else @create-room="createRoom" />
  </main>
</template>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html {
    overflow-x: hidden;
    width: 100%;
    scrollbar-gutter: stable;
}

body {
    margin: 0;
    padding: 0;
    background-color: #000;
    width: 100%;
    max-width: 100%;
    overflow-x: hidden;
    position: relative;
}

#app {
    max-width: 100% !important;
    width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
    display: block !important;
    grid-template-columns: unset !important;
    overflow-x: hidden;
}

main {
    width: 100%;
    max-width: 100%;
    margin: 0;
    padding: 0;
    overflow-x: hidden;
}
</style>