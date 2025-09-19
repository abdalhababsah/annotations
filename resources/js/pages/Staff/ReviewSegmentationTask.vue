<template>
  <div class="creative-homepage">
    <Head title="AudioAI - Revolutionary Audio Intelligence Platform">
      <link rel="preconnect" href="https://fonts.bunny.net" />
      <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800,900|space-grotesk:300,400,500,600,700,800,900" rel="stylesheet" />
    </Head>

    <!-- Floating Navigation -->
    <nav class="floating-nav" :class="{ 'nav-scrolled': scrolled }">
      <div class="nav-container">
        <div class="nav-brand">
          <div class="logo-orb"></div>
          <span class="brand-text">AudioAI</span>
        </div>
        
        <div class="nav-links">
          <a href="#features" class="nav-link magnetic-element">Features</a>
          <a href="#technology" class="nav-link magnetic-element">Technology</a>
          <a href="#workflow" class="nav-link magnetic-element">Workflow</a>
          <a href="#results" class="nav-link magnetic-element">Results</a>
        </div>

        <div class="nav-actions">
          <Button v-if="$page.props.auth.user" class="nav-btn primary-btn magnetic-element">
            <Link :href="route('dashboard')">Dashboard</Link>
          </Button>
          <template v-else>
            <Button class="nav-btn ghost-btn magnetic-element">
              <Link :href="route('login')">Sign In</Link>
            </Button>
            <Button class="nav-btn primary-btn magnetic-element">
              <Link :href="route('register')">Launch AI</Link>
            </Button>
          </template>
        </div>
      </div>
    </nav>

    <!-- Hero Section with Interactive Audio Visualizer -->
    <section class="hero-section" ref="heroSection">
      <div class="hero-background">
        <div class="gradient-orbs">
          <div class="orb orb-1"></div>
          <div class="orb orb-2"></div>
          <div class="orb orb-3"></div>
        </div>
        
        <!-- Interactive Particles -->
        <div class="particles-container">
          <div v-for="i in 50" :key="i" class="particle" 
               :style="{ left: Math.random() * 100 + '%', animationDelay: Math.random() * 3 + 's' }"></div>
        </div>
      </div>

      <div class="hero-content">
        <div class="hero-left">
          <div class="hero-badge animate-float">
            <Sparkles class="w-4 h-4" />
            <span>Next-Gen Audio Intelligence</span>
          </div>

          <h1 class="hero-title">
            <span class="title-line">
              <span class="morphing-text" data-text="Transform">Transform</span>
            </span>
            <span class="title-line">
              <span class="gradient-text">Audio Data</span>
            </span>
            <span class="title-line">
              <span class="morphing-text" data-text="Into AI Magic">Into AI Magic</span>
            </span>
          </h1>

          <p class="hero-description">
            Revolutionary platform that turns raw audio into perfectly labeled training datasets. 
            AI-powered segmentation, collaborative annotation, and automated quality assurance.
          </p>

          <div class="hero-actions">
            <Button class="primary-action magnetic-element">
              <Link :href="route('register')">
                Start Creating
                <div class="btn-effect"></div>
              </Link>
            </Button>
            <Button class="secondary-action magnetic-element">
              <Play class="w-5 h-5" />
              Watch Demo
            </Button>
          </div>

          <div class="hero-stats">
            <div class="stat-item">
              <div class="stat-number" data-target="2.5">0</div>
              <div class="stat-label">Million Hours Processed</div>
            </div>
            <div class="stat-item">
              <div class="stat-number" data-target="99.8">0</div>
              <div class="stat-label">% Accuracy Achieved</div>
            </div>
            <div class="stat-item">
              <div class="stat-number" data-target="500">0</div>
              <div class="stat-label">AI Models Trained</div>
            </div>
          </div>
        </div>

        <div class="hero-right">
          <div class="audio-visualizer-container">
            <div class="visualizer-frame">
              <div class="visualizer-header">
                <div class="playback-controls">
                  <div class="control-btn play-btn">
                    <Play class="w-4 h-4" />
                  </div>
                  <div class="waveform-progress">
                    <div class="progress-bar"></div>
                  </div>
                  <div class="time-display">1:23 / 3:45</div>
                </div>
              </div>
              
              <div class="interactive-waveform">
                <div v-for="i in 100" :key="i" 
                     class="wave-bar" 
                     :style="{ height: Math.random() * 80 + 20 + '%', animationDelay: i * 0.01 + 's' }">
                </div>
              </div>

              <div class="annotation-labels">
                <div class="label-tag speech-tag" style="left: 20%; width: 30%;">
                  <span>Speech</span>
                </div>
                <div class="label-tag music-tag" style="left: 55%; width: 25%;">
                  <span>Music</span>
                </div>
                <div class="label-tag noise-tag" style="left: 85%; width: 10%;">
                  <span>Noise</span>
                </div>
              </div>
            </div>

            <!-- Floating UI Elements -->
            <div class="floating-ui-elements">
              <div class="floating-card accuracy-card">
                <div class="card-icon">🎯</div>
                <div class="card-content">
                  <div class="card-value">99.8%</div>
                  <div class="card-label">Accuracy</div>
                </div>
              </div>
              
              <div class="floating-card speed-card">
                <div class="card-icon">⚡</div>
                <div class="card-content">
                  <div class="card-value">10x</div>
                  <div class="card-label">Faster</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Problem & Solution Section -->
    <section class="problem-solution-section">
      <div class="section-container">
        <div class="split-content">
          <div class="problem-side">
            <div class="section-badge problem-badge">The Challenge</div>
            <h2 class="section-title">Audio Data is Messy, Complex, and Time-Consuming</h2>
            <div class="problem-points">
              <div class="problem-point">
                <div class="problem-icon">😤</div>
                <div class="problem-text">Manual annotation takes months</div>
              </div>
              <div class="problem-point">
                <div class="problem-icon">😵</div>
                <div class="problem-text">Inconsistent labeling quality</div>
              </div>
              <div class="problem-point">
                <div class="problem-icon">💸</div>
                <div class="problem-text">Expensive outsourcing costs</div>
              </div>
              <div class="problem-point">
                <div class="problem-icon">🔄</div>
                <div class="problem-text">Endless revision cycles</div>
              </div>
            </div>
          </div>

          <div class="solution-side">
            <div class="section-badge solution-badge">Our Solution</div>
            <h2 class="section-title">AI-Powered Automation Meets Human Precision</h2>
            <div class="solution-points">
              <div class="solution-point">
                <div class="solution-icon">🚀</div>
                <div class="solution-text">10x faster with AI pre-segmentation</div>
              </div>
              <div class="solution-point">
                <div class="solution-icon">🎯</div>
                <div class="solution-text">99.8% accuracy with quality gates</div>
              </div>
              <div class="solution-point">
                <div class="solution-icon">💰</div>
                <div class="solution-text">80% cost reduction</div>
              </div>
              <div class="solution-point">
                <div class="solution-icon">✨</div>
                <div class="solution-text">One-click dataset export</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Bento Grid Features -->
    <section id="features" class="features-section">
      <div class="section-container">
        <div class="section-header">
          <div class="section-badge">Revolutionary Features</div>
          <h2 class="section-title">Everything You Need in One Platform</h2>
          <p class="section-description">
            From raw audio to production-ready datasets in minutes, not months
          </p>
        </div>

        <div class="bento-grid">
          <!-- AI Segmentation -->
          <div class="bento-card large-card ai-segmentation">
            <div class="card-content">
              <div class="card-header">
                <div class="card-icon">🤖</div>
                <h3>AI Auto-Segmentation</h3>
              </div>
              <p>Advanced ML models automatically detect and segment speech, music, silence, and custom audio events with superhuman precision.</p>
            </div>
            <div class="card-visual">
              <div class="segmentation-demo">
                <div class="demo-waveform">
                  <div v-for="i in 50" :key="i" class="demo-bar" 
                       :class="{ active: i % 3 === 0 }"
                       :style="{ height: Math.random() * 60 + 20 + 'px' }">
                  </div>
                </div>
                <div class="segment-overlays">
                  <div class="segment speech-segment" style="left: 10%; width: 30%;"></div>
                  <div class="segment music-segment" style="left: 45%; width: 40%;"></div>
                </div>
              </div>
            </div>
          </div>

          <!-- Collaborative Annotation -->
          <div class="bento-card medium-card collaboration">
            <div class="card-content">
              <div class="card-header">
                <div class="card-icon">👥</div>
                <h3>Team Collaboration</h3>
              </div>
              <p>Real-time collaborative annotation with role-based access and review workflows.</p>
            </div>
            <div class="collaboration-visual">
              <div class="user-avatars">
                <div class="avatar avatar-1"></div>
                <div class="avatar avatar-2"></div>
                <div class="avatar avatar-3"></div>
              </div>
            </div>
          </div>

          <!-- Quality Assurance -->
          <div class="bento-card medium-card quality">
            <div class="card-content">
              <div class="card-header">
                <div class="card-icon">✅</div>
                <h3>Quality Gates</h3>
              </div>
              <p>Automated quality checks and human review ensure perfect training data.</p>
            </div>
            <div class="quality-visual">
              <div class="quality-meter">
                <div class="meter-fill"></div>
                <div class="meter-label">99.8%</div>
              </div>
            </div>
          </div>

          <!-- Export Formats -->
          <div class="bento-card small-card export">
            <div class="card-content">
              <div class="card-header">
                <div class="card-icon">📤</div>
                <h3>Universal Export</h3>
              </div>
              <p>Export to any format for any ML platform.</p>
            </div>
          </div>

          <!-- Speed -->
          <div class="bento-card small-card speed">
            <div class="card-content">
              <div class="card-header">
                <div class="card-icon">⚡</div>
                <h3>Lightning Fast</h3>
              </div>
              <p>Process hours in minutes with parallel processing.</p>
            </div>
          </div>

          <!-- Custom Labels -->
          <div class="bento-card medium-card labels">
            <div class="card-content">
              <div class="card-header">
                <div class="card-icon">🏷️</div>
                <h3>Custom Labels</h3>
              </div>
              <p>Create domain-specific annotation schemas for any use case.</p>
            </div>
            <div class="labels-visual">
              <div class="label-tags">
                <span class="label-tag">Speech</span>
                <span class="label-tag">Music</span>
                <span class="label-tag">Noise</span>
                <span class="label-tag">Custom</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Technology Showcase -->
    <section id="technology" class="tech-section">
      <div class="section-container">
        <div class="section-header">
          <div class="section-badge">Technology</div>
          <h2 class="section-title">Powered by Cutting-Edge AI</h2>
        </div>

        <div class="tech-grid">
          <div class="tech-card">
            <div class="tech-icon">🧠</div>
            <h3>Advanced ML Models</h3>
            <p>State-of-the-art transformer architectures trained on millions of audio hours</p>
          </div>
          <div class="tech-card">
            <div class="tech-icon">☁️</div>
            <h3>Cloud-Native Architecture</h3>
            <p>Scalable infrastructure that grows with your data processing needs</p>
          </div>
          <div class="tech-card">
            <div class="tech-icon">🔒</div>
            <h3>Enterprise Security</h3>
            <p>SOC 2 compliance with end-to-end encryption and audit trails</p>
          </div>
          <div class="tech-card">
            <div class="tech-icon">🔗</div>
            <h3>API-First Design</h3>
            <p>Integrate seamlessly with your existing ML pipeline and tools</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Workflow Animation -->
    <section id="workflow" class="workflow-section">
      <div class="section-container">
        <div class="section-header">
          <div class="section-badge">How It Works</div>
          <h2 class="section-title">From Upload to Trained Model in 3 Steps</h2>
        </div>

        <div class="workflow-container">
          <div class="workflow-step" v-for="(step, index) in workflowSteps" :key="index">
            <div class="step-number">{{ index + 1 }}</div>
            <div class="step-content">
              <h3>{{ step.title }}</h3>
              <p>{{ step.description }}</p>
            </div>
            <div class="step-visual">
              <component :is="step.icon" class="w-12 h-12" />
            </div>
          </div>
          
          <!-- Animated Connection Line -->
          <svg class="workflow-line" viewBox="0 0 800 200">
            <path d="M50 100 Q 200 50 350 100 Q 500 150 650 100" 
                  stroke="url(#workflowGradient)" 
                  stroke-width="3" 
                  fill="none" 
                  class="animated-path" />
            <defs>
              <linearGradient id="workflowGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                <stop offset="0%" style="stop-color:hsl(var(--primary));stop-opacity:0.3" />
                <stop offset="50%" style="stop-color:hsl(var(--primary));stop-opacity:1" />
                <stop offset="100%" style="stop-color:hsl(var(--primary));stop-opacity:0.3" />
              </linearGradient>
            </defs>
          </svg>
        </div>
      </div>
    </section>

    <!-- Results & Metrics -->
    <section id="results" class="results-section">
      <div class="section-container">
        <div class="section-header">
          <div class="section-badge">Proven Results</div>
          <h2 class="section-title">Numbers That Speak</h2>
        </div>

        <div class="metrics-grid">
          <div class="metric-card" v-for="metric in metrics" :key="metric.label">
            <div class="metric-icon">{{ metric.icon }}</div>
            <div class="metric-value">{{ metric.value }}</div>
            <div class="metric-label">{{ metric.label }}</div>
            <div class="metric-description">{{ metric.description }}</div>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
      <div class="cta-background">
        <div class="cta-orbs">
          <div class="cta-orb cta-orb-1"></div>
          <div class="cta-orb cta-orb-2"></div>
        </div>
      </div>
      
      <div class="cta-content">
        <h2 class="cta-title">Ready to Transform Your Audio Workflow?</h2>
        <p class="cta-description">
          Join the AI revolution. Start creating perfect training datasets today.
        </p>
        
        <div class="cta-actions">
          <Button class="cta-primary magnetic-element">
            <Link :href="route('register')">
              Start Free Trial
              <div class="btn-shine"></div>
            </Link>
          </Button>
          <Button class="cta-secondary magnetic-element">
            Schedule Demo
          </Button>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="creative-footer">
      <div class="footer-content">
        <div class="footer-brand">
          <div class="footer-logo">
            <div class="logo-orb"></div>
            <span>AudioAI</span>
          </div>
          <p>Transforming audio into intelligence</p>
        </div>
        
        <div class="footer-links">
          <div class="link-group">
            <h4>Platform</h4>
            <a href="#features">Features</a>
            <a href="#pricing">Pricing</a>
            <a href="#docs">Documentation</a>
          </div>
          <div class="link-group">
            <h4>Company</h4>
            <a href="#about">About</a>
            <a href="#careers">Careers</a>
            <a href="#contact">Contact</a>
          </div>
          <div class="link-group">
            <h4>Resources</h4>
            <a href="#blog">Blog</a>
            <a href="#help">Help Center</a>
            <a href="#api">API</a>
          </div>
        </div>
      </div>
      
      <div class="footer-bottom">
        <p>&copy; {{ new Date().getFullYear() }} AudioAI. All rights reserved.</p>
        <div class="social-links">
          <a href="#" class="social-link"><Github class="w-5 h-5" /></a>
          <a href="#" class="social-link"><Twitter class="w-5 h-5" /></a>
        </div>
      </div>
    </footer>

    <!-- Cursor Follower -->
    <div class="cursor-follower" :style="{ left: cursorX + 'px', top: cursorY + 'px' }"></div>
  </div>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '../components/ui/button';
import { ref, onMounted, onUnmounted } from 'vue';
import { 
  Play, Sparkles, Upload, Brain, Download, Github, Twitter
} from 'lucide-vue-next';

// Reactive data
const scrolled = ref(false);
const cursorX = ref(0);
const cursorY = ref(0);
const heroSection = ref(null);

const workflowSteps = [
  {
    title: 'Upload Audio',
    description: 'Drag & drop your audio files. We handle all formats automatically.',
    icon: Upload
  },
  {
    title: 'AI Processing',
    description: 'Our ML models analyze and segment your audio with superhuman accuracy.',
    icon: Brain
  },
  {
    title: 'Export Dataset',
    description: 'Download perfectly labeled training data ready for your AI models.',
    icon: Download
  }
];

const metrics = [
  {
    icon: '🚀',
    value: '10x',
    label: 'Faster Processing',
    description: 'Compared to manual annotation'
  },
  {
    icon: '🎯',
    value: '99.8%',
    label: 'Accuracy Rate',
    description: 'Validated by human experts'
  },
  {
    icon: '💰',
    value: '80%',
    label: 'Cost Reduction',
    description: 'Vs traditional outsourcing'
  },
  {
    icon: '⏱️',
    value: '5min',
    label: 'Average Processing',
    description: 'Per hour of audio'
  }
];

// Methods
const handleMouseMove = (e: MouseEvent) => {
  cursorX.value = e.clientX;
  cursorY.value = e.clientY;
};

const handleScroll = () => {
  scrolled.value = window.scrollY > 50;
};

const animateCounters = () => {
  const counters = document.querySelectorAll('.stat-number');
  counters.forEach((counter) => {
    const target = parseInt(counter.getAttribute('data-target') || '0');
    let current = 0;
    const increment = target / 100;
    const timer = setInterval(() => {
      current += increment;
      if (current >= target) {
        current = target;
        clearInterval(timer);
      }
      counter.textContent = Math.floor(current).toString() + (target < 100 ? '' : '');
    }, 20);
  });
};

const setupMagneticElements = () => {
  const elements = document.querySelectorAll('.magnetic-element');
  elements.forEach((el) => {
    el.addEventListener('mousemove', (e) => {
      const rect = el.getBoundingClientRect();
      const centerX = rect.left + rect.width / 2;
      const centerY = rect.top + rect.height / 2;
      const deltaX = (e.clientX - centerX) * 0.1;
      const deltaY = (e.clientY - centerY) * 0.1;
      el.style.transform = `translate(${deltaX}px, ${deltaY}px)`;
    });
    
    el.addEventListener('mouseleave', () => {
      el.style.transform = 'translate(0px, 0px)';
    });
  });
};

onMounted(() => {
  window.addEventListener('scroll', handleScroll);
  window.addEventListener('mousemove', handleMouseMove);
  
  setTimeout(animateCounters, 1000);
  setTimeout(setupMagneticElements, 500);
});

onUnmounted(() => {
  window.removeEventListener('scroll', handleScroll);
  window.removeEventListener('mousemove', handleMouseMove);
});
</script>

<style scoped>
/* Global Styles */
.creative-homepage {
  background: hsl(var(--background));
  color: hsl(var(--foreground));
  overflow-x: hidden;
  position: relative;
}

/* Custom Cursor */
.cursor-follower {
  position: fixed;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: hsl(var(--primary));
  opacity: 0.3;
  pointer-events: none;
  z-index: 9999;
  transition: transform 0.1s ease;
  transform: translate(-50%, -50%);
}

/* Floating Navigation */
.floating-nav {
  position: fixed;
  top: 20px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 1000;
  backdrop-filter: blur(20px);
  background: rgba(255, 255, 255, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 50px;
  padding: 8px 24px;
  transition: all 0.3s ease;
}

.floating-nav.nav-scrolled {
  background: rgba(0, 0, 0, 0.8);
  border-color: hsl(var(--border));
}

.nav-container {
  display: flex;
  align-items: center;
  gap: 32px;
}

.nav-brand {
  display: flex;
  align-items: center;
  gap: 12px;
}

.logo-orb {
  width: 32px;
  height: 32px;
  background: linear-gradient(135deg, hsl(var(--primary)), hsl(var(--accent)));
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.brand-text {
  font-weight: 700;
  font-size: 18px;
  color: hsl(var(--foreground));
}

.nav-links {
  display: flex;
  gap: 24px;
}

.nav-link {
  color: hsl(var(--muted-foreground));
  text-decoration: none;
  font-weight: 500;
  transition: all 0.3s ease;
  position: relative;
}

.nav-link:hover {
  color: hsl(var(--foreground));
}

.nav-actions {
  display: flex;
  gap: 12px;
}

.nav-btn {
  border-radius: 25px;
  padding: 8px 20px;
  font-weight: 500;
  transition: all 0.3s ease;
}

.primary-btn {
  background: hsl(var(--primary));
  color: hsl(var(--primary-foreground));
  border: none;
}

.ghost-btn {
  background: transparent;
  color: hsl(var(--muted-foreground));
  border: 1px solid transparent;
}

.ghost-btn:hover {
  border-color: hsl(var(--border));
  color: hsl(var(--foreground));
}

/* Hero Section */
.hero-section {
  min-height: 100vh;
  display: flex;
  align-items: center;
  position: relative;
  overflow: hidden;
}

.hero-background {
  position: absolute;
  inset: 0;
  z-index: -1;
}

.gradient-orbs {
  position: absolute;
  inset: 0;
}

.orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(100px);
  opacity: 0.6;
}

.orb-1 {
  width: 400px;
  height: 400px;
  background: hsl(var(--primary));
  top: 10%;
  left: 10%;
  animation: float 6s ease-in-out infinite;
}

.orb-2 {
  width: 300px;
  height: 300px;
  background: hsl(var(--accent));
  top: 60%;
  right: 10%;
  animation: float 8s ease-in-out infinite reverse;
}

.orb-3 {
  width: 200px;
  height: 200px;
  background: hsl(var(--secondary));
  bottom: 20%;
  left: 60%;
  animation: float 7s ease-in-out infinite;
}

.particles-container {
  position: absolute;
  inset: 0;
}

.particle {
  position: absolute;
  width: 4px;
  height: 4px;
  background: hsl(var(--primary));
  border-radius: 50%;
  opacity: 0.6;
  animation: particleFloat 4s ease-in-out infinite;
}

.hero-content {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 24px;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 80px;
  align-items: center;
}

.hero-left {
  z-index: 10;
}

.hero-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 20px;
  background: rgba(255, 255, 255, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 50px;
  font-size: 14px;
  font-weight: 500;
  color: hsl(var(--foreground));
  margin-bottom: 32px;
}

.hero-title {
  font-size: clamp(48px, 8vw, 72px);
  font-weight: 800;
  line-height: 1.1;
  margin-bottom: 24px;
}

.title-line {
  display: block;
  overflow: hidden;
}

.morphing-text {
  display: inline-block;
  position: relative;
  animation: morphText 3s ease-in-out infinite;
}

.gradient-text {
  background: linear-gradient(135deg, hsl(var(--primary)), hsl(var(--accent)), hsl(var(--foreground)));
  background-size: 200% 200%;
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  animation: gradientShift 3s ease-in-out infinite;
}

.hero-description {
  font-size: 20px;
  line-height: 1.6;
  color: hsl(var(--muted-foreground));
  margin-bottom: 40px;
  max-width: 500px;
}

.hero-actions {
  display: flex;
  gap: 20px;
  margin-bottom: 60px;
}

.primary-action {
  background: hsl(var(--primary));
  color: hsl(var(--primary-foreground));
  padding: 16px 32px;
  border-radius: 50px;
  font-size: 16px;
  font-weight: 600;
  border: none;
  position: relative;
  overflow: hidden;
  transition: all 0.3s ease;
}

.primary-action:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
}

.btn-effect {
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
  transition: left 0.5s;
}

.primary-action:hover .btn-effect {
  left: 100%;
}

.secondary-action {
  background: transparent;
  color: hsl(var(--foreground));
  border: 2px solid hsl(var(--border));
  padding: 14px 28px;
  border-radius: 50px;
  font-size: 16px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 8px;
  transition: all 0.3s ease;
}

.secondary-action:hover {
  border-color: hsl(var(--primary));
  color: hsl(var(--primary));
}

.hero-stats {
  display: flex;
  gap: 40px;
}

.stat-item {
  text-align: left;
}

.stat-number {
  font-size: 32px;
  font-weight: 800;
  color: hsl(var(--primary));
  line-height: 1;
}

.stat-label {
  font-size: 14px;
  color: hsl(var(--muted-foreground));
  margin-top: 4px;
}

/* Audio Visualizer */
.audio-visualizer-container {
  position: relative;
  z-index: 10;
}

.visualizer-frame {
  background: rgba(255, 255, 255, 0.05);
  backdrop-filter: blur(20px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 20px;
  padding: 24px;
  position: relative;
  overflow: hidden;
}

.visualizer-header {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 20px;
}

.playback-controls {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
}

.control-btn {
  width: 40px;
  height: 40px;
  background: hsl(var(--primary));
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: hsl(var(--primary-foreground));
  cursor: pointer;
  transition: all 0.3s ease;
}

.control-btn:hover {
  transform: scale(1.1);
}

.waveform-progress {
  flex: 1;
  height: 4px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 2px;
  position: relative;
}

.progress-bar {
  height: 100%;
  width: 35%;
  background: hsl(var(--primary));
  border-radius: 2px;
  animation: progressMove 3s ease-in-out infinite;
}

.time-display {
  font-size: 12px;
  color: hsl(var(--muted-foreground));
  min-width: 80px;
}

.interactive-waveform {
  display: flex;
  align-items: end;
  gap: 2px;
  height: 120px;
  margin: 20px 0;
}

.wave-bar {
  flex: 1;
  background: linear-gradient(to top, hsl(var(--primary)), hsl(var(--accent)));
  border-radius: 2px;
  min-height: 4px;
  animation: waveAnimation 2s ease-in-out infinite alternate;
  opacity: 0.8;
}

.annotation-labels {
  position: relative;
  height: 30px;
  margin-top: 16px;
}

.label-tag {
  position: absolute;
  top: 0;
  padding: 4px 12px;
  border-radius: 15px;
  font-size: 12px;
  font-weight: 500;
  color: white;
  text-align: center;
}

.speech-tag {
  background: #10B981;
}

.music-tag {
  background: #3B82F6;
}

.noise-tag {
  background: #EF4444;
}

.floating-ui-elements {
  position: absolute;
  inset: 0;
  pointer-events: none;
}

.floating-card {
  position: absolute;
  background: rgba(255, 255, 255, 0.1);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 12px;
  padding: 12px 16px;
  display: flex;
  align-items: center;
  gap: 8px;
  animation: floatCard 4s ease-in-out infinite;
}

.accuracy-card {
  top: -20px;
  right: -40px;
  animation-delay: 0s;
}

.speed-card {
  bottom: -20px;
  left: -40px;
  animation-delay: 2s;
}

.card-icon {
  font-size: 20px;
}

.card-value {
  font-size: 16px;
  font-weight: 700;
  color: hsl(var(--foreground));
}

.card-label {
  font-size: 12px;
  color: hsl(var(--muted-foreground));
}

/* Problem Solution Section */
.problem-solution-section {
  padding: 120px 0;
  background: linear-gradient(135deg, rgba(0,0,0,0.05), rgba(0,0,0,0.02));
}

.section-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 24px;
}

.split-content {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 80px;
  align-items: start;
}

.section-badge {
  display: inline-block;
  padding: 6px 16px;
  background: rgba(255, 255, 255, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 20px;
}

.problem-badge {
  color: #EF4444;
  border-color: #EF4444;
  background: rgba(239, 68, 68, 0.1);
}

.solution-badge {
  color: #10B981;
  border-color: #10B981;
  background: rgba(16, 185, 129, 0.1);
}

.section-title {
  font-size: 36px;
  font-weight: 700;
  line-height: 1.2;
  margin-bottom: 32px;
}

.problem-points,
.solution-points {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.problem-point,
.solution-point {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px;
  border-radius: 12px;
  transition: all 0.3s ease;
}

.problem-point {
  background: rgba(239, 68, 68, 0.05);
  border: 1px solid rgba(239, 68, 68, 0.2);
}

.solution-point {
  background: rgba(16, 185, 129, 0.05);
  border: 1px solid rgba(16, 185, 129, 0.2);
}

.problem-icon,
.solution-icon {
  font-size: 24px;
  min-width: 24px;
}

.problem-text,
.solution-text {
  font-size: 16px;
  font-weight: 500;
}

/* Features Section - Bento Grid */
.features-section {
  padding: 120px 0;
}

.section-header {
  text-align: center;
  margin-bottom: 80px;
}

.section-header .section-title {
  font-size: 48px;
  margin-bottom: 16px;
}

.section-description {
  font-size: 18px;
  color: hsl(var(--muted-foreground));
  max-width: 600px;
  margin: 0 auto;
}

.bento-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  grid-template-rows: repeat(3, 200px);
  gap: 20px;
}

.bento-card {
  background: rgba(255, 255, 255, 0.03);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 20px;
  padding: 24px;
  position: relative;
  overflow: hidden;
  transition: all 0.3s ease;
}

.bento-card:hover {
  transform: translateY(-5px);
  border-color: hsl(var(--primary));
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
}

.large-card {
  grid-column: span 2;
  grid-row: span 2;
}

.medium-card {
  grid-column: span 2;
  grid-row: span 1;
}

.small-card {
  grid-column: span 1;
  grid-row: span 1;
}

.card-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
}

.card-icon {
  font-size: 24px;
}

.card-header h3 {
  font-size: 18px;
  font-weight: 600;
  margin: 0;
}

.card-content p {
  color: hsl(var(--muted-foreground));
  line-height: 1.5;
}

/* Bento Card Visuals */
.segmentation-demo {
  position: absolute;
  bottom: 24px;
  right: 24px;
  left: 24px;
}

.demo-waveform {
  display: flex;
  gap: 2px;
  height: 60px;
  align-items: end;
  margin-bottom: 8px;
}

.demo-bar {
  flex: 1;
  background: rgba(255, 255, 255, 0.3);
  border-radius: 1px;
  min-height: 4px;
  transition: all 0.3s ease;
}

.demo-bar.active {
  background: hsl(var(--primary));
  animation: pulse 1s infinite;
}

.segment-overlays {
  position: relative;
  height: 20px;
}

.segment {
  position: absolute;
  height: 100%;
  border-radius: 10px;
  opacity: 0.7;
}

.speech-segment {
  background: #10B981;
}

.music-segment {
  background: #3B82F6;
}

.collaboration-visual {
  position: absolute;
  bottom: 24px;
  right: 24px;
}

.user-avatars {
  display: flex;
  gap: -8px;
}

.avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  border: 2px solid white;
  margin-left: -8px;
}

.avatar-1 { background: linear-gradient(135deg, #FF6B6B, #4ECDC4); }
.avatar-2 { background: linear-gradient(135deg, #45B7D1, #96CEB4); }
.avatar-3 { background: linear-gradient(135deg, #FECA57, #FF9FF3); }

.quality-visual {
  position: absolute;
  bottom: 24px;
  right: 24px;
}

.quality-meter {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: conic-gradient(hsl(var(--primary)) 99.8%, rgba(255,255,255,0.2) 0%);
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
}

.quality-meter::before {
  content: '';
  width: 60px;
  height: 60px;
  background: hsl(var(--card));
  border-radius: 50%;
  position: absolute;
}

.meter-label {
  font-size: 14px;
  font-weight: 700;
  z-index: 1;
}

.labels-visual {
  position: absolute;
  bottom: 24px;
  left: 24px;
  right: 24px;
}

.label-tags {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.label-tags .label-tag {
  background: hsl(var(--primary));
  color: hsl(var(--primary-foreground));
  padding: 4px 12px;
  border-radius: 15px;
  font-size: 12px;
  font-weight: 500;
}

/* Technology Section */
.tech-section {
  padding: 120px 0;
  background: linear-gradient(135deg, rgba(0,0,0,0.02), transparent);
}

.tech-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 32px;
  margin-top: 60px;
}

.tech-card {
  background: rgba(255, 255, 255, 0.03);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 32px;
  text-align: center;
  transition: all 0.3s ease;
}

.tech-card:hover {
  transform: translateY(-5px);
  border-color: hsl(var(--primary));
}

.tech-icon {
  font-size: 48px;
  margin-bottom: 20px;
}

.tech-card h3 {
  font-size: 20px;
  font-weight: 600;
  margin-bottom: 12px;
}

.tech-card p {
  color: hsl(var(--muted-foreground));
  line-height: 1.6;
}

/* Workflow Section */
.workflow-section {
  padding: 120px 0;
}

.workflow-container {
  position: relative;
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 60px;
  margin-top: 80px;
}

.workflow-step {
  text-align: center;
  position: relative;
}

.step-number {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  background: hsl(var(--primary));
  color: hsl(var(--primary-foreground));
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  font-weight: 700;
  margin: 0 auto 20px;
}

.step-content h3 {
  font-size: 24px;
  font-weight: 600;
  margin-bottom: 12px;
}

.step-content p {
  color: hsl(var(--muted-foreground));
  line-height: 1.6;
}

.step-visual {
  margin-top: 20px;
  opacity: 0.6;
}

.workflow-line {
  position: absolute;
  top: 30px;
  left: 0;
  width: 100%;
  height: 100px;
  z-index: -1;
}

.animated-path {
  stroke-dasharray: 1000;
  stroke-dashoffset: 1000;
  animation: drawPath 3s ease-in-out forwards;
  animation-delay: 1s;
}

/* Results Section */
.results-section {
  padding: 120px 0;
  background: linear-gradient(135deg, rgba(0,0,0,0.05), transparent);
}

.metrics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 32px;
  margin-top: 60px;
}

.metric-card {
  background: rgba(255, 255, 255, 0.03);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 32px;
  text-align: center;
  transition: all 0.3s ease;
}

.metric-card:hover {
  transform: translateY(-5px);
  border-color: hsl(var(--primary));
}

.metric-icon {
  font-size: 48px;
  margin-bottom: 16px;
}

.metric-value {
  font-size: 36px;
  font-weight: 800;
  color: hsl(var(--primary));
  margin-bottom: 8px;
}

.metric-label {
  font-size: 18px;
  font-weight: 600;
  margin-bottom: 8px;
}

.metric-description {
  font-size: 14px;
  color: hsl(var(--muted-foreground));
}

/* CTA Section */
.cta-section {
  padding: 120px 0;
  position: relative;
  text-align: center;
}

.cta-background {
  position: absolute;
  inset: 0;
  overflow: hidden;
}

.cta-orbs {
  position: absolute;
  inset: 0;
}

.cta-orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(100px);
  opacity: 0.3;
}

.cta-orb-1 {
  width: 300px;
  height: 300px;
  background: hsl(var(--primary));
  top: 20%;
  left: 20%;
}

.cta-orb-2 {
  width: 200px;
  height: 200px;
  background: hsl(var(--accent));
  bottom: 20%;
  right: 20%;
}

.cta-content {
  position: relative;
  z-index: 10;
  max-width: 800px;
  margin: 0 auto;
}

.cta-title {
  font-size: 48px;
  font-weight: 700;
  margin-bottom: 20px;
  line-height: 1.2;
}

.cta-description {
  font-size: 20px;
  color: hsl(var(--muted-foreground));
  margin-bottom: 40px;
  line-height: 1.6;
}

.cta-actions {
  display: flex;
  justify-content: center;
  gap: 20px;
}

.cta-primary {
  background: hsl(var(--primary));
  color: hsl(var(--primary-foreground));
  padding: 16px 32px;
  border-radius: 50px;
  font-size: 18px;
  font-weight: 600;
  border: none;
  position: relative;
  overflow: hidden;
  transition: all 0.3s ease;
}

.cta-primary:hover {
  transform: translateY(-3px);
  box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
}

.btn-shine {
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
  transition: left 0.6s;
}

.cta-primary:hover .btn-shine {
  left: 100%;
}

.cta-secondary {
  background: transparent;
  color: hsl(var(--foreground));
  border: 2px solid hsl(var(--border));
  padding: 14px 30px;
  border-radius: 50px;
  font-size: 18px;
  font-weight: 600;
  transition: all 0.3s ease;
}

.cta-secondary:hover {
  border-color: hsl(var(--primary));
  color: hsl(var(--primary));
}

/* Footer */
.creative-footer {
  background: linear-gradient(135deg, rgba(0,0,0,0.05), transparent);
  border-top: 1px solid hsl(var(--border));
  padding: 60px 0 20px;
}

.footer-content {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 24px;
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 1fr;
  gap: 60px;
}

.footer-brand {
  max-width: 300px;
}

.footer-logo {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
}

.footer-logo span {
  font-size: 20px;
  font-weight: 700;
}

.footer-brand p {
  color: hsl(var(--muted-foreground));
  margin-bottom: 24px;
}

.link-group h4 {
  font-size: 16px;
  font-weight: 600;
  margin-bottom: 20px;
}

.link-group a {
  display: block;
  color: hsl(var(--muted-foreground));
  text-decoration: none;
  margin-bottom: 12px;
  transition: color 0.3s ease;
}

.link-group a:hover {
  color: hsl(var(--foreground));
}

.footer-bottom {
  max-width: 1200px;
  margin: 40px auto 0;
  padding: 20px 24px 0;
  border-top: 1px solid hsl(var(--border));
  display: flex;
  justify-content: between;
  align-items: center;
}

.social-links {
  display: flex;
  gap: 12px;
}

.social-link {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.05);
  display: flex;
  align-items: center;
  justify-content: center;
  color: hsl(var(--muted-foreground));
  transition: all 0.3s ease;
}

.social-link:hover {
  background: hsl(var(--primary));
  color: hsl(var(--primary-foreground));
  transform: translateY(-2px);
}

/* Animations */
@keyframes float {
  0%, 100% { transform: translateY(0px) rotate(0deg); }
  50% { transform: translateY(-20px) rotate(5deg); }
}

@keyframes particleFloat {
  0%, 100% { transform: translateY(0px); opacity: 0.3; }
  50% { transform: translateY(-30px); opacity: 1; }
}

@keyframes morphText {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.05); }
}

@keyframes gradientShift {
  0% { background-position: 0% 50%; }
  50% { background-position: 100% 50%; }
  100% { background-position: 0% 50%; }
}

@keyframes progressMove {
  0% { width: 35%; }
  50% { width: 65%; }
  100% { width: 35%; }
}

@keyframes waveAnimation {
  0% { transform: scaleY(0.3); opacity: 0.5; }
  100% { transform: scaleY(1); opacity: 1; }
}

@keyframes floatCard {
  0%, 100% { transform: translateY(0px); }
  50% { transform: translateY(-10px); }
}

@keyframes pulse {
  0%, 100% { opacity: 0.8; }
  50% { opacity: 1; }
}

@keyframes drawPath {
  to { stroke-dashoffset: 0; }
}

.animate-float {
  animation: float 3s ease-in-out infinite;
}

/* Magnetic Elements */
.magnetic-element {
  transition: transform 0.3s ease;
}

/* Responsive Design */
@media (max-width: 768px) {
  .hero-content {
    grid-template-columns: 1fr;
    gap: 40px;
    text-align: center;
  }
  
  .split-content {
    grid-template-columns: 1fr;
    gap: 40px;
  }
  
  .bento-grid {
    grid-template-columns: 1fr;
    grid-template-rows: auto;
  }
  
  .large-card,
  .medium-card,
  .small-card {
    grid-column: span 1;
    grid-row: span 1;
  }
  
  .workflow-container {
    grid-template-columns: 1fr;
    gap: 40px;
  }
  
  .footer-content {
    grid-template-columns: 1fr;
    gap: 40px;
    text-align: center;
  }
  
  .nav-links {
    display: none;
  }
}
</style>