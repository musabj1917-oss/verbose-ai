/**
 * Verbose AI - 3D WebGPU Background Engine
 * Powered by Vercel Labs' vgpu (WebGPU Shaders & WGSL Compute)
 * Seamless 3D ambient spatial background for the reading canvas & app interface.
 * Automatically falls back to WebGL2 / Canvas on non-WebGPU devices.
 */

class VgpuAppBackground {
  constructor(canvasId = 'starfield-canvas') {
    this.canvas = document.getElementById(canvasId) || document.getElementById('app-3d-canvas');
    if (!this.canvas) return;

    this.enabled = true; // Always active permanent 3D background
    this.state = 'idle'; // 'idle', 'searching', 'grounding', 'weaving'
    this.modeVal = 0.0;
    this.targetModeVal = 0.0;
    this.speed = 0.6;
    this.targetSpeed = 0.6;
    this.resonance = 0.8;

    this.mouse = { x: 0.5, y: 0.5, targetX: 0.5, targetY: 0.5 };
    this.isWebGpuActive = false;
    this.gpu = null;

    this.init();
  }

  async init() {
    if (!this.canvas) return;

    this.setupListeners();
    this.resize();

    if (navigator.gpu) {
      try {
        await this.initWebGPU();
        return;
      } catch (e) {
        console.warn('[VGPU Background] WebGPU init failed, using WebGL fallback:', e);
      }
    }

    this.initWebGLFallback();
  }

  setupListeners() {
    window.addEventListener('resize', () => this.resize());

    window.addEventListener('mousemove', (e) => {
      const rect = this.canvas.getBoundingClientRect();
      this.mouse.targetX = (e.clientX - rect.left);
      this.mouse.targetY = (e.clientY - rect.top);
    });

    window.addEventListener('touchmove', (e) => {
      if (e.touches.length > 0) {
        const rect = this.canvas.getBoundingClientRect();
        this.mouse.targetX = (e.touches[0].clientX - rect.left);
        this.mouse.targetY = (e.touches[0].clientY - rect.top);
      }
    }, { passive: true });
  }

  resize() {
    if (!this.canvas) return;
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    const parent = this.canvas.parentElement;
    const w = parent ? parent.clientWidth : window.innerWidth;
    const h = parent ? parent.clientHeight : window.innerHeight;

    if (this.canvas.width !== w * dpr || this.canvas.height !== h * dpr) {
      this.canvas.width = Math.floor(w * dpr);
      this.canvas.height = Math.floor(h * dpr);
    }
  }

  setState(name) {
    this.state = name;
    if (name === 'searching') {
      this.targetModeVal = 0.5;
      this.targetSpeed = 1.4;
    } else if (name === 'grounding' || name === 'solving') {
      this.targetModeVal = 1.0;
      this.targetSpeed = 0.9;
    } else if (name === 'weaving') {
      this.targetModeVal = 2.0;
      this.targetSpeed = 1.1;
    } else {
      // idle
      this.targetModeVal = 0.0;
      this.targetSpeed = 0.6;
    }
  }

  setEnabled(val) {
    this.enabled = !!val;
    localStorage.setItem('verbose_3d_bg', this.enabled ? 'true' : 'false');
    if (this.enabled) {
      this.init();
    } else {
      this.initMinimalStarfield();
    }
  }

  /**
   * Primary WebGPU Renderer powered by Vercel Labs vgpu
   */
  async initWebGPU() {
    const { init, effect, frameLoop, surface, clock } = await import('https://cdn.jsdelivr.net/npm/vgpu@0.6.0/+esm');

    const gpu = await init();
    this.gpu = gpu;
    const surf = surface(gpu, this.canvas, { dpr: Math.min(window.devicePixelRatio || 1, 2) });

    // Sophisticated Ambient 3D Spatial Background Shader (WGSL)
    const wgslSource = `
      struct Uniforms {
        resolution: vec2<f32>,
        time: f32,
        speed: f32,
        mouse: vec2<f32>,
        mode: f32,
        resonance: f32,
        pad: vec2<f32>,
      };

      @group(0) @binding(0) var<uniform> u: Uniforms;

      // 3D Spatial Noise
      fn hash31(p: vec3<f32>) -> f32 {
        var p3 = fract(p * 0.1031);
        p3 = p3 + dot(p3, p3.yzx + 33.33);
        return fract((p3.x + p3.y) * p3.z);
      }

      fn spatialLattice(pos: vec2<f32>, t: f32) -> f32 {
        var v = 0.0;
        var a = 0.5;
        let rot = mat2x2<f32>(cos(0.4), sin(0.4), -sin(0.4), cos(0.4));
        var p = pos;
        for (var i = 0; i < 3; i = i + 1) {
          let s = sin(p.x * 1.5 + t) * cos(p.y * 1.5 + t * 0.6);
          v = v + a * abs(s);
          p = rot * p * 1.8 + vec2<f32>(5.0);
          a = a * 0.5;
        }
        return v;
      }

      @fragment
      fn fs_main(@builtin(position) coord: vec4<f32>) -> @location(0) vec4<f32> {
        let uv = (coord.xy - 0.5 * u.resolution) / min(u.resolution.x, u.resolution.y);
        let mouseUv = (u.mouse - 0.5 * u.resolution) / min(u.resolution.x, u.resolution.y);
        let distMouse = length(uv - mouseUv);
        
        let t = u.time * (0.25 * u.speed);
        
        // Subtle 3D perspective warp
        var p = uv * 2.2;
        let mouseGrav = exp(-distMouse * 3.0) * 0.12 * u.resonance;
        p = p - (mouseUv - uv) * mouseGrav;
        
        let lattice = spatialLattice(p, t);
        let d = length(uv);
        
        // Deep obsidian background base
        var col = vec3<f32>(0.012, 0.012, 0.018);
        
        // Subtle harmonic energy
        let pulse = sin(d * 10.0 - t * 2.5 + lattice * 2.5);
        let intensity = pow(abs(pulse), 5.0) * exp(-d * 1.8);
        
        if (u.mode < 0.3) {
          // Idle: Monochromatic deep space with faint cyan shimmer
          let c1 = vec3<f32>(0.0, 0.6, 0.85);
          let c2 = vec3<f32>(0.35, 0.15, 0.65);
          col = mix(col, c2, clamp(lattice * 0.65, 0.0, 1.0));
          col = mix(col, c1, clamp(intensity * 0.8, 0.0, 1.0));
        } else if (u.mode < 0.8) {
          // Searching: Sweeping cyan meridian wave
          let c1 = vec3<f32>(0.0, 0.95, 1.0);
          col = mix(col, c1, clamp(intensity * 1.4 + lattice * 0.5, 0.0, 1.0));
        } else if (u.mode < 1.5) {
          // Grounding / Solving: Emerald anti-hallucination lattice
          let c1 = vec3<f32>(0.0, 0.9, 0.55);
          let c2 = vec3<f32>(0.8, 1.0, 0.9);
          col = mix(col, c1, clamp(lattice * 1.1, 0.0, 1.0));
          col = mix(col, c2, clamp(intensity * 1.5, 0.0, 1.0));
        } else {
          // Weaving: Dual harmonic weave
          let c1 = vec3<f32>(0.7, 0.3, 1.0);
          let c2 = vec3<f32>(1.0, 0.6, 0.2);
          col = mix(col, c1, clamp(lattice * 0.9, 0.0, 1.0));
          col = mix(col, c2, clamp(intensity * 1.2, 0.0, 1.0));
        }
        
        // Atmospheric vignette
        let vig = 1.0 - smoothstep(0.5, 1.5, d);
        col = col * vig;
        
        // Gentle cursor glow
        let cursorGlow = exp(-distMouse * 4.0) * 0.15;
        col = col + vec3<f32>(0.2, 0.5, 0.9) * cursorGlow;
        
        return vec4<f32>(col, 1.0);
      }
    `;

    const shaderEffect = effect(gpu, wgslSource);
    const clk = clock(gpu);
    this.isWebGpuActive = true;

    frameLoop(gpu, (f) => {
      if (!this.enabled) return;

      // Smooth state transitions
      this.modeVal += (this.targetModeVal - this.modeVal) * 0.08;
      this.speed += (this.targetSpeed - this.speed) * 0.08;
      this.mouse.x += (this.mouse.targetX - this.mouse.x) * 0.08;
      this.mouse.y += (this.mouse.targetY - this.mouse.y) * 0.08;

      shaderEffect.set({
        resolution: [this.canvas.width, this.canvas.height],
        time: clk.time,
        speed: this.speed,
        mouse: [this.mouse.x, this.mouse.y],
        mode: this.modeVal,
        resonance: this.resonance,
        pad: [0.0, 0.0]
      });

      f.pass(surf, shaderEffect);
    });
  }

  /**
   * WebGL2 Fallback Renderer
   */
  initWebGLFallback() {
    this.isWebGpuActive = false;
    const gl = this.canvas.getContext('webgl2') || this.canvas.getContext('webgl');
    if (!gl) {
      this.initMinimalStarfield();
      return;
    }

    const vsSource = `
      attribute vec2 position;
      void main() { gl_Position = vec4(position, 0.0, 1.0); }
    `;

    const fsSource = `
      precision highp float;
      uniform vec2 u_res;
      uniform float u_time;
      uniform float u_speed;
      uniform vec2 u_mouse;
      uniform float u_mode;

      float spatialLattice(vec2 pos, float t) {
        float v = 0.0;
        float a = 0.5;
        mat2 rot = mat2(cos(0.4), sin(0.4), -sin(0.4), cos(0.4));
        vec2 p = pos;
        for (int i = 0; i < 3; i++) {
          float s = sin(p.x * 1.5 + t) * cos(p.y * 1.5 + t * 0.6);
          v += a * abs(s);
          p = rot * p * 1.8 + vec2(5.0);
          a *= 0.5;
        }
        return v;
      }

      void main() {
        vec2 uv = (gl_FragCoord.xy - 0.5 * u_res) / min(u_res.x, u_res.y);
        vec2 mouseUv = (u_mouse - 0.5 * u_res) / min(u_res.x, u_res.y);
        float distMouse = length(uv - mouseUv);
        float t = u_time * (0.25 * u_speed);

        vec2 p = uv * 2.2 - (mouseUv - uv) * (exp(-distMouse * 3.0) * 0.12);
        float lattice = spatialLattice(p, t);
        float d = length(uv);
        float wave = sin(d * 10.0 - t * 2.5 + lattice * 2.5);
        float intensity = pow(abs(wave), 5.0) * exp(-d * 1.8);

        vec3 col = vec3(0.012, 0.012, 0.018);
        if (u_mode < 0.3) {
          vec3 c1 = vec3(0.0, 0.6, 0.85);
          vec3 c2 = vec3(0.35, 0.15, 0.65);
          col = mix(col, c2, clamp(lattice * 0.65, 0.0, 1.0));
          col = mix(col, c1, clamp(intensity * 0.8, 0.0, 1.0));
        } else if (u_mode < 0.8) {
          vec3 c1 = vec3(0.0, 0.95, 1.0);
          col = mix(col, c1, clamp(intensity * 1.4 + lattice * 0.5, 0.0, 1.0));
        } else {
          vec3 c1 = vec3(0.0, 0.9, 0.55);
          col = mix(col, c1, clamp(lattice * 1.1 + intensity * 1.5, 0.0, 1.0));
        }

        col *= (1.0 - smoothstep(0.5, 1.5, d));
        col += vec3(0.2, 0.5, 0.9) * (exp(-distMouse * 4.0) * 0.15);
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

    const locRes = gl.getUniformLocation(program, 'u_res');
    const locTime = gl.getUniformLocation(program, 'u_time');
    const locSpeed = gl.getUniformLocation(program, 'u_speed');
    const locMouse = gl.getUniformLocation(program, 'u_mouse');
    const locMode = gl.getUniformLocation(program, 'u_mode');

    const start = performance.now();
    const render = (now) => {
      if (this.enabled) {
        this.modeVal += (this.targetModeVal - this.modeVal) * 0.08;
        this.speed += (this.targetSpeed - this.speed) * 0.08;
        this.mouse.x += (this.mouse.targetX - this.mouse.x) * 0.08;
        this.mouse.y += (this.mouse.targetY - this.mouse.y) * 0.08;

        gl.viewport(0, 0, this.canvas.width, this.canvas.height);
        gl.uniform2f(locRes, this.canvas.width, this.canvas.height);
        gl.uniform1f(locTime, (now - start) * 0.001);
        gl.uniform1f(locSpeed, this.speed);
        gl.uniform2f(locMouse, this.mouse.x, this.canvas.height - this.mouse.y);
        gl.uniform1f(locMode, this.modeVal);

        gl.drawArrays(gl.TRIANGLES, 0, 6);
      }
      requestAnimationFrame(render);
    };
    requestAnimationFrame(render);
  }

  initMinimalStarfield() {
    const ctx = this.canvas.getContext('2d');
    if (!ctx) return;
    ctx.fillStyle = '#030305';
    ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
  }
}

// Auto-initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
  window.app3DBackground = new VgpuAppBackground('starfield-canvas');
});
