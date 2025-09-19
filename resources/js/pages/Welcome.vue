<template>
  <div>
    <Head title="MENADevsAI - AI Audio Annotation Platform">
      <link rel="preconnect" href="https://fonts.bunny.net" />
      <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|space-grotesk:400,700" rel="stylesheet" />
    </Head>

    <div class="relative min-h-screen overflow-x-hidden bg-background text-foreground">
      <!-- Animated Background -->
      <div class="fixed inset-0 -z-10 bg-gradient-to-br from-background via-background to-accent/5">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_50%_50%,rgba(120,119,198,0.1),transparent_50%)] animate-pulse-slow"></div>
        <div class="absolute top-0 -left-4 w-72 h-72 bg-primary/10 rounded-full mix-blend-multiply filter blur-xl animate-blob"></div>
        <div class="absolute top-0 -right-4 w-72 h-72 bg-accent/20 rounded-full mix-blend-multiply filter blur-xl animate-blob animation-delay-2000"></div>
        <div class="absolute -bottom-8 left-20 w-72 h-72 bg-secondary/30 rounded-full mix-blend-multiply filter blur-xl animate-blob animation-delay-4000"></div>
      </div>

      <!-- Header -->
      <header class="sticky top-0 z-40 w-full bg-background/80 backdrop-blur-md">
        <div class="container mx-auto flex h-16 items-center justify-between px-4 sm:px-6">
          <div class="flex items-center gap-3">
            <!-- <div class="w-8 h-8 bg-gradient-to-br from-primary to-accent rounded-lg flex items-center justify-center">
              <Mic class="w-4 h-4 text-primary-foreground" />
            </div> -->
            <span class="text-2xl font-bold font-space-grotesk text-primary"><span class="text-green-600">MENA</span>DevsAI</span>
          </div>

          <nav class="flex items-center gap-4">
            <div class="hidden gap-1 md:flex">
              <Button variant="ghost" size="sm">
                <a href="#features">Features</a>
              </Button>
              <Button variant="ghost" size="sm">
                <a href="#how-it-works">How It Works</a>
              </Button>
              <Button variant="ghost" size="sm">
                <a href="#testimonials">Reviews</a>
              </Button>
              <Button variant="ghost" size="sm">
                <a href="#pricing">Pricing</a>
              </Button>
              <Button variant="ghost" size="sm">
                <a href="#faq">FAQ</a>
              </Button>
            </div>

            <Button v-if="$page.props.auth.user" variant="default">
              <Link :href="route('dashboard')">Dashboard</Link>
            </Button>
            <template v-else>
              <Button variant="ghost">
                <Link :href="route('login')">Log in</Link>
              </Button>
              <Button variant="default">
                <Link :href="route('register')">Register</Link>
              </Button>
            </template>
          </nav>
        </div>
      </header>

      <!-- Hero Section -->
      <section class="relative  flex items-center lg:py-30 overflow-hidden">
        <div class="container relative z-10 mx-auto px-4 py-20 sm:px-6">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <!-- Left Content -->
            <div class="space-y-8 animate-fade-in-up">
              <Badge variant="outline" class="border-primary/20 text-primary px-6 py-2 text-sm font-medium rounded-full backdrop-blur-sm bg-background/50">
                <Sparkles class="w-4 h-4 mr-2" />
                Next-Gen Audio Intelligence
              </Badge>
              
              <div class="space-y-4">
                <h1 class="text-5xl lg:text-7xl font-bold font-space-grotesk tracking-tight">
                  <span class="bg-gradient-to-r from-primary via-foreground to-primary bg-clip-text text-transparent animate-gradient-x">
                    Transform Audio
                  </span>
                  <br />
                  <span class="relative">
                    With AI Precision
                    <div class="absolute -bottom-2 left-0 w-full h-1 bg-green-600 rounded-full animate-expand-width"></div>
                  </span>
                </h1>
                
                <p class="text-xl text-muted-foreground max-w-2xl leading-relaxed">
                  Revolutionary audio annotation platform where human expertise meets AI precision. 
                  Segment, annotate, and train models with unprecedented accuracy.
                </p>
              </div>

              <div class="flex flex-col sm:flex-row gap-4">
                <Button size="lg" class="group bg-green-600 text-primary-foreground hover:bg-primary/90 transition-all duration-300 shadow-lg hover:shadow-xl">
                  <a href="#demo" class="flex items-center gap-2">
                    Start Demo
                    <ChevronRight class="h-5 w-5 group-hover:translate-x-1 transition-transform" />
                  </a>
                </Button>
                <!-- <Button variant="outline" size="lg" class="border-border/50 hover:bg-accent/50 backdrop-blur-sm">
                  <a href="#demo" class="flex items-center gap-2">
                    Watch Demo <Play class="h-5 w-5" />
                  </a>
                </Button> -->
              </div>

              <!-- Stats -->
              <div class="grid grid-cols-3 gap-8 pt-8">
                <div class="text-center">
                  <div class="text-3xl font-bold text-primary">1M+</div>
                  <div class="text-sm text-muted-foreground">Audio Hours</div>
                </div>
                <div class="text-center">
                  <div class="text-3xl font-bold text-primary">99.9%</div>
                  <div class="text-sm text-muted-foreground">Accuracy</div>
                </div>
                <div class="text-center">
                  <div class="text-3xl font-bold text-primary">500+</div>
                  <div class="text-sm text-muted-foreground">AI Models</div>
                </div>
              </div>
            </div>

            <!-- Right Visual -->
            <div class="relative animate-fade-in-right">
              <div class="relative z-10">
                <div class="glassmorphism h-80 w-full rounded-2xl overflow-hidden p-6">
                  <div class="flex items-center justify-between mb-4">
                    <div class="text-sm font-medium text-foreground">Live Waveform Analysis</div>
                    <div class="flex items-center gap-2">
                      <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                      <span class="text-xs text-muted-foreground">Active</span>
                    </div>
                  </div>
                  <div class="waveform-container h-48 flex items-center justify-center gap-1">
                    <div v-for="i in 80" :key="i" 
                        class="wave-bar bg-gradient-to-t from-black via-green-600 to-accent"
                         :style="{ 
                           animationDelay: `${i * 0.02}s`,
                           height: `${Math.random() * 80 + 20}%`
                         }">
                    </div>
                  </div>
                  <div class="mt-4 flex items-center justify-between text-xs text-muted-foreground">
                    <span>0:00</span>
                    <span>Processing...</span>
                    <span>2:34</span>
                  </div>
                </div>
              </div>
              
              <!-- Floating Elements -->
              <div class="absolute -top-4 -right-4 w-30 h-30 bg-green-600/10 rounded-full blur-xl animate-float"></div>
              <div class="absolute -bottom-4 -left-4 w-32 h-32 bg-accent/20 rounded-full blur-xl animate-float animation-delay-2000"></div>
            </div>
          </div>
        </div>
      </section>
      <!-- How It Works -->
      <section id="how-it-works" class="py-24 bg-muted/30">
        <div class="container mx-auto px-4 sm:px-6">
          <div class="text-center mb-16">
            <Badge variant="outline" class="border-primary/20 text-primary mb-6">Simple Process</Badge>
            <h2 class="text-4xl font-bold font-space-grotesk mb-4">From upload to trained model</h2>
            <p class="text-xl text-muted-foreground max-w-3xl mx-auto">
              Our streamlined workflow ensures quality at every step
            </p>
          </div>

          <div class="relative">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-8">
              <div v-for="(step, index) in workflowSteps" :key="index" 
                   class="flex flex-col items-center text-center group">
                <div class="relative mb-6">
                  <div class="w-20 h-20 rounded-full bg-primary/10 border-2 border-primary/20 flex items-center justify-center group-hover:scale-110 transition-transform">
                    <component :is="step.icon" class="h-8 w-8 text-primary" />
                  </div>
                  <div class="absolute -top-2 -right-2 w-8 h-8 bg-primary text-primary-foreground rounded-full flex items-center justify-center text-sm font-bold">
                    {{ step.step }}
                  </div>
                </div>
                <h3 class="text-lg font-semibold font-space-grotesk mb-2">{{ step.title }}</h3>
                <p class="text-muted-foreground text-sm">{{ step.description }}</p>
              </div>
            </div>
            
            <!-- Connection Lines -->
            <svg class="absolute top-10 left-0 w-full h-20 -z-10 hidden md:block">
              <defs>
                <linearGradient id="lineGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                  <stop offset="0%" style="stop-color:var(--primary);stop-opacity:0.3" />
                  <stop offset="50%" style="stop-color:var(--primary);stop-opacity:0.8" />
                  <stop offset="100%" style="stop-color:var(--primary);stop-opacity:0.3" />
                </linearGradient>
              </defs>
              <path d="M80 40 Q 300 20 520 40 Q 740 60 960 40" stroke="url(#lineGradient)" stroke-width="2" fill="none" stroke-dasharray="8 4" class="animate-dash" />
            </svg>
          </div>
        </div>
      </section>
      <!-- Dashboard Preview -->
      <section id="demo" class="py-24 relative">
        <div class="container mx-auto px-4 sm:px-6">
          <div class="text-center mb-16">
            <Badge variant="outline" class="border-primary/20 text-primary mb-6">Live Preview</Badge>
            <h2 class="text-4xl font-bold font-space-grotesk mb-4">See <span class="text-green-600">MENA</span>DevsAI in action</h2>
            <p class="text-xl text-muted-foreground max-w-3xl mx-auto">
              Experience our intuitive interface and powerful annotation tools
            </p>
          </div>
          
          <div class="relative max-w-6xl mx-auto">
            <div class="glassmorphism rounded-2xl p-2 shadow-2xl">
              <div class="bg-background rounded-xl overflow-hidden border border-border/50">
                <!-- Fake Browser Bar -->
                <div class="flex items-center justify-between px-4 py-3 bg-muted/50 border-b border-border/50">
                  <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full bg-red-500"></div>
                    <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
                    <div class="w-3 h-3 rounded-full bg-green-500"></div>
                  </div>
                  <div class="text-sm text-muted-foreground">MENADevsAI.com/dashboard</div>
                  <div></div>
                </div>
                
                <!-- Interactive Demo Content -->
                <div class="p-6 space-y-6 bg-gradient-to-br from-background to-muted/20 min-h-[500px]">
                  <!-- Header -->
                  <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                      <div class="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center">
                        <Volume2 class="w-5 h-5 text-primary" />
                      </div>
                      <div>
                        <h3 class="font-semibold text-lg">Interactive Audio Segmentation</h3>
                        <p class="text-sm text-muted-foreground">Try dragging to create segments!</p>
                      </div>
                    </div>
                    <div class="text-sm text-muted-foreground">
                      {{ formatTime(demoCurrentTime) }} / {{ formatTime(demoDuration) }}
                    </div>
                  </div>

                  <!-- Label Selection Palette -->
                  <div class="space-y-3">
                    <div class="flex items-center gap-2">
                      <MousePointer2 class="w-4 h-4 text-muted-foreground" />
                      <span class="text-sm font-medium text-muted-foreground">Select a label, then drag on waveform:</span>
                      <span v-if="demoSelectedLabel" class="text-sm font-medium text-primary">{{ demoSelectedLabel.name }}</span>
                      <span v-else class="text-sm text-muted-foreground opacity-60">None selected</span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                      <button
                        v-for="label in demoLabels"
                        :key="label.id"
                        @click="selectDemoLabel(label)"
                        class="px-4 py-2 rounded-lg font-medium text-sm transition-all duration-200 hover:scale-105 border-2"
                        :class="demoSelectedLabel?.id === label.id 
                          ? 'ring-2 ring-primary ring-offset-2 scale-105 shadow-lg' 
                          : 'hover:shadow-md border-border'"
                        :style="{
                          backgroundColor: label.color + '20',
                          borderColor: demoSelectedLabel?.id === label.id ? 'var(--primary)' : label.color,
                          color: label.color
                        }"
                      >
                        <div class="flex items-center gap-2">
                          <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: label.color }"></div>
                          {{ label.name }}
                        </div>
                      </button>
                    </div>
                  </div>

                  <!-- Controls -->
                  <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                      <button
                        @click="toggleDemoPlayback"
                        class="flex items-center gap-2 px-4 py-2 bg-primary text-primary-foreground rounded-lg hover:bg-primary/90 transition-colors"
                      >
                        <component :is="demoIsPlaying ? Pause : Play" class="w-4 h-4" />
                        {{ demoIsPlaying ? 'Pause' : 'Play' }}
                      </button>
                    </div>
                    <div class="flex items-center gap-2">
                      <button @click="demoZoomOut" class="p-2 hover:bg-muted rounded-lg">
                        <ZoomOut class="w-4 h-4" />
                      </button>
                      <span class="text-xs text-muted-foreground">{{ Math.round(demoZoom) }}x</span>
                      <button @click="demoZoomIn" class="p-2 hover:bg-muted rounded-lg">
                        <ZoomIn class="w-4 h-4" />
                      </button>
                    </div>
                  </div>

                  <!-- Waveform Container -->
                  <div class="relative">
                    <div 
                      ref="demoWaveformRef"
                      class="relative h-32 bg-muted/30 rounded-lg border border-border/50 overflow-hidden cursor-crosshair"
                      @mousedown="handleDemoMouseDown"
                      @mousemove="handleDemoMouseMove"
                      @mouseup="handleDemoMouseUp"
                      @mouseleave="handleDemoMouseUp"
                    >
                      <!-- Waveform Bars -->
                      <div class="flex items-end h-full px-2 gap-px">
                        <div 
                          v-for="(height, i) in demoWaveformData" 
                          :key="i"
                          class="flex-1 bg-muted-foreground/40 transition-all duration-100"
                          :style="{ height: height + '%' }"
                        ></div>
                      </div>

                      <!-- All Segments (Fully Editable) -->
                      <template v-for="segment in allDemoSegments" :key="`segment-${segment.id}-${segment.preset ? 'preset' : 'new'}`">
                        <div
                          class="absolute top-2 h-28 border-2 rounded cursor-pointer hover:shadow-lg transition-all group"
                          :style="getDemoSegmentStyle(segment)"
                          @click="playDemoSegment(segment)"
                        >
                          <!-- Resize Handles -->
                          <div 
                            class="absolute left-0 top-0 w-3 h-full bg-gray-800 opacity-0 group-hover:opacity-80 cursor-ew-resize rounded-l flex items-center justify-center z-30"
                            @mousedown="startDemoResize(segment, 'start', $event)"
                            title="Drag to resize start"
                          >
                            <div class="w-0.5 h-8 bg-white rounded"></div>
                          </div>
                          <div 
                            class="absolute right-0 top-0 w-3 h-full bg-gray-800 opacity-0 group-hover:opacity-80 cursor-ew-resize rounded-r flex items-center justify-center z-30"
                            @mousedown="startDemoResize(segment, 'end', $event)"
                            title="Drag to resize end"
                          >
                            <div class="w-0.5 h-8 bg-white rounded"></div>
                          </div>

                          <!-- Segment Info -->
                          <div class="absolute top-1 left-2 right-2 text-xs font-bold text-white bg-black/60 px-2 py-1 rounded flex items-center justify-between">
                            <div class="flex items-center gap-1">
                              <div class="w-2 h-2 rounded-full" :style="{ backgroundColor: segment.label.color }"></div>
                              {{ segment.label.name }}
                              <span v-if="segment.preset" class="opacity-75 text-[10px]">(original)</span>
                            </div>
                            <div class="text-[10px]">
                              {{ (segment.end - segment.start).toFixed(1) }}s
                            </div>
                          </div>

                          <!-- Control Buttons -->
                          <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 opacity-0 group-hover:opacity-100 transition-opacity flex items-center gap-1 z-20">
                            <button
                              @click.stop="playDemoSegment(segment)"
                              class="w-6 h-6 bg-white/90 text-gray-800 rounded-full hover:bg-white flex items-center justify-center shadow-md"
                              title="Play segment"
                            >
                              <Play class="h-3 w-3 ml-0.5" />
                            </button>
                          </div>

                          <!-- Action Buttons -->
                          <div class="absolute -top-2 -right-2 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity z-30">
                        
                            <button 
                              @click.stop="removeDemoSegment(segment.id, segment.preset)"
                              class="w-5 h-5 bg-red-500 text-white rounded-full hover:bg-red-600 flex items-center justify-center shadow-lg"
                              title="Delete segment"
                            >
                              <X class="h-3 w-3" />
                            </button>
                          </div>

                          <!-- Time Display -->
                          <div class="absolute bottom-1 left-2 right-2 text-[10px] text-white bg-black/50 px-1 py-0.5 rounded flex justify-between">
                            <span>{{ formatTime(segment.start) }}</span>
                            <span>{{ formatTime(segment.end) }}</span>
                          </div>
                        </div>
                      </template>

                      <!-- Drag Selection -->
                      <div 
                        v-if="demoIsDragging && demoSelectedLabel"
                        class="absolute top-2 h-28 border-2 border-primary bg-primary/20 rounded"
                        :style="{
                          left: Math.min(demoDragStart, demoDragCurrent) / demoDuration * 100 + '%',
                          width: Math.abs(demoDragCurrent - demoDragStart) / demoDuration * 100 + '%'
                        }"
                      ></div>

                      <!-- Playhead -->
                      <div 
                        class="absolute top-0 bottom-0 w-0.5 bg-primary z-20"
                        :style="{ left: getDemoPlayheadPosition() + '%' }"
                      >
                        <div class="absolute -top-1 -left-1 w-3 h-3 bg-primary rounded-full"></div>
                      </div>
                    </div>
                    
                    <!-- Instructions -->
                    <div class="mt-3 text-xs text-muted-foreground text-center">
                      <span class="inline-flex items-center gap-1">
                        <Edit3 class="w-3 h-3" />
                        Select a label above, then drag on the waveform to create segments
                      </span>
                      <span class="mx-2">•</span>
                      <span>Drag segment edges to resize</span>
                      <span class="mx-2">•</span>
                      <span>Use +/- buttons to expand/minimize</span>
                      <span class="mx-2">•</span>
                      <span>All segments can be edited or deleted</span>
                    </div>
                  </div>

                  <!-- Stats -->
                  <div class="grid grid-cols-3 gap-4 text-center">
                    <div class="p-3 bg-muted/30 rounded-lg">
                      <div class="text-lg font-bold text-primary">{{ allDemoSegments.length }}</div>
                      <div class="text-xs text-muted-foreground">Total Segments</div>
                    </div>
                    <div class="p-3 bg-muted/30 rounded-lg">
                      <div class="text-lg font-bold text-primary">{{ demoNewSegments.length }}</div>
                      <div class="text-xs text-muted-foreground">User Created</div>
                    </div>
                    <div class="p-3 bg-muted/30 rounded-lg">
                      <div class="text-lg font-bold text-primary">{{ demoLabels.length }}</div>
                      <div class="text-xs text-muted-foreground">Label Types</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Floating UI Elements -->
            <div class="absolute -top-4 -right-4 glassmorphism rounded-lg p-3 animate-float">
              <div class="flex items-center gap-2 text-sm">
                <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                <span class="text-foreground">Live Demo</span>
              </div>
            </div>
            
            <div class="absolute -bottom-4 -left-4 glassmorphism rounded-lg p-3 animate-float animation-delay-2000">
              <div class="text-sm text-foreground">
                <div class="font-semibold">Try It!</div>
                <div class="text-muted-foreground">Fully Interactive</div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Features Section -->
      <section id="features" class="py-24 relative">
        <div class="container mx-auto px-4 sm:px-6">
          <div class="text-center mb-16">
            <Badge variant="outline" class="border-primary/20 text-primary mb-6">Powerful Features</Badge>
            <h2 class="text-4xl font-bold font-space-grotesk mb-4">Everything you need for audio AI</h2>
            <p class="text-xl text-muted-foreground max-w-3xl mx-auto">
              Advanced tools and workflows designed for precision, scale, and collaboration
            </p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <Card v-for="(feature, index) in features" :key="index" 
                  class="glassmorphism border-border/50 hover:border-primary/30 transition-all duration-500 hover:scale-105 group">
              <CardHeader class="pb-4">
                <div class="w-12 h-12 rounded-lg bg-primary/10 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                  <component :is="feature.icon" class="h-6 w-6 text-primary" />
                </div>
                <CardTitle class="text-xl font-space-grotesk text-foreground">{{ feature.title }}</CardTitle>
              </CardHeader>
              <CardContent>
                <p class="text-muted-foreground leading-relaxed">{{ feature.description }}</p>
              </CardContent>
            </Card>
          </div>
        </div>
      </section>





      <!-- Testimonials -->
      <section id="testimonials" class="py-24 bg-muted/30">
        <div class="container mx-auto px-4 sm:px-6">
          <div class="text-center mb-16">
            <Badge variant="outline" class="border-primary/20 text-primary mb-6">Customer Stories</Badge>
            <h2 class="text-4xl font-bold font-space-grotesk mb-4">What our users say</h2>
            <p class="text-xl text-muted-foreground max-w-3xl mx-auto">
              Hear from teams who've transformed their audio workflows
            </p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <Card v-for="(testimonial, index) in testimonials" :key="index"
                  class="glassmorphism border-border/50 p-6">
              <div class="flex items-center gap-1 mb-4">
                <Star v-for="i in 5" :key="i" class="w-4 h-4 fill-primary text-primary" />
              </div>
              <blockquote class="text-foreground mb-6 italic">
                "{{ testimonial.quote }}"
              </blockquote>
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center">
                  <User class="w-5 h-5 text-primary" />
                </div>
                <div>
                  <div class="font-semibold text-foreground">{{ testimonial.name }}</div>
                  <div class="text-sm text-muted-foreground">{{ testimonial.role }}</div>
                </div>
              </div>
            </Card>
          </div>
        </div>
      </section>


      <!-- FAQ -->
      <section id="faq" class="py-24 bg-muted/30">
        <div class="container mx-auto px-4 sm:px-6">
          <div class="text-center mb-16">
            <Badge variant="outline" class="border-primary/20 text-primary mb-6">FAQ</Badge>
            <h2 class="text-4xl font-bold font-space-grotesk mb-4">Frequently asked questions</h2>
            <p class="text-xl text-muted-foreground max-w-3xl mx-auto">
              Everything you need to know about <span class="text-green-600">MENA</span>DevsAI
            </p>
          </div>

          <div class="max-w-3xl mx-auto space-y-4">
            <Card v-for="(faq, index) in faqs" :key="index" class="border-border/50">
              <CardHeader class="cursor-pointer" @click="faq.open = !faq.open">
                <div class="flex items-center justify-between">
                  <h3 class="text-lg font-semibold">{{ faq.question }}</h3>
                  <ChevronRight :class="`w-5 h-5 transition-transform ${faq.open ? 'rotate-90' : ''}`" />
                </div>
              </CardHeader>
              <CardContent v-if="faq.open" class="pt-0">
                <p class="text-muted-foreground">{{ faq.answer }}</p>
              </CardContent>
            </Card>
          </div>
        </div>
      </section>

      <!-- CTA -->
      <section class="py-24 relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-r from-primary/10 via-accent/10 to-primary/10"></div>
        <div class="container relative mx-auto px-4 sm:px-6">
          <div class="text-center max-w-3xl mx-auto">
            <h2 class="text-5xl font-bold font-space-grotesk mb-6">
              Ready to transform your audio workflow?
            </h2>
            <p class="text-xl text-muted-foreground mb-8">
              Join thousands of teams already using <span class="text-green-600">MENA</span>DevsAI to build better AI models
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
              <Button size="lg" class="bg-primary text-primary-foreground hover:bg-primary/90 shadow-lg">
                <Link :href="route('register')" class="flex items-center gap-2">
                  Start Free Trial <ArrowRight class="w-5 h-5" />
                </Link>
              </Button>
              <Button variant="outline" size="lg" class="border-border/50">
                Schedule Demo
              </Button>
            </div>
          </div>
        </div>
      </section>

      <!-- Footer -->
      <footer class="bg-background/50 backdrop-blur-xl border-t border-border/50 py-16">
        <div class="container mx-auto px-4 sm:px-6">
          <div class="grid grid-cols-1 md:grid-cols-5 gap-8">
            <div class="md:col-span-2">
              <div class="flex items-center gap-3 mb-4">
                <!-- <div class="w-8 h-8 bg-gradient-to-br from-primary to-accent rounded-lg flex items-center justify-center">
                  <Mic class="w-4 h-4 text-primary-foreground" />
                </div> -->
                <span class="text-xl font-bold font-space-grotesk text-primary"><span class="text-green-600">MENA</span>DevsAI</span>
              </div>
              <p class="text-muted-foreground mb-6 max-w-sm">
                The future of audio annotation and AI training. Transform your workflow with precision tools and collaborative features.
              </p>
              <div class="flex gap-4">
                <Button variant="ghost" size="sm" class="text-muted-foreground hover:text-primary">
                  <Github class="w-5 h-5" />
                </Button>
                <Button variant="ghost" size="sm" class="text-muted-foreground hover:text-primary">
                  <Twitter class="w-5 h-5" />
                </Button>
                <Button variant="ghost" size="sm" class="text-muted-foreground hover:text-primary">
                  <Linkedin class="w-5 h-5" />
                </Button>
              </div>
            </div>
            
            <div>
              <h3 class="font-semibold mb-4">Platform</h3>
              <ul class="space-y-3 text-sm text-muted-foreground">
                <li><a href="#features" class="hover:text-primary transition-colors">Features</a></li>
                <li><a href="#pricing" class="hover:text-primary transition-colors">Pricing</a></li>
                <li><a href="#" class="hover:text-primary transition-colors">Documentation</a></li>
                <li><a href="#" class="hover:text-primary transition-colors">API Reference</a></li>
              </ul>
            </div>
            
            <div>
              <h3 class="font-semibold mb-4">Company</h3>
              <ul class="space-y-3 text-sm text-muted-foreground">
                <li><a href="#" class="hover:text-primary transition-colors">About</a></li>
                <li><a href="#" class="hover:text-primary transition-colors">Blog</a></li>
                <li><a href="#" class="hover:text-primary transition-colors">Careers</a></li>
                <li><a href="#" class="hover:text-primary transition-colors">Contact</a></li>
              </ul>
            </div>
            
            <div>
              <h3 class="font-semibold mb-4">Legal</h3>
              <ul class="space-y-3 text-sm text-muted-foreground">
                <li><a href="#" class="hover:text-primary transition-colors">Privacy Policy</a></li>
                <li><a href="#" class="hover:text-primary transition-colors">Terms of Service</a></li>
                <li><a href="#" class="hover:text-primary transition-colors">Cookie Policy</a></li>
              </ul>
            </div>
          </div>
          
          <Separator class="my-8 border-border/50" />
          <div class="flex flex-col md:flex-row items-center justify-between">
            <p class="text-sm text-muted-foreground">
              © {{ new Date().getFullYear() }} <span class="text-green-600">MENA</span>DevsAI. All rights reserved.
            </p>
            <div class="flex items-center gap-4 mt-4 md:mt-0">
              <Button variant="ghost" size="sm" class="text-muted-foreground hover:text-primary">
                Status
              </Button>
              <Button variant="ghost" size="sm" class="text-muted-foreground hover:text-primary">
                Support
              </Button>
            </div>
          </div>
        </div>
      </footer>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '../components/ui/button';
import { Card, CardHeader, CardTitle, CardContent } from '../components/ui/card';
import { Badge } from '../components/ui/badge';
import { Separator } from '../components/ui/separator';
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { 
  ChevronRight, Github, Twitter, Linkedin, Sparkles, Mic, Headphones, FileAudio, 
  Zap, CheckCircle, RefreshCw, Play, ArrowRight, Menu, Star, User, Pause, Volume2, 
  ZoomIn, ZoomOut, Plus, Edit3, MousePointer2, X
} from 'lucide-vue-next';

// Demo functionality
const demoIsPlaying = ref(false);
const demoCurrentTime = ref(0);
const demoDuration = ref(120); // 2 minutes demo
const demoZoom = ref(50);
const demoSelectedLabel = ref(null);
const demoIsDragging = ref(false);
const demoDragStart = ref(0);
const demoDragCurrent = ref(0);
const demoWaveformRef = ref(null);

// Demo labels
const demoLabels = ref([
  { id: 1, name: 'Speech', color: 'hsl(220, 70%, 50%)', description: 'Human speech segments' },
  { id: 2, name: 'Music', color: 'hsl(160, 60%, 45%)', description: 'Musical content' },
  { id: 3, name: 'Noise', color: 'hsl(30, 80%, 55%)', description: 'Background noise' },
  { id: 4, name: 'Silence', color: 'hsl(280, 65%, 60%)', description: 'Silent periods' },
  { id: 5, name: 'Applause', color: 'hsl(340, 75%, 55%)', description: 'Audience reactions' }
]);

// Pre-existing segments (now fully editable)
const demoSegments = ref([
  { id: 1, start: 10, end: 25, label: { id: 1, name: 'Speech', color: 'hsl(220, 70%, 50%)' }, preset: true },
  { id: 2, start: 30, end: 45, label: { id: 2, name: 'Music', color: 'hsl(160, 60%, 45%)' }, preset: true },
  { id: 3, start: 50, end: 60, label: { id: 3, name: 'Noise', color: 'hsl(30, 80%, 55%)' }, preset: true },
  { id: 4, start: 70, end: 85, label: { id: 1, name: 'Speech', color: 'hsl(220, 70%, 50%)' }, preset: true },
]);

const demoNewSegments = ref([]);

// All segments combined for easier manipulation
const allDemoSegments = computed(() => [...demoSegments.value, ...demoNewSegments.value]);

// Waveform data (simulated)
const demoWaveformData = ref(Array.from({ length: 200 }, () => Math.random() * 100));

let demoPlaybackInterval = null;
let isResizing = false;
let resizeSegmentRef = null;
let resizeMode = 'start'; // 'start' or 'end'

const formatTime = (seconds) => {
  const mins = Math.floor(seconds / 60);
  const secs = Math.floor(seconds % 60);
  return `${mins}:${secs.toString().padStart(2, '0')}`;
};

const toggleDemoPlayback = () => {
  demoIsPlaying.value = !demoIsPlaying.value;
  
  if (demoIsPlaying.value) {
    demoPlaybackInterval = setInterval(() => {
      demoCurrentTime.value += 0.1;
      if (demoCurrentTime.value >= demoDuration.value) {
        demoCurrentTime.value = 0;
        demoIsPlaying.value = false;
        clearInterval(demoPlaybackInterval);
      }
    }, 100);
  } else {
    if (demoPlaybackInterval) {
      clearInterval(demoPlaybackInterval);
      demoPlaybackInterval = null;
    }
  }
};

const demoZoomIn = () => {
  demoZoom.value = Math.min(demoZoom.value * 1.2, 200);
};

const demoZoomOut = () => {
  demoZoom.value = Math.max(demoZoom.value * 0.8, 25);
};

const selectDemoLabel = (label) => {
  demoSelectedLabel.value = demoSelectedLabel.value?.id === label.id ? null : label;
};

const handleDemoMouseDown = (e) => {
  if (!demoSelectedLabel.value || !demoWaveformRef.value || isResizing) return;
  
  const rect = demoWaveformRef.value.getBoundingClientRect();
  const x = e.clientX - rect.left;
  const time = (x / rect.width) * demoDuration.value;
  
  demoIsDragging.value = true;
  demoDragStart.value = time;
  demoDragCurrent.value = time;
};

const handleDemoMouseMove = (e) => {
  if (!demoIsDragging.value || !demoWaveformRef.value) return;
  
  const rect = demoWaveformRef.value.getBoundingClientRect();
  const x = e.clientX - rect.left;
  const time = Math.max(0, Math.min((x / rect.width) * demoDuration.value, demoDuration.value));
  
  demoDragCurrent.value = time;
};

const handleDemoMouseUp = () => {
  if (!demoIsDragging.value || !demoSelectedLabel.value) {
    demoIsDragging.value = false;
    return;
  }
  
  const start = Math.min(demoDragStart.value, demoDragCurrent.value);
  const end = Math.max(demoDragStart.value, demoDragCurrent.value);
  
  if (end - start > 1) { // Minimum 1 second
    demoNewSegments.value.push({
      id: Date.now(),
      start,
      end,
      label: demoSelectedLabel.value,
      preset: false
    });
  }
  
  demoIsDragging.value = false;
};

const removeDemoSegment = (segmentId, isPreset = false) => {
  if (isPreset) {
    const index = demoSegments.value.findIndex(s => s.id === segmentId);
    if (index !== -1) {
      demoSegments.value.splice(index, 1);
    }
  } else {
    const index = demoNewSegments.value.findIndex(s => s.id === segmentId);
    if (index !== -1) {
      demoNewSegments.value.splice(index, 1);
    }
  }
};

const startDemoResize = (segment, mode, e) => {
  e.preventDefault();
  e.stopPropagation();
  isResizing = true;
  resizeSegmentRef = segment;
  resizeMode = mode;
  
  const onMove = (e) => {
    if (!isResizing || !resizeSegmentRef) return;
    
    const rect = demoWaveformRef.value?.getBoundingClientRect();
    if (!rect) return;
    
    const x = e.clientX - rect.left;
    const time = (x / rect.width) * demoDuration.value;
    const clampedTime = Math.max(0, Math.min(time, demoDuration.value));
    
    if (resizeMode === 'start') {
      resizeSegmentRef.start = Math.max(0, Math.min(clampedTime, resizeSegmentRef.end - 0.5));
    } else {
      resizeSegmentRef.end = Math.max(resizeSegmentRef.start + 0.5, Math.min(clampedTime, demoDuration.value));
    }
  };
  
  const onUp = () => {
    isResizing = false;
    resizeSegmentRef = null;
    document.removeEventListener('mousemove', onMove);
    document.removeEventListener('mouseup', onUp);
  };
  
  document.addEventListener('mousemove', onMove);
  document.addEventListener('mouseup', onUp);
};

const playDemoSegment = (segment) => {
  demoCurrentTime.value = segment.start;
  if (!demoIsPlaying.value) {
    toggleDemoPlayback();
  }
};

const minimizeSegment = (segment) => {
  const duration = segment.end - segment.start;
  const center = segment.start + duration / 2;
  const minDuration = 0.5;
  
  segment.start = center - minDuration / 2;
  segment.end = center + minDuration / 2;
  
  // Ensure bounds
  if (segment.start < 0) {
    segment.start = 0;
    segment.end = minDuration;
  }
  if (segment.end > demoDuration.value) {
    segment.end = demoDuration.value;
    segment.start = demoDuration.value - minDuration;
  }
};

const expandSegment = (segment) => {
  const center = segment.start + (segment.end - segment.start) / 2;
  const newDuration = Math.min(10, (segment.end - segment.start) * 2); // Max 10 seconds
  
  segment.start = Math.max(0, center - newDuration / 2);
  segment.end = Math.min(demoDuration.value, center + newDuration / 2);
};

const getDemoSegmentStyle = (segment) => {
  const left = (segment.start / demoDuration.value) * 100;
  const width = ((segment.end - segment.start) / demoDuration.value) * 100;
  
  return {
    left: `${left}%`,
    width: `${width}%`,
    backgroundColor: segment.label.color + '40',
    borderColor: segment.label.color,
  };
};

const getDemoPlayheadPosition = () => {
  return (demoCurrentTime.value / demoDuration.value) * 100;
};

// Set default label selection
onMounted(() => {
  if (demoLabels.value.length > 0) {
    demoSelectedLabel.value = demoLabels.value[0];
  }
});

// Cleanup on unmount
onBeforeUnmount(() => {
  if (demoPlaybackInterval) {
    clearInterval(demoPlaybackInterval);
    demoPlaybackInterval = null;
  }
});

const features = [
  {
    icon: Mic,
    title: 'Advanced Segmentation',
    description: 'AI-powered audio segmentation with pixel-perfect precision. Automatically detect speech boundaries, silence, and audio events.'
  },
  {
    icon: Headphones,
    title: 'Smart Annotation',
    description: 'Collaborative annotation tools with real-time feedback. Custom labels, speaker identification, and emotion tagging.'
  },
  {
    icon: FileAudio,
    title: 'Quality Assurance',
    description: 'Multi-tier review system ensuring dataset quality. Automated checks and human validation for perfect training data.'
  },
  {
    icon: Zap,
    title: 'Lightning Fast',
    description: 'Process hours of audio in minutes. Optimized workflows and batch processing for maximum efficiency.'
  },
  {
    icon: RefreshCw,
    title: 'Continuous Learning',
    description: 'AI models that improve with every annotation. Feedback loops and active learning for better accuracy over time.'
  },
  {
    icon: CheckCircle,
    title: 'Enterprise Ready',
    description: 'Scalable infrastructure with enterprise security. SSO, audit logs, and compliance-ready data handling.'
  }
];

const workflowSteps = [
  {
    step: '1',
    title: 'Upload Audio',
    description: 'Support for all major formats with batch processing',
    icon: FileAudio
  },
  {
    step: '2',
    title: 'AI Segmentation',
    description: 'Automatic detection and smart suggestions',
    icon: Mic
  },
  {
    step: '3',
    title: 'Human Review',
    description: 'Expert validation and quality control',
    icon: Headphones
  },
  {
    step: '4',
    title: 'Export Data',
    description: 'Ready-to-use datasets in any format',
    icon: Zap
  },
  {
    step: '5',
    title: 'Train Models',
    description: 'Deploy with confidence using verified data',
    icon: RefreshCw
  }
];

const testimonials = [
  {
    quote: "MENADevsAI reduced our annotation time by 80% while improving quality. The collaboration features are game-changing.",
    name: "Sarah Chen",
    role: "ML Engineer, TechCorp"
  },
  {
    quote: "The accuracy and speed of their segmentation tools helped us launch our voice AI product 3 months ahead of schedule.",
    name: "Marcus Rodriguez",
    role: "Product Manager, VoiceTech"
  },
  {
    quote: "Best annotation platform we've used. The quality assurance system ensures our training data is always perfect.",
    name: "Dr. Emily Watson",
    role: "Research Director, SoundAI"
  }
];

const pricingPlans = [
  {
    name: 'Starter',
    price: '$29',
    description: 'Perfect for small teams and researchers',
    features: [
      '10 hours of audio/month',
      'Basic segmentation tools',
      'Standard support',
      'Export to common formats'
    ],
    featured: false
  },
  {
    name: 'Professional',
    price: '$99',
    description: 'Advanced features for growing teams',
    features: [
      '100 hours of audio/month',
      'AI-powered segmentation',
      'Advanced collaboration tools',
      'Priority support',
      'Custom export formats',
      'Quality assurance workflows'
    ],
    featured: true
  },
  {
    name: 'Enterprise',
    price: 'Custom',
    description: 'Unlimited scale for large organizations',
    features: [
      'Unlimited audio processing',
      'Custom AI model training',
      'Dedicated support team',
      'SSO & enterprise security',
      'Custom integrations',
      'SLA guarantees'
    ],
    featured: false
  }
];

const faqs = ref([
  {
    question: 'What audio formats do you support?',
    answer: 'We support all major audio formats including WAV, MP3, FLAC, M4A, and more. Our system automatically handles format conversion and optimization.',
    open: false
  },
  {
    question: 'How accurate is the AI segmentation?',
    answer: 'Our AI achieves 99.9% accuracy on speech segmentation and 97% on general audio events. All AI suggestions go through human review for final validation.',
    open: false
  },
  {
    question: 'Can I integrate MENADevsAI with my existing workflow?',
    answer: 'Yes! We offer REST APIs, webhooks, and integrations with popular ML platforms like AWS SageMaker, Google Cloud AI, and Azure ML.',
    open: false
  },
  {
    question: 'What about data security and privacy?',
    answer: 'We use enterprise-grade encryption, comply with GDPR and SOC 2, and offer on-premise deployment options for sensitive data.',
    open: false
  },
  {
    question: 'Do you offer custom annotation guidelines?',
    answer: 'Absolutely! We can create custom annotation schemas, train annotators on your specific requirements, and provide dedicated project management.',
    open: false
  }
]);
</script>

<style scoped>
html {
  scroll-behavior: smooth !important;
}
/* Modern Animations */
.glassmorphism {
  background: #fcfcfc;
  backdrop-filter: blur(12px);
  border: 1px solid rgba(255, 255, 255, 0.1);
}

@keyframes blob {
  0% { transform: translate(0px, 0px) scale(1); }
  33% { transform: translate(30px, -50px) scale(1.1); }
  66% { transform: translate(-20px, 20px) scale(0.9); }
  100% { transform: translate(0px, 0px) scale(1); }
}

.animate-blob {
  animation: blob 7s infinite;
}

.animation-delay-2000 {
  animation-delay: 2s;
}

.animation-delay-4000 {
  animation-delay: 4s;
}

@keyframes gradient-x {
  0%, 100% { background-position: 0% 50%; }
  50% { background-position: 100% 50%; }
}

.animate-gradient-x {
  background-size: 200% 200%;
  animation: gradient-x 3s ease infinite;
}

@keyframes expand-width {
  0% { width: 0; }
  100% { width: 100%; }
}

.animate-expand-width {
  animation: expand-width 1.5s ease-out forwards;
}

@keyframes float {
  0%, 100% { transform: translateY(0px); }
  50% { transform: translateY(-20px); }
}

.animate-float {
  animation: float 6s ease-in-out infinite;
}

.wave-bar {
  width: 2px;
  min-height: 4px;
  border-radius: 2px;
  animation: wave 1.5s ease-in-out infinite alternate;
}

@keyframes wave {
  0% { transform: scaleY(0.3); opacity: 0.5; }
  100% { transform: scaleY(1); opacity: 1; }
}

@keyframes dash {
  to { stroke-dashoffset: -1000; }
}

.animate-dash {
  stroke-dasharray: 50 20;
  animation: dash 3s linear infinite;
}

@keyframes fade-in-up {
  from { opacity: 0; transform: translateY(30px); }
  to { opacity: 1; transform: translateY(0); }
}

.animate-fade-in-up {
  animation: fade-in-up 0.8s ease-out forwards;
}

@keyframes fade-in-right {
  from { opacity: 0; transform: translateX(30px); }
  to { opacity: 1; transform: translateX(0); }
}

.animate-fade-in-right {
  animation: fade-in-right 1s ease-out forwards;
}

@keyframes slide-in-left {
  from { opacity: 0; transform: translateX(-30px); }
  to { opacity: 1; transform: translateX(0); }
}

.animate-slide-in-left {
  animation: slide-in-left 0.8s ease-out forwards;
}

@keyframes pulse-slow {
  0%, 100% { opacity: 0.3; }
  50% { opacity: 0.8; }
}

.animate-pulse-slow {
  animation: pulse-slow 4s ease-in-out infinite;
}

.waveform-container {
  display: flex;
  align-items: end;
  gap: 1px;
  padding: 0 1rem;
}
</style>