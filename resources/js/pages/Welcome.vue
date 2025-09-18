<template>
  <div>

    <Head title="MENA Devs">
      <link rel="preconnect" href="https://fonts.bunny.net" />
      <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
    </Head>

    <div class="relative min-h-screen overflow-x-hidden bg-background">
      <!-- Header without border -->
      <header class="sticky top-0 z-40 w-full bg-background/80 backdrop-blur-md">
          <div class="container mx-auto flex h-16 items-center justify-between px-4 sm:px-6">
            <div 
              class="flex items-center gap-3 animate-fadeInUp"
              :style="{ animationDelay: '0ms' }"
            >
              <div class="flex items-center">
                <AppLogo />
              </div>
            </div>
            
            <nav 
              class="flex items-center gap-4 animate-fadeInUp"
              :style="{ animationDelay: '300ms' }"
            >
              <div class="hidden gap-1 md:flex">
                <Button variant="ghost" size="sm">
                  <a href="#features">Features</a>
                </Button>
                <Button variant="ghost" size="sm">
                  <a href="#how-it-works">How It Works</a>
                </Button>
                <Button variant="ghost" size="sm">
                  <a href="#annotation-types">Types</a>
                </Button>
                <Button variant="ghost" size="sm">
                  <a href="#integrations">Integrations</a>
                </Button>
              </div>
              
              <Button v-if="$page.props.auth.user" variant="default">
                <Link :href="route('dashboard')">
                  Dashboard
                </Link>
              </Button>
              <template v-else>
                <Button variant="ghost">
                  <Link :href="route('login')">
                    Log in
                  </Link>
                </Button>
                <Button variant="default">
                  <Link :href="route('register')">
                    Register
                  </Link>
                </Button>
              </template>
</nav>
</div>
</header>

      <!-- Hero Section with Unicorn Studio Background -->
      <section class="relative flex min-h-[90vh] items-center overflow-hidden">
        <!-- Unicorn Studio Background -->
        <!-- <div class="absolute inset-0 z-0 w-full h-full overflow-hidden">
            <div data-us-project="Lp0iYqmMNjZk4wXHejyG" style="width: 100%; height: 100%; position: absolute;"></div>
          </div> -->

        <div class="container relative z-10 mx-auto px-4 py-16 sm:px-6 sm:py-24">
          <div class="flex flex-col items-start justify-center text-left">
            <!-- Modern Badge -->
            <div class="mb-8 text-left animate-fadeInUp" :style="{ animationDelay: '0ms' }">
              <Badge variant="outline" class="px-4 py-2 text-sm backdrop-blur-sm border-primary/20">
                <span class="mr-2 inline-block h-2 w-2 rounded-full bg-primary animate-pulse"></span>
                Intelligent Data Annotation Platform
              </Badge>
            </div>

            <!-- Main heading with animated gradient -->
            <h1
              class="max-w-4xl text-left text-4xl font-bold tracking-tight sm:text-5xl md:text-6xl lg:text-7xl animate-fadeInUp"
              :style="{ animationDelay: '200ms' }">
              Accelerate AI with
              <span class="relative inline-block">
                <span
                  class="absolute inset-0 bg-gradient-to-r from-primary via-primary/80 to-primary bg-clip-text blur-sm"></span>
                <span
                  class="relative bg-gradient-to-r from-primary via-primary/80 to-primary bg-clip-text text-transparent">Smart
                  Labeling & Annotation</span>
              </span>
            </h1>
            <div class="h-[140px] sm:h-[190px] md:h-[260px] lg:h-[344px]"></div>
            <p class="mb-10 max-w-2xl text-left text-lg text-muted-foreground animate-fadeInUp"
              :style="{ animationDelay: '400ms' }">
              Create high-quality training datasets faster with our comprehensive annotation platform designed for text,
              image, and multimodal AI projects.
            </p>

            <!-- Technology badges -->
            <div class="mt-10 flex flex-wrap justify-start gap-2 animate-fadeInUp"
              :style="{ animationDelay: '1200ms' }">
              <Badge v-for="(tech, index) in ['Text Analysis', 'Computer Vision', 'NLP', 'Active Learning', 'REST API']"
                :key="index" variant="secondary" class="bg-card/40 backdrop-blur-sm">
                {{ tech }}
              </Badge>
            </div>
          </div>
        </div>
      </section>

      <!-- Features Section -->
      <section id="features" class="bg-muted/40 py-20">
        <div class="container mx-auto px-4 sm:px-6">
          <div class="mb-12 text-center">
            <Badge variant="outline" class="mb-4">Features</Badge>
            <h2 class="mb-4 text-3xl font-bold tracking-tight sm:text-4xl">Comprehensive Annotation Tools</h2>
            <p class="mx-auto max-w-2xl text-muted-foreground">
              Everything you need to create high-quality training datasets for your machine learning projects.
            </p>
          </div>

          <div class="grid grid-cols-1 gap-8 md:grid-cols-3">
            <Card v-for="(feature, index) in features" :key="index" class="border-0 bg-card/50">
              <CardHeader>
                <div class="mb-4" v-html="feature.icon"></div>
                <CardTitle>{{ feature.title }}</CardTitle>
              </CardHeader>
              <CardContent>
                <p class="text-muted-foreground">{{ feature.description }}</p>
              </CardContent>
            </Card>
          </div>
        </div>
      </section>

      <!-- Annotation Types Section -->
      <section id="annotation-types" class="py-20">
        <div class="container mx-auto px-4 sm:px-6">
          <div class="mb-12 text-center">
            <Badge variant="outline" class="mb-4">Annotation Types</Badge>
            <h2 class="mb-4 text-3xl font-bold tracking-tight sm:text-4xl">Support for Every Data Type</h2>
            <p class="mx-auto max-w-2xl text-muted-foreground">
              From simple classification to complex structured annotations across multiple data modalities.
            </p>
          </div>

          <div class="grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-4">
            <Card v-for="(type, index) in annotationTypes" :key="index"
              class="border bg-card group hover:shadow-lg transition-shadow">
              <CardHeader class="pb-3">
                <div
                  class="mb-3 flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10 group-hover:bg-primary/20 transition-colors">
                  <component :is="type.icon" class="h-8 w-8 text-primary" />
                </div>
                <CardTitle class="text-lg">{{ type.title }}</CardTitle>
              </CardHeader>
              <CardContent>
                <p class="text-sm text-muted-foreground mb-3">{{ type.description }}</p>
                <div class="flex flex-wrap gap-1">
                  <Badge v-for="(example, i) in type.examples" :key="i" variant="secondary" class="text-xs">
                    {{ example }}
                  </Badge>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      </section>

      <!-- How It Works Section - Modern Visual Design -->
      <section id="how-it-works" class="py-24 overflow-hidden bg-gradient-to-b from-background to-muted/30">
        <div class="container mx-auto px-4 sm:px-6">
          <div class="mb-16 text-center">
            <div class="animate-fadeInUp" :style="{ animationDelay: '0ms' }">
              <Badge variant="outline" class="mb-4 px-3 py-1">Process</Badge>
              <h2 class="mb-4 text-3xl font-bold tracking-tight sm:text-4xl md:text-5xl">
                Streamlined <span class="text-primary">Annotation</span> Workflow
              </h2>
              <p class="mx-auto max-w-2xl text-muted-foreground">
                From data upload to model-ready datasets in a few simple steps
              </p>
            </div>
          </div>

          <!-- Modern Hexagon Flow -->
          <div class="relative mx-auto max-w-5xl">
            <!-- Connecting lines -->
            <svg class="absolute inset-0 h-full w-full" style="z-index: 0;">
              <defs>
                <linearGradient id="lineGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                  <stop offset="0%" stop-color="var(--primary)" stop-opacity="0.1" />
                  <stop offset="50%" stop-color="var(--primary)" stop-opacity="0.5" />
                  <stop offset="100%" stop-color="var(--primary)" stop-opacity="0.1" />
                </linearGradient>
              </defs>
              <path d="M150,100 L350,180 L550,100 L750,180 L950,100" stroke="url(#lineGradient)" stroke-width="3"
                stroke-linecap="round" fill="none" class="animate-drawPath" />
            </svg>

            <!-- Process steps in hexagon cards -->
            <div class="relative z-10 grid grid-cols-1 gap-12 md:grid-cols-3 lg:grid-cols-5">
              <div v-for="(step, index) in workflowSteps" :key="index"
                class="group flex flex-col items-center animate-fadeInUp"
                :style="{ animationDelay: `${step.delay}ms` }">
                <!-- Hexagon shape with gradient border -->
                <div class="relative mb-4">
                  <!-- Hexagon background glow -->
                  <div
                    class="absolute -inset-3 bg-gradient-to-r from-primary/20 to-primary/5 blur-xl rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-700">
                  </div>

                  <!-- Hexagon with icon -->
                  <div
                    class="relative h-24 w-24 flex items-center justify-center bg-card border border-primary/30 rounded-xl shadow-lg transform transition-transform duration-300 group-hover:scale-110 group-hover:border-primary/70 group-hover:shadow-primary/20">
                    <div
                      class="absolute -top-3 -right-3 flex h-8 w-8 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground">
                      {{ step.step }}
                    </div>
                    <div class="text-primary">
                      <component :is="step.icon" class="h-8 w-8" />
                    </div>
                  </div>
                </div>

                <h3 class="mb-2 text-center text-lg font-semibold">{{ step.title }}</h3>
                <p class="text-center text-sm text-muted-foreground max-w-[180px]">{{ step.description }}</p>
              </div>
            </div>

            <!-- Interactive explanation -->
            <div class="mt-16 flex justify-center animate-fadeInUp" :style="{ animationDelay: '1000ms' }">
              <Card class="max-w-2xl border border-primary/20 bg-card/50 backdrop-blur-sm">
                <CardHeader>
                  <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10">
                      <Tag class="h-5 w-5 text-primary" />
                    </div>
                    <CardTitle>Why Quality Annotations Matter</CardTitle>
                  </div>
                </CardHeader>
                <CardContent>
                  <p class="text-muted-foreground">
                    High-quality annotations are the foundation of successful AI models. Our platform ensures
                    consistency,
                    accuracy, and efficiency in your labeling process through smart tools, collaborative workflows,
                    and comprehensive quality control measures that scale with your team and projects.
                  </p>
                </CardContent>
                <CardFooter class="flex justify-end">
                  <Button variant="ghost" size="sm" class="gap-1">
                    Learn more about our quality process
                    <ChevronRight class="h-4 w-4" />
                  </Button>
                </CardFooter>
              </Card>
            </div>
          </div>
        </div>
      </section>

      <!-- Integrations Section -->
      <section id="integrations" class="py-20">
        <div class="container mx-auto px-4 sm:px-6">
          <div class="mb-12 text-center">
            <Badge variant="outline" class="mb-4">Integrations</Badge>
            <h2 class="mb-4 text-3xl font-bold tracking-tight sm:text-4xl">Seamless Integration with Your Workflow</h2>
            <p class="mx-auto max-w-2xl text-muted-foreground">
              Connect our annotation platform with your existing ML pipeline and tools
            </p>
          </div>

          <div class="grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-4">
            <Card v-for="(integration, index) in integrations" :key="index" class="border bg-card">
              <CardHeader class="pb-2">
                <div class="mb-2">
                  <component :is="integration.icon" class="h-10 w-10 text-primary" />
                </div>
                <CardTitle>{{ integration.title }}</CardTitle>
              </CardHeader>
              <CardContent>
                <p class="text-sm text-muted-foreground">{{ integration.description }}</p>
              </CardContent>
              <CardFooter class="pt-0">
                <Button variant="ghost" size="sm" class="gap-1 p-0 text-primary">
                  Learn more
                  <ChevronRight class="h-4 w-4" />
                </Button>
              </CardFooter>
            </Card>
          </div>

          <div class="mt-16 flex justify-center">
            <Card class="border-0 bg-muted/50 max-w-3xl">
              <CardContent class="p-6">
                <div class="flex flex-col items-center justify-between gap-6 text-center md:flex-row md:text-left">
                  <div>
                    <h3 class="mb-2 text-xl font-semibold">Ready to integrate with your ML pipeline?</h3>
                    <p class="text-muted-foreground">Our developer documentation provides everything you need to get
                      started.
                    </p>
                  </div>
                  <Button class="whitespace-nowrap">View Documentation</Button>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      </section>

      <!-- CTA Section -->
      <section class="bg-primary text-primary-foreground py-16">
        <div class="container mx-auto px-4 sm:px-6">
          <div class="flex flex-col items-center justify-between gap-8 text-center md:flex-row md:text-left">
            <div>
              <h2 class="mb-2 text-3xl font-bold">Ready to accelerate your AI development?</h2>
              <p class="text-primary-foreground/80">Start creating high-quality datasets today.</p>
            </div>
            <div class="flex gap-4">
              <Button variant="secondary" size="lg">
                <Link :href="route('register')">
                Start Annotating
                </Link>
              </Button>
              <Button variant="outline" size="lg"
                class="border-primary-foreground/20 bg-transparent text-primary-foreground hover:bg-primary-foreground/10">
                <a href="#features">
                  Learn More
                </a>
              </Button>
            </div>
          </div>
        </div>
      </section>

      <!-- Footer -->
      <footer class="border-t py-12">
        <div class="container mx-auto px-4 sm:px-6">
          <div class="grid grid-cols-1 gap-8 md:grid-cols-4">
            <div>
              <div class="flex items-center">
                <AppLogo />
              </div>
              <p class="mt-4 text-sm text-muted-foreground">
                Accelerating AI development through intelligent data annotation and collaborative workflows.
              </p>
              <div class="mt-4 flex gap-4">
                <a href="#" class="text-muted-foreground hover:text-foreground">
                  <Github class="h-5 w-5" />
                </a>
                <!-- Add more social icons as needed -->
              </div>
            </div>

            <div>
              <h3 class="mb-4 text-sm font-semibold">Product</h3>
              <ul class="space-y-2 text-sm">
                <li><a href="#features" class="text-muted-foreground hover:text-foreground">Features</a></li>
                <li><a href="#annotation-types" class="text-muted-foreground hover:text-foreground">Annotation Types</a>
                </li>
                <li><a href="#integrations" class="text-muted-foreground hover:text-foreground">Integrations</a></li>
                <li><a href="#" class="text-muted-foreground hover:text-foreground">API</a></li>
                <li><a href="#" class="text-muted-foreground hover:text-foreground">Documentation</a></li>
              </ul>
            </div>

            <div>
              <h3 class="mb-4 text-sm font-semibold">Company</h3>
              <ul class="space-y-2 text-sm">
                <li><a href="#" class="text-muted-foreground hover:text-foreground">About</a></li>
                <li><a href="#" class="text-muted-foreground hover:text-foreground">Blog</a></li>
                <li><a href="#" class="text-muted-foreground hover:text-foreground">Careers</a></li>
                <li><a href="#" class="text-muted-foreground hover:text-foreground">Contact</a></li>
              </ul>
            </div>

            <div>
              <h3 class="mb-4 text-sm font-semibold">Legal</h3>
              <ul class="space-y-2 text-sm">
                <li><a href="#" class="text-muted-foreground hover:text-foreground">Privacy Policy</a></li>
                <li><a href="#" class="text-muted-foreground hover:text-foreground">Terms of Service</a></li>
                <li><a href="#" class="text-muted-foreground hover:text-foreground">Cookie Policy</a></li>
              </ul>
            </div>
          </div>

          <Separator class="my-8" />

          <div
            class="flex flex-col items-center justify-between gap-4 text-center text-sm text-muted-foreground md:flex-row md:text-left">
            <p>© {{ new Date().getFullYear() }} Annotation Platform. All rights reserved.</p>
            <div class="flex gap-4">
              <a href="#" class="hover:text-foreground">Privacy</a>
              <a href="#" class="hover:text-foreground">Terms</a>
              <a href="#" class="hover:text-foreground">Contact</a>
            </div>
          </div>
        </div>
      </footer>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { onMounted } from 'vue';

// Import shadcn/ui components
import Button from '../components/ui/button/Button.vue';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '../components/ui/card';
import { Badge } from '../components/ui/badge';
import Separator from '../components/ui/separator/Separator.vue';

// Import app components
import AppLogo from '../components/AppLogo.vue';

// Import icons (using lucide-vue-next or similar)
import {
  ChevronRight,
  Tag,
  Users,
  BarChart3,
  Zap,
  Github,
  Code,
  Terminal,
  Database,
  RefreshCw,
  FileText,
  Target,
  MousePointer
} from 'lucide-vue-next';

// Data
const features = [
  {
    icon: '<svg class="h-10 w-10 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>',
    title: "Multi-Modal Annotation",
    description: "Support for text, images, and audio annotation with intuitive tools and collaborative workflows for teams."
  },
  {
    icon: '<svg class="h-10 w-10 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
    title: "Smart Labeling",
    description: "AI-assisted annotation with active learning to reduce manual effort and improve annotation consistency and accuracy."
  },
  {
    icon: '<svg class="h-10 w-10 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>',
    title: "Quality Assurance",
    description: "Built-in quality control with inter-annotator agreement metrics, validation workflows, and detailed analytics."
  }
];

const annotationTypes = [
  {
    icon: FileText,
    title: "Text Annotation",
    description: "Named entity recognition, sentiment analysis, text classification, and span labeling",
    examples: ["NER", "Sentiment", "Classification"]
  },
  {
    icon: MousePointer,
    title: "Image Annotation",
    description: "Bounding boxes, polygons, semantic segmentation, and keypoint detection",
    examples: ["Bounding Box", "Segmentation", "Keypoints"]
  },
  {
    icon: Target,
    title: "Object Detection",
    description: "Multi-class object detection with precision annotation tools and validation",
    examples: ["Detection", "Tracking", "3D Boxes"]
  },
  {
    icon: Tag,
    title: "Custom Labels",
    description: "Define custom annotation schemas for domain-specific requirements",
    examples: ["Custom Fields", "Hierarchical", "Relationships"]
  }
];

const workflowSteps = [
  {
    step: "01",
    title: "Upload Data",
    description: "Import your datasets from various sources and formats",
    icon: Database,
    delay: 0
  },
  {
    step: "02",
    title: "Configure Project",
    description: "Set up annotation schemas and define labeling guidelines",
    icon: Code,
    delay: 200
  },
  {
    step: "03",
    title: "Annotate & Label",
    description: "Use intuitive tools to label data with team collaboration",
    icon: Tag,
    delay: 400
  },
  {
    step: "04",
    title: "Quality Control",
    description: "Review annotations with built-in quality assurance",
    icon: Target,
    delay: 600
  },
  {
    step: "05",
    title: "Export Dataset",
    description: "Download ready-to-use datasets in your preferred format",
    icon: Zap,
    delay: 800
  }
];

const integrations = [
  {
    icon: Code,
    title: "Python SDK",
    description: "Comprehensive Python library for programmatic access to annotation projects and datasets"
  },
  {
    icon: Terminal,
    title: "CLI Tools",
    description: "Command-line interface for batch operations, project management, and automation"
  },
  {
    icon: Database,
    title: "Data Connectors",
    description: "Direct integration with cloud storage, databases, and popular ML frameworks"
  },
  {
    icon: RefreshCw,
    title: "MLOps Integration",
    description: "Seamless workflow integration with MLflow, Kubeflow, and other MLOps platforms"
  }
];

onMounted(() => {
  // Add Unicorn Studio script directly to the page
  const script = document.createElement('script');
  script.innerHTML = `
      (function() {
        if (!window.UnicornStudio) {
          window.UnicornStudio = { isInitialized: false };
          var s = document.createElement('script');
          s.src = "https://cdn.jsdelivr.net/gh/hiunicornstudio/unicornstudio.js@v1.4.29/dist/unicornStudio.umd.js";
          s.onload = function() {
            setTimeout(function() {
              if (typeof UnicornStudio !== 'undefined' && typeof UnicornStudio.init === 'function') {
                UnicornStudio.init();
              }
            }, 500);
          };
          document.body.appendChild(s);
        }
      })();
    `;
  document.body.appendChild(script);
});
</script>

<style scoped>
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes drawPath {
  from {
    stroke-dasharray: 1000;
    stroke-dashoffset: 1000;
  }

  to {
    stroke-dasharray: 1000;
    stroke-dashoffset: 0;
  }
}

.animate-fadeInUp {
  animation: fadeInUp 0.6s ease-out forwards;
  opacity: 0;
}

.animate-drawPath {
  animation: drawPath 1.5s ease-in-out forwards;
}

.animate-pulse {
  animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {

  0%,
  100% {
    opacity: 1;
  }

  50% {
    opacity: .5;
  }
}
</style>