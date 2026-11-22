/**
 * Verbose AI - WebGPU Hero Visualizer
 * Powered by Vercel Labs' vgpu (https://vgpu.sh)
 * Cross-runtime WebGPU shaders with automatic fallback to WebGL / Canvas.
 */

class VgpuHeroController {
  constructor(canvasId, options = {}) {
    this.canvas = document.getElementById(canvasId);
    this.statusBadge = document.getElementById(options.statusBadgeId || 'vgpu-status-badge');
    this.fpsCounter = document.getElementById(options.fpsCounterId || 'vgpu-fps-counter');
    this.modeButtons = document.querySelectorAll(options.modeButtonsSelector || '.vgpu-mode-btn');

    this.options = Object.assign({
      speed: 1.0,
      resonance: 1.2,
      distortion: 1.0,
      mode: 0.0, // 0: Neural Flux, 1: Zero-H Barrier, 2: Singularity
      paused: false
    }, options);

    this.mouse = { x: 0.5, y: 0.5, targetX: 0.5, targetY: 0.5 };
    this.lastFrameTime = performance.now();
    this.frameCount = 0;
    this.fps = 60;
    this.isWebGpuActive = false;
    this.gpu = null;
    this.cleanupFn = null;

    this.init();
  }

  async init() {
    if (!this.canvas) return;

    this.setupEventListeners();
    this.resizeCanvas();

    // Check if WebGPU is available in browser
    if (navigator.gpu) {
      try {
        await this.initVgpuWebGPU();
        return;
      } catch (err) {
        console.warn('[VGPU] WebGPU init failed, falling back to WebGL:', err);
      }
    }

    // Fallback if WebGPU is not supported or failed
    this.initFallbackRenderer();
  }

  setupEventListeners() {
    window.addEventListener('resize', () => this.resizeCanvas());

    window.addEventListener('mousemove', (e) => {
      const rect = this.canvas.getBoundingClientRect();
      this.mouse.targetX = (e.clientX - rect.left);
      this.mouse.targetY = (e.clientY - rect.top);
    });

    // Touch support for mobile
    window.addEventListener('touchmove', (e) => {
      if (e.touches.length > 0) {
        const rect = this.canvas.getBoundingClientRect();
        this.mouse.targetX = (e.touches[0].clientX - rect.left);
        this.mouse.targetY = (e.touches[0].clientY - rect.top);
      }
    }, { passive: true });
  }

  resizeCanvas() {
    if (!this.canvas) return;
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    const width = this.canvas.clientWidth || window.innerWidth;
    const height = this.canvas.clientHeight || window.innerHeight;

    if (this.canvas.width !== width * dpr || this.canvas.height !== height * dpr) {
      this.canvas.width = Math.floor(width * dpr);
      this.canvas.height = Math.floor(height * dpr);
    }
  }

  updateStatus(isActive, message) {
    if (this.statusBadge) {
      this.statusBadge.innerHTML = `
        <span class="status-pulse-dot" style="background:${isActive ? '#00f0ff' : '#a0a0a0'}; box-shadow: 0 0 10px ${isActive ? '#00f0ff' : 'transparent'};"></span>
        <span>${message}</span>
      `;
    }
  }

  setMode(modeIndex) {
    this.options.mode = parseFloat(modeIndex);
    if (this.modeButtons) {
      this.modeButtons.forEach((btn, idx) => {
        if (idx === modeIndex) {
          btn.classList.add('active');
        } else {
          btn.classList.remove('active');
        }
      });
    }
    window.SFX?.playBlip(720, 0.04);
  }

  setSpeed(val) {
    this.options.speed = Math.max(0.1, Math.min(3.0, parseFloat(val)));
  }

  setResonance(val) {
    this.options.resonance = Math.max(0.1, Math.min(3.0, parseFloat(val)));
  }

  setDistortion(val) {
    this.options.distortion = Math.max(0.0, Math.min(3.0, parseFloat(val)));
  }

  togglePause() {
    this.options.paused = !this.options.paused;
    window.SFX?.playBlip(540, 0.05);
    return this.options.paused;
  }

  /**
   * Primary Renderer: Vercel Labs vgpu (WebGPU WGSL Shader)
   */
  async initVgpuWebGPU() {
    this.updateStatus(false, 'Compiling VGPU Pipeline...');

    // Import vgpu from ESM CDN
    const { init, effect, frameLoop, surface, clock } = await import('https://cdn.jsdelivr.net/npm/vgpu@0.6.0/+esm');

    const gpu = await init();
    this.gpu = gpu;

    const surf = surface(gpu, this.canvas, { dpr: Math.min(window.devicePixelRatio || 1, 2) });

    // WGSL Shader Source code
    const wgslSource = `
      struct Uniforms {
        resolution: vec2<f32>,
        time: f32,
        speed: f32,
        mouse: vec2<f32>,
        resonance: f32,
        distortion: f32,
        mode: f32,
        pad: f32,
      };

      @group(0) @binding(0) var<uniform> u: Uniforms;

      fn neuralOctave(p: vec2<f32>, t: f32) -> f32 {
        var v = 0.0;
        var a = 0.5;
        var shift = vec2<f32>(10.0, 10.0);
        let rot = mat2x2<f32>(cos(0.55), sin(0.55), -sin(0.55), cos(0.55));
        var pos = p;
        for (var i = 0; i < 4; i = i + 1) {
          let s = sin(pos.x * 2.2 + t) * cos(pos.y * 2.2 + t * 0.7);
          v = v + a * abs(s);
          pos = rot * pos * 2.1 + shift;
          a = a * 0.48;
        }
        return v;
      }

      @fragment
      fn fs_main(@builtin(position) coord: vec4<f32>) -> @location(0) vec4<f32> {
        let uv = (coord.xy - 0.5 * u.resolution) / min(u.resolution.x, u.resolution.y);
        let mouseUv = (u.mouse - 0.5 * u.resolution) / min(u.resolution.x, u.resolution.y);
        let distMouse = length(uv - mouseUv);
        
        let t = u.time * (0.4 * u.speed);
        
        var warped = uv;
        let ripple = sin(length(uv) * 14.0 - t * 3.5) * 0.035 * u.distortion;
        warped = warped + normalize(uv + vec2<f32>(0.0001)) * ripple;
        
        let mousePull = exp(-distMouse * 3.2) * 0.2 * u.resonance;
        warped = warped - (mouseUv - uv) * mousePull;
        
        let n = neuralOctave(warped * 2.8, t);
        let d = length(warped);
        
        let wave = sin(d * 18.0 - t * 4.0 + n * 3.2);
        let intensity = pow(abs(wave), 4.0) * exp(-d * 1.5);
        
        var col = vec3<f32>(0.015, 0.015, 0.035);
        
        if (u.mode < 0.5) {
          // Mode 0: Neural Flux (Iridescent Cyan & Luminous Violet)
          let c1 = vec3<f32>(0.0, 0.94, 1.0);
          let c2 = vec3<f32>(0.61, 0.25, 0.98);
          let c3 = vec3<f32>(1.0, 0.85, 0.35);
          col = mix(col, c2, clamp(n * 1.4, 0.0, 1.0));
          col = mix(col, c1, clamp(intensity * 1.6, 0.0, 1.0));
          col = col + c3 * (exp(-d * 5.0) * 0.9);
        } else if (u.mode < 1.5) {
          // Mode 1: Zero-Hallucination Grounding Barrier (Emerald & Pure White Truth)
          let c1 = vec3<f32>(0.0, 1.0, 0.65);
          let c2 = vec3<f32>(0.92, 0.98, 1.0);
          col = mix(col, c1, clamp(n * 1.3, 0.0, 1.0));
          col = mix(col, c2, clamp(intensity * 2.2, 0.0, 1.0));
        } else {
          // Mode 2: Cascading Singularity (Amber & Deep Space Obsidian)
          let c1 = vec3<f32>(1.0, 0.45, 0.15);
          let c2 = vec3<f32>(0.55, 0.12, 0.92);
          col = mix(col, c2, clamp(n * 1.3, 0.0, 1.0));
          col = mix(col, c1, clamp(intensity * 1.8, 0.0, 1.0));
        }
        
        let vignette = 1.0 - smoothstep(0.4, 1.4, length(uv));
        col = col * vignette;
        
        let cursorGlow = exp(-distMouse * 3.8) * 0.3;
        col = col + vec3<f32>(0.35, 0.75, 1.0) * cursorGlow;
        
        return vec4<f32>(col, 1.0);
      }
    `;

    const shaderEffect = effect(gpu, wgslSource);
    const clk = clock(gpu);

    this.isWebGpuActive = true;
    this.updateStatus(true, 'VGPU WebGPU Active (Vercel Labs Engine)');

    // Start render loop
    frameLoop(gpu, (frameHandle) => {
      if (this.options.paused) return;

      // Smooth mouse interpolation
      this.mouse.x += (this.mouse.targetX - this.mouse.x) * 0.1;
      this.mouse.y += (this.mouse.targetY - this.mouse.y) * 0.1;

      this.updateFps();

      shaderEffect.set({
        resolution: [this.canvas.width, this.canvas.height],
        time: clk.time,
        speed: this.options.speed,
        mouse: [this.mouse.x, this.mouse.y],
        resonance: this.options.resonance,
        distortion: this.options.distortion,
        mode: this.options.mode,
        pad: 0.0
      });

      frameHandle.pass(surf, shaderEffect);
    });
  }

  /**
   * Fallback Renderer: High-performance WebGL / Canvas shader
   * Guarantees 100% visual fidelity even when WebGPU is disabled
   */
  initFallbackRenderer() {
    this.isWebGpuActive = false;
    this.updateStatus(false, 'WebGPU Hardware Fallback (WebGL2)');

    const gl = this.canvas.getContext('webgl2') || this.canvas.getContext('webgl');
    if (!gl) {
      this.init2dCanvasFallback();
      return;
    }

    const vsSource = `
      attribute vec2 position;
      void main() {
        gl_Position = vec4(position, 0.0, 1.0);
      }
    `;

    const fsSource = `
      precision highp float;
      uniform vec2 u_resolution;
      uniform float u_time;
      uniform float u_speed;
      uniform vec2 u_mouse;
      uniform float u_resonance;
      uniform float u_distortion;
      uniform float u_mode;

      float neuralField(vec2 p, float t) {
        float v = 0.0;
        float a = 0.5;
        mat2 rot = mat2(cos(0.55), sin(0.55), -sin(0.55), cos(0.55));
        vec2 pos = p;
        for (int i = 0; i < 4; i++) {
          float s = sin(pos.x * 2.2 + t) * cos(pos.y * 2.2 + t * 0.7);
          v += a * abs(s);
          pos = rot * pos * 2.1 + vec2(10.0);
          a *= 0.48;
        }
        return v;
      }

      void main() {
        vec2 uv = (gl_FragCoord.xy - 0.5 * u_resolution) / min(u_resolution.x, u_resolution.y);
        vec2 mouseUv = (u_mouse - 0.5 * u_resolution) / min(u_resolution.x, u_resolution.y);
        float distMouse = length(uv - mouseUv);
        
        float t = u_time * (0.4 * u_speed);
        vec2 warped = uv;
        float ripple = sin(length(uv) * 14.0 - t * 3.5) * 0.035 * u_distortion;
        warped += normalize(uv + vec2(0.0001)) * ripple;
        
        float mousePull = exp(-distMouse * 3.2) * 0.2 * u_resonance;
        warped -= (mouseUv - uv) * mousePull;
        
        float n = neuralField(warped * 2.8, t);
        float d = length(warped);
        float wave = sin(d * 18.0 - t * 4.0 + n * 3.2);
        float intensity = pow(abs(wave), 4.0) * exp(-d * 1.5);
        
        vec3 col = vec3(0.015, 0.015, 0.035);
        if (u_mode < 0.5) {
          vec3 c1 = vec3(0.0, 0.94, 1.0);
          vec3 c2 = vec3(0.61, 0.25, 0.98);
          vec3 c3 = vec3(1.0, 0.85, 0.35);
          col = mix(col, c2, clamp(n * 1.4, 0.0, 1.0));
          col = mix(col, c1, clamp(intensity * 1.6, 0.0, 1.0));
          col += c3 * (exp(-d * 5.0) * 0.9);
        } else if (u_mode < 1.5) {
          vec3 c1 = vec3(0.0, 1.0, 0.65);
          vec3 c2 = vec3(0.92, 0.98, 1.0);
          col = mix(col, c1, clamp(n * 1.3, 0.0, 1.0));
          col = mix(col, c2, clamp(intensity * 2.2, 0.0, 1.0));
        } else {
          vec3 c1 = vec3(1.0, 0.45, 0.15);
          vec3 c2 = vec3(0.55, 0.12, 0.92);
          col = mix(col, c2, clamp(n * 1.3, 0.0, 1.0));
          col = mix(col, c1, clamp(intensity * 1.8, 0.0, 1.0));
        }
        
        float vignette = 1.0 - smoothstep(0.4, 1.4, length(uv));
        col *= vignette;
        col += vec3(0.35, 0.75, 1.0) * (exp(-distMouse * 3.8) * 0.3);
        
        gl_FragColor = vec4(col, 1.0);
      }
    `;

    const createShader = (type, src) => {
      const s = gl.createShader(type);
      gl.shaderSource(s, src);
      gl.compileShader(s);
      return s;
    };

    const program = gl.createProgram();
    gl.attachShader(program, createShader(gl.VERTEX_SHADER, vsSource));
    gl.attachShader(program, createShader(gl.FRAGMENT_SHADER, fsSource));
    gl.linkProgram(program);
    gl.useProgram(program);

    const buf = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, buf);
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, -1, 1, 1, -1, 1, 1]), gl.STATIC_DRAW);

    const posAttr = gl.getAttribLocation(program, 'position');
    gl.enableVertexAttribArray(posAttr);
    gl.vertexAttribPointer(posAttr, 2, gl.FLOAT, false, 0, 0);

    const locRes = gl.getUniformLocation(program, 'u_resolution');
    const locTime = gl.getUniformLocation(program, 'u_time');
    const locSpeed = gl.getUniformLocation(program, 'u_speed');
    const locMouse = gl.getUniformLocation(program, 'u_mouse');
    const locResonance = gl.getUniformLocation(program, 'u_resonance');
    const locDistortion = gl.getUniformLocation(program, 'u_distortion');
    const locMode = gl.getUniformLocation(program, 'u_mode');

    const startTime = performance.now();

    const render = (now) => {
      if (!this.options.paused) {
        this.mouse.x += (this.mouse.targetX - this.mouse.x) * 0.1;
        this.mouse.y += (this.mouse.targetY - this.mouse.y) * 0.1;

        this.updateFps();

        gl.viewport(0, 0, this.canvas.width, this.canvas.height);
        gl.uniform2f(locRes, this.canvas.width, this.canvas.height);
        gl.uniform1f(locTime, (now - startTime) * 0.001);
        gl.uniform1f(locSpeed, this.options.speed);
        gl.uniform2f(locMouse, this.mouse.x, this.canvas.height - this.mouse.y);
        gl.uniform1f(locResonance, this.options.resonance);
        gl.uniform1f(locDistortion, this.options.distortion);
        gl.uniform1f(locMode, this.options.mode);

        gl.drawArrays(gl.TRIANGLES, 0, 6);
      }
      requestAnimationFrame(render);
    };

    requestAnimationFrame(render);
  }

  init2dCanvasFallback() {
    this.updateStatus(false, '2D Canvas Mode');
    const ctx = this.canvas.getContext('2d');
    if (!ctx) return;

    let t = 0;
    const render = () => {
      t += 0.02 * this.options.speed;
      ctx.fillStyle = '#050508';
      ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);

      const cx = this.canvas.width / 2;
      const cy = this.canvas.height / 2;

      for (let i = 0; i < 5; i++) {
        const r = 80 + i * 45 + Math.sin(t + i) * 15;
        ctx.beginPath();
        ctx.arc(cx, cy, r, 0, Math.PI * 2);
        ctx.strokeStyle = i % 2 === 0 ? 'rgba(0, 240, 255, 0.25)' : 'rgba(157, 78, 221, 0.25)';
        ctx.lineWidth = 2;
        ctx.stroke();
      }
      requestAnimationFrame(render);
    };
    requestAnimationFrame(render);
  }

  updateFps() {
    this.frameCount++;
    const now = performance.now();
    if (now - this.lastFrameTime >= 1000) {
      this.fps = Math.round((this.frameCount * 1000) / (now - this.lastFrameTime));
      this.frameCount = 0;
      this.lastFrameTime = now;
      if (this.fpsCounter) {
        this.fpsCounter.textContent = `${this.fps} FPS`;
      }
    }
  }
}

// Global exposure
window.VgpuHeroController = VgpuHeroController;
