<script setup>
import { defineEmits } from 'vue';

const emit = defineEmits(['create-room']);
</script>

<template>
  <div class="landing-page">
    <nav class="navbar">
        <div class="logo">VideoCall</div>
    </nav>

    <section class="hero">
      <div class="hero-content">
        <h1>Seamless Video Calls<br>for Everyone.</h1>
        <p>No downloads. No signups. Just clear communication.</p>
        <button @click="emit('create-room')" class="cta-button">
          Create Room
        </button>
      </div>
    </section>

    <section class="features">
      <div class="feature-card">
        <div class="icon">🚀</div>
        <h3>Lightning Fast</h3>
        <p>Low latency peer-to-peer connections ensure you never miss a beat.</p>
      </div>
      <div class="feature-card">
        <div class="icon">🔒</div>
        <h3>Secure & Private</h3>
        <p>Your calls are encrypted and direct. No data is stored on our servers.</p>
      </div>
      <div class="feature-card">
        <div class="icon">✨</div>
        <h3>Crystal Clear</h3>
        <p>HD video and audio quality powered by modern WebRTC technology.</p>
      </div>
    </section>
  </div>
</template>

<style scoped>
.landing-page {
  background-color: #000;
  color: white;
  min-height: 100vh;
  font-family: 'Inter', sans-serif;
  overflow-x: hidden;
  width: 100%;
  max-width: 100vw;
  position: relative;
}

.navbar {
    padding: 20px 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    max-width: 1400px;
    margin: 0 auto;
    width: 100%;
    box-sizing: border-box;
}

.logo {
    font-weight: 800;
    font-size: 1.5rem;
    color: #FF5500;
}

.hero {
  position: relative;
  height: 85vh;
  display: flex;
  align-items: center;
  justify-content: center;
  text-align: center;
  overflow: hidden;
}

.hero-content {
  position: relative;
  z-index: 4;
  width: 100%;
  max-width: 1200px;
  padding: 0 40px;
  margin: 0 auto;
  text-align: center;
}

h1 {
  font-size: 5rem;
  line-height: 1.1;
  margin-bottom: 2rem;
  margin-left: auto;
  margin-right: auto;
  font-weight: 900;
  letter-spacing: -2px;
  background: linear-gradient(to right, #fff, #aaa);
  background-clip: text;
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

p {
  font-size: 1.5rem;
  color: #888;
  margin-bottom: 3rem;
  max-width: 700px;
  margin-left: auto;
  margin-right: auto;
}

.cta-button {
  background: #FF5500;
  color: white;
  border: none;
  padding: 18px 40px;
  font-size: 1.3rem;
  font-weight: 600;
  border-radius: 50px;
  cursor: pointer;
  transition: transform 0.2s, box-shadow 0.2s;
  box-shadow: 0 0 20px rgba(255, 85, 0, 0.3);
}

.cta-button:hover {
  transform: translateY(-2px);
  box-shadow: 0 0 40px rgba(255, 85, 0, 0.6);
  background: #FF6600;
}

.features {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 40px;
  padding: 100px 40px;
  background: #0a0a0a;
  max-width: 1400px;
  margin: 0 auto;
}

.feature-card {
  background: #111;
  padding: 40px;
  border-radius: 20px;
  border: 1px solid #222;
  transition: transform 0.3s;
}

.feature-card:hover {
    transform: translateY(-5px);
    border-color: #333;
}

.icon {
    font-size: 2.5rem;
    margin-bottom: 25px;
}

.feature-card h3 {
  font-size: 1.8rem;
  margin-bottom: 15px;
  color: white;
}

.feature-card p {
  font-size: 1.1rem;
  color: #888;
  margin-bottom: 0;
  line-height: 1.6;
}

/* Mobile Responsiveness */
@media (max-width: 768px) {
    .navbar {
        padding: 20px;
    }
    
    h1 {
        font-size: 3rem;
    }
    
    .hero {
        height: auto;
        padding: 100px 0;
        min-height: 70vh;
    }
    
    .hero-content {
        padding: 0 20px;
    }
    
    p {
        font-size: 1.1rem;
    }
    
    .features {
        padding: 60px 20px;
        gap: 20px;
    }
    
    .feature-card {
        padding: 30px;
    }
}
</style>
